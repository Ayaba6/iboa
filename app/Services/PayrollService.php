<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPayment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\PayrollVariable;
use App\Models\SalaryAdvance;
use App\Services\PayrollAccountingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RH-PAIE] Service de calcul de paie — normes Burkina Faso (CNSS + IUTS).
 *
 * [NO-HARDCODE] Tous les taux, plafonds et barèmes sont lus depuis
 * la table payroll_settings via PayrollSetting::forCompany().
 * Aucune constante numérique ne sert de fallback silencieux.
 * Si un paramètre est manquant, PayrollSetting::assertComplete()
 * lève une RuntimeException explicite avec la liste des champs manquants.
 *
 * ─── Configurable dans RH → Paramètres de paie ──────────────────────────────
 *   CNSS salarié  : cnss_employee_rate (%)   + cnss_ceiling (FCFA)
 *   CNSS patronal : cnss_employer_rate (%)
 *   IUTS          : iuts_brackets (JSON) + iuts_abattement_rate (%)
 *   Ancienneté    : anc_rate_per_year (%/an) + anc_rate_max_pct (% max)
 *   HS            : hs_rate_25 / hs_rate_50 / hs_rate_nuit (%)
 *   Effort de paix: effort_paix_enabled + effort_paix_rate (%)
 *   Quotient fam. : parts_base_single/married/widowed + parts_per_child + nb_parts_max
 */
class PayrollService
{

    /** Paramètres chargés depuis la DB pour la session de calcul. */
    private ?PayrollSetting $settings = null;

    /**
     * Buffer des remboursements de prêts à persister après le calcul de tous les employés.
     * Format : [['loan_id' => int, 'employee_id' => int, 'amount' => int], ...]
     */
    private array $pendingLoanPayments = [];

    // ─── Chargement des paramètres ───────────────────────────────────────────
    /**
     * [NO-HARDCODE] Charge les paramètres de paie et vérifie qu'ils sont complets.
     * Lance une RuntimeException si un paramètre requis est null en DB,
     * ce qui évite tout calcul silencieux avec une valeur par défaut codée en dur.
     */
    private function loadSettings(int $companyId): PayrollSetting
    {
        if (! $this->settings || $this->settings->company_id !== $companyId) {
            $this->settings = PayrollSetting::forCompany($companyId);
        }
        // Bloc si un paramètre est manquant — aucun fallback silencieux
        $this->settings->assertComplete();
        return $this->settings;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API publique
    // ─────────────────────────────────────────────────────────────────────────

    /** Crée un bulletin vide (brouillon). */
    public function createRun(array $data): PayrollRun
    {
        $company = Company::findOrFail(Auth::user()->company_id);

        if (PayrollRun::where('company_id', $company->id)
            ->where('period_month', $data['period_month'])
            ->where('period_year',  $data['period_year'])
            ->exists()
        ) {
            throw new \RuntimeException(
                "Un bulletin de paie existe déjà pour {$data['period_month']}/{$data['period_year']}."
            );
        }

        return PayrollRun::create([
            'company_id'     => $company->id,
            'fiscal_year_id' => $company->current_fiscal_year_id,
            'period_month'   => $data['period_month'],
            'period_year'    => $data['period_year'],
            'status'         => 'brouillon',
            'notes'          => $data['notes'] ?? null,
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * Calcule la paie de tous les employés actifs.
     * Intègre les variables mensuelles (HS, absences, avances) saisies.
     */
    public function calculate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            if (!$run->isEditable()) {
                throw new \RuntimeException('Ce bulletin est déjà validé et ne peut plus être recalculé.');
            }

            // Charger les paramètres de paie depuis la DB
            $this->loadSettings($run->company_id);

            // Supprimer les anciens calculs
            $run->items()->delete();
            $this->pendingLoanPayments = [];

            // Charger les employés + leurs données
            $employees = Employee::with([
                    'activeContract',
                    'allowances.type',
                    'allowances.rubric',  // [P1] flags rubriques paramétrables
                    'department',
                    // [P1] prêts actifs avec solde restant > 0
                    'loans' => fn($q) => $q->where('status', 'actif')
                                           ->where('remaining_balance', '>', 0),
                ])
                ->where('company_id', $run->company_id)
                ->where('status', 'actif')
                ->whereHas('activeContract')
                ->orderBy('last_name')
                ->get();

            if ($employees->isEmpty()) {
                throw new \RuntimeException(
                    'Aucun employé actif avec un contrat en cours : impossible de calculer la paie.'
                );
            }

            // Variables mensuelles déjà saisies pour ce run (groupées par employee_id)
            $variables = PayrollVariable::where('payroll_run_id', $run->id)
                ->get()
                ->groupBy('employee_id');

            // Cumuls YTD des runs précédents du même exercice (même year)
            $ytdByEmployee = $this->getYtdCumuls($run);

            $totals = [
                'total_brut'          => 0,
                'total_cnss_employee' => 0,
                'total_cnss_employer' => 0,
                'total_iuts'          => 0,
                'total_net'           => 0,
                'employee_count'      => 0,
            ];

            foreach ($employees as $employee) {
                $empVars = $variables->get($employee->id, collect());
                $ytd     = $ytdByEmployee[$employee->id] ?? ['brut' => 0, 'cnss' => 0, 'iuts' => 0, 'net' => 0];

                $item = $this->calculateItem($employee, $run, $empVars, $ytd);
                $run->items()->create($item);

                $totals['total_brut']          += $item['salaire_brut'];
                $totals['total_cnss_employee'] += $item['cnss_employee'];
                $totals['total_cnss_employer'] += $item['cnss_employer'];
                $totals['total_iuts']          += $item['iuts_amount'];
                $totals['total_net']           += $item['salaire_net'];
                $totals['employee_count']++;
            }

            // Marquer les avances approuvées comme récupérées
            $this->recoverAdvances($run);

            // [P1] Créer les lignes de remboursement de prêts et MAJ des soldes
            $this->processLoanPayments($run);

            // Snapshot des paramètres réglementaires utilisés : un changement
            // ultérieur de barème/taux ne doit JAMAIS altérer la lecture d'un
            // bulletin déjà calculé/validé. Source d'audit du run.
            $snapshot = [
                'captured_at'            => now()->toIso8601String(),
                'regulation_version'     => 'BF-' . now()->format('Y-m-d'),
                'iuts_brackets'          => $this->settings->iuts_brackets,
                'iuts_family_reductions' => $this->settings->iuts_family_reductions,
                'iuts_max_charges'       => $this->settings->iuts_max_charges,
                'iuts_abattement_rate'   => $this->settings->iuts_abattement_rate,
                'cnss_ceiling'           => $this->settings->cnss_ceiling,
                'cnss_annual_ceiling'    => $this->settings->cnss_annual_ceiling,
                'cnss_employee_rate'     => $this->settings->cnss_employee_rate,
                'cnss_employer_pension_rate' => $this->settings->cnss_employer_pension_rate,
                'cnss_employer_rp_rate'  => $this->settings->cnss_employer_rp_rate,
                'cnss_employer_pf_rate'  => $this->settings->cnss_employer_pf_rate,
                'cnss_employer_rate'     => $this->settings->cnss_employer_rate,
                'smig'                   => $this->settings->smig,
                'effort_paix_enabled'    => $this->settings->effort_paix_enabled,
                'effort_paix_rate'       => $this->settings->effort_paix_rate,
            ];

            $run->update(array_merge($totals, [
                'status' => 'calcule',
                'calculation_parameters_snapshot' => $snapshot,
            ]));
            return $run->fresh('items.employee');
        });
    }

    /**
     * Valide le bulletin (statut : valide) + génère l'écriture comptable.
     *
     * TRANSACTIONNEL : validation, écriture comptable et mise à jour de statut
     * réussissent ensemble ou sont annulées ensemble. L'ancien comportement
     * (catch qui avalait l'échec comptable) produisait des runs « validés »
     * sans écriture, ou des écritures orphelines après suppression du run.
     *
     * IDEMPOTENT : lockForUpdate + contrôle de statut + contrôle
     * journal_entry_id — un double clic, une relance ou un timeout ne peut
     * jamais générer deux écritures pour le même run.
     */
    public function validate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);

            // [SEC-PHASE2 §2] Maker-checker : le préparateur du run ne le valide pas définitivement
            app(\App\Services\MakerCheckerService::class)->assert($run->created_by, 'payroll_run.validate', "le run de paie {$run->period_month}/{$run->period_year}", $run);

            if ($run->status !== 'calcule') {
                throw new \RuntimeException('Seuls les bulletins calculés peuvent être validés.');
            }

            if ((int) $run->employee_count === 0) {
                throw new \RuntimeException('Ce bulletin ne contient aucun employé : recalculez la paie avant de valider.');
            }

            // Clé d'idempotence : une écriture déjà rattachée = validation déjà
            // passée (ou en cours dans une autre requête, bloquée par le lock).
            if ($run->journal_entry_id !== null) {
                throw new \RuntimeException(
                    "Ce bulletin possède déjà une écriture comptable (#{$run->journal_entry_id}) — validation déjà effectuée."
                );
            }

            $run->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            // Écriture comptable : un échec ici annule TOUTE la validation.
            app(PayrollAccountingService::class)->generateForRun($run->fresh());

            $run = $run->fresh();
            if ($run->journal_entry_id === null) {
                throw new \RuntimeException(
                    'La comptabilisation de la paie n\'a produit aucune écriture — validation annulée.'
                );
            }

            return $run;
        });
    }

    /** Marque comme payé. */
    public function markPaid(PayrollRun $run, string $paidAt): PayrollRun
    {
        if ($run->status !== 'valide') {
            throw new \RuntimeException('Seuls les bulletins validés peuvent être marqués comme payés.');
        }
        $run->update(['status' => 'paye', 'paid_at' => $paidAt]);
        return $run->fresh();
    }

    /** Simulation individuelle sans enregistrement. */
    public function simulate(Employee $employee, ?Collection $variables = null): array
    {
        $this->loadSettings($employee->company_id);
        return $this->calculateItem($employee, null, $variables ?? collect(), ['brut'=>0,'cnss'=>0,'iuts'=>0,'net'=>0]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calcul cœur
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateItem(
        Employee $employee,
        ?PayrollRun $run,
        Collection $variables,
        array $ytd
    ): array {
        $contract   = $employee->activeContract;
        $baseSalary = $contract ? (int) $contract->base_salary : 0;

        // ─── Jours travaillés (depuis les variables si absence) ───────────────
        $absenceDays = 0;
        $absenceVars = $variables->filter(fn($v) => in_array($v->type, ['absence_injust','absence_maladie','absence_cp']));
        foreach ($absenceVars as $v) {
            $absenceDays += $v->qty;
        }
        // [NO-HARDCODE] Paramètres lus depuis la DB — assertComplete() garantit qu'ils sont non-null
        $workDays  = $this->settings->work_days_month;
        $workHours = $this->settings->work_hours_day;

        $workedDays = max(0, $workDays - $absenceDays);
        $totalDays  = $workDays;

        // ─── Salaire de base (proratisé si absence) ───────────────────────────
        $proratedBase = $absenceDays > 0
            ? (int) round($baseSalary * $workedDays / $totalDays)
            : $baseSalary;

        // ─── Déduction pour absences ──────────────────────────────────────────
        $dailyRate    = $totalDays > 0 ? $baseSalary / $totalDays : 0;
        $absenceAmount= (int) round($dailyRate * $absenceDays);

        // ─── Taux horaire pour HS ─────────────────────────────────────────────
        $hourlyRate = $totalDays > 0 && $workHours > 0
            ? $baseSalary / ($totalDays * $workHours)
            : 0;

        // ─── Heures supplémentaires ───────────────────────────────────────────
        $hs25Hours = $hs25Amount = 0;
        $hs50Hours = $hs50Amount = 0;
        $hsNuitHours = $hsNuitAmount = 0;

        // [NO-HARDCODE] Majorations HS depuis la DB
        $hsRate25   = 1 + ($this->settings->hs_rate_25   / 100);
        $hsRate50   = 1 + ($this->settings->hs_rate_50   / 100);
        $hsRateNuit = 1 + ($this->settings->hs_rate_nuit / 100);

        foreach ($variables->filter(fn($v) => $v->type === 'hs_25') as $v) {
            $hs25Hours  += $v->qty;
            $hs25Amount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRate25);
        }
        foreach ($variables->filter(fn($v) => $v->type === 'hs_50') as $v) {
            $hs50Hours  += $v->qty;
            $hs50Amount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRate50);
        }
        foreach ($variables->filter(fn($v) => $v->type === 'hs_nuit') as $v) {
            $hsNuitHours  += $v->qty;
            $hsNuitAmount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRateNuit);
        }

        // ─── Ancienneté automatique ──────────────────────────────────────────
        // [NO-HARDCODE] Taux et plafond lus depuis payroll_settings :
        //   anc_rate_per_year : % par année complète de service (BF: 2 %)
        //   anc_rate_max_pct  : plafond cumulé (BF: 25 %)
        // Toujours imposable (inclus dans brut, base CNSS et base IUTS).
        // Calculé automatiquement pour TOUS les employés avec une date d'embauche.
        $ancRate   = 0;
        $ancAmount = 0;

        if ($employee->hiring_date && $run !== null) {
            $periodDate     = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
            $yearsOfService = (int) $employee->hiring_date->diffInYears($periodDate);
            $ancRate        = min($yearsOfService * $this->settings->anc_rate_per_year, $this->settings->anc_rate_max_pct);
            $ancAmount      = $ancRate > 0 ? (int) round($baseSalary * $ancRate / 100) : 0;
        }

        // ─── Primes et indemnités FIXES ──────────────────────────────────────
        // [P1] Support rubriques : si l'allocation a un pay_rubric_id actif,
        // ses flags (is_in_brut, is_cnss_base, is_iuts_base) remplacent ceux de
        // payroll_allowance_type. Comportement identique pour allowances sans rubrique.
        // Les allocations de type ANCIENNETE sont exclues : gérées ci-dessus.
        $activeAllowances = $employee->allowances->filter(
            fn($a) => $a->is_active && $a->type?->code !== 'ANCIENNETE'
        );

        $taxableAllowances    = $ancAmount; // ancienneté incluse d'emblée (toujours taxable)
        $nonTaxableAllowances = 0;
        $cnssExclusions       = 0; // montants dans brut mais exclus de la base CNSS
        $iutsExclusions       = 0; // montants dans brut mais exclus de la base IUTS

        // Contexte partiel pour formules basées sur salaire_base uniquement
        $baseContext = ['salaire_base' => $proratedBase];

        foreach ($activeAllowances as $allowance) {
            $rubric    = $allowance->rubric;
            $amount    = (int) $allowance->amount;

            if ($rubric && $rubric->is_active) {
                // ── Rubrique paramétrée : ses flags font foi ─────────────────
                $isInBrut   = $rubric->is_in_brut;
                $isCnssBase = $rubric->is_cnss_base;
                $isIutsBase = $rubric->is_iuts_base ?? $rubric->is_taxable;

                // Si montant non saisi sur l'allocation et rubrique auto-calculable
                if ($amount === 0 && $rubric->calc_type !== 'manuel') {
                    $amount = $rubric->compute($baseContext);
                }
            } else {
                // ── Pas de rubrique → comportement historique inchangé ───────
                $isTaxable  = $allowance->type?->is_taxable ?? true;
                $isInBrut   = $isTaxable;          // taxable = dans le brut
                $isCnssBase = $allowance->type?->is_social_charged ?? $isTaxable;
                $isIutsBase = $isTaxable;
            }

            if ($isInBrut) {
                $taxableAllowances += $amount;
                if (! $isCnssBase) {
                    $cnssExclusions += $amount;
                }
                if (! $isIutsBase) {
                    $iutsExclusions += $amount;
                }
            } else {
                $nonTaxableAllowances += $amount;
            }
        }

        // ─── Primes/gains ponctuels (variables) ──────────────────────────────
        $primesExcept  = 0;
        $autresGains   = 0;

        foreach ($variables->filter(fn($v) => $v->is_gain && !in_array($v->type,['hs_25','hs_50','hs_nuit'])) as $v) {
            if ($v->type === 'prime_exceptionnelle') {
                $primesExcept += (int) abs($v->amount);
            } elseif ($v->is_taxable) {
                $taxableAllowances += (int) abs($v->amount);
            } else {
                $autresGains += (int) abs($v->amount);
            }
        }

        // ─── Retenues ponctuelles ─────────────────────────────────────────────
        $avancesDeductions = 0;
        $autresRetenues    = 0;

        foreach ($variables->filter(fn($v) => !$v->is_gain && !in_array($v->type,['absence_injust','absence_maladie','absence_cp'])) as $v) {
            if ($v->type === 'avance_deduction') {
                $avancesDeductions += (int) abs($v->amount);
            } else {
                $autresRetenues += (int) abs($v->amount);
            }
        }

        // ─── Salaire BRUT ─────────────────────────────────────────────────────
        // = base proratisé + HS + primes taxables + primes exceptionnelles
        $salaireBrut = $proratedBase
            + $hs25Amount + $hs50Amount + $hsNuitAmount
            + $taxableAllowances
            + $primesExcept;

        // ─── CNSS ────────────────────────────────────────────────────────────
        // [NO-HARDCODE] Taux et plafond lus depuis la DB — jamais depuis des constantes
        // Base CNSS = MIN(salaire soumis à cotisation, plafond mensuel 800 000 BF)
        $cnssPlafond  = $this->settings->cnss_ceiling;
        $cnssEmpRate  = $this->settings->cnss_employee_rate;

        // [P1] Exclure les éléments is_cnss_base=false de la base cotisable
        $cnssBase     = min(max(0, $salaireBrut - $cnssExclusions), $cnssPlafond);
        $cnssEmployee = (int) round($cnssBase * $cnssEmpRate / 100);

        // Ventilation patronale BF : pension 8,5 % + risques pro 1,5 % + PF 6 %.
        // Chaque composante est arrondie séparément (traçabilité comptable et
        // déclarative) ; le total patronal est la somme des trois.
        $cnssEmployerPension = (int) round($cnssBase * $this->settings->cnss_employer_pension_rate / 100);
        $cnssEmployerRp      = (int) round($cnssBase * $this->settings->cnss_employer_rp_rate / 100);
        $cnssEmployerPf      = (int) round($cnssBase * $this->settings->cnss_employer_pf_rate / 100);
        $cnssEmployer        = $cnssEmployerPension + $cnssEmployerRp + $cnssEmployerPf;

        // ─── IUTS ────────────────────────────────────────────────────────────
        // [P1] Exclure les éléments is_iuts_base=false de la base imposable
        // [P3.C] Appliquer l'abattement forfaitaire pour frais professionnels
        //         (BF CGI Art. 130 : 20 % par défaut, configurable dans PayrollSetting).
        $salaireImposableBrut = max(0, $salaireBrut - $iutsExclusions - $cnssEmployee);
        // [NO-HARDCODE] Abattement IUTS depuis la DB (BF CGI Art. 130 : 20 % configurable)
        $abattementRate       = $this->settings->iuts_abattement_rate;
        $salaireImposable     = (int) round($salaireImposableBrut * (1 - $abattementRate / 100));

        // [NO-HARDCODE] Barème progressif sur le revenu imposable TOTAL, puis
        // réduction pour charges de famille sur l'IUTS brut (régime légal BF —
        // remplace l'ancien quotient familial par parts depuis le 17/07/2026).
        $familyCharges = $this->settings->computeCharges((int) ($employee->nb_children ?? 0));
        $iutsDetail    = $this->settings->computeIutsDetail($salaireImposable, $familyCharges);
        $iuts          = $iutsDetail['net'];

        // ─── Effort de paix ──────────────────────────────────────────────────
        // [NO-HARDCODE] Taux et activation lus depuis la DB
        // Base légale BF : brut total (incl. non-imposables) − CNSS salarié − IUTS
        $effortPaix = 0;
        if ($this->settings->effort_paix_enabled) {
            $effortPaixBase = max(0, $salaireBrut + $nonTaxableAllowances + $autresGains
                                     - $cnssEmployee - $iuts);
            $effortPaix     = (int) round($effortPaixBase * $this->settings->effort_paix_rate / 100);
        }

        // ─── Prêts salariés — déduction automatique ──────────────────────────
        // [P1] Calcule et bufferise les remboursements mensuels des prêts actifs.
        // Ne s'applique pas en mode simulation ($run === null).
        $loanDeductions = 0;
        if ($run !== null) {
            $activeLoans = $employee->relationLoaded('loans')
                ? $employee->loans->where('status', 'actif')->where('remaining_balance', '>', 0)
                : EmployeeLoan::where('employee_id', $employee->id)
                              ->where('status', 'actif')
                              ->where('remaining_balance', '>', 0)
                              ->get();

            foreach ($activeLoans as $loan) {
                $deductible = min((int) $loan->monthly_deduction, (int) $loan->remaining_balance);
                if ($deductible > 0) {
                    $loanDeductions += $deductible;
                    $this->pendingLoanPayments[] = [
                        'loan_id'     => $loan->id,
                        'employee_id' => $employee->id,
                        'amount'      => $deductible,
                    ];
                }
            }
        }

        // ─── Net à payer ──────────────────────────────────────────────────────
        $salaireNet = $salaireBrut
            + $nonTaxableAllowances
            + $autresGains
            - $cnssEmployee
            - $iuts
            - $effortPaix        // [P3.B]
            - $avancesDeductions
            - $loanDeductions
            - $autresRetenues;

        $coutEmployeur = $salaireBrut + $cnssEmployer;

        // ─── Cumuls YTD ───────────────────────────────────────────────────────
        $cumulBrutYtd = $ytd['brut'] + $salaireBrut + $nonTaxableAllowances;
        $cumulCnssYtd = $ytd['cnss'] + $cnssEmployee;
        $cumulIutsYtd = $ytd['iuts'] + $iuts;
        $cumulNetYtd  = $ytd['net']  + max(0, $salaireNet);

        return [
            // ─ Snapshot
            'employee_id'                  => $employee->id,
            'employee_name'                => $employee->full_name,
            'employee_matricule'           => $employee->matricule,
            'job_title'                    => $employee->job_title,
            'department_name'              => $employee->department?->name,
            // ─ Base
            'base_salary'                  => $proratedBase,
            'worked_days'                  => $workedDays,
            'total_days'                   => $totalDays,
            // ─ Ancienneté automatique (BF Art. 109)
            'anc_rate'                     => $ancRate,
            'anc_amount'                   => $ancAmount,
            // ─ Primes fixes
            'total_allowances_taxable'     => $taxableAllowances,
            'total_allowances_non_taxable' => $nonTaxableAllowances,
            // ─ Heures supplémentaires
            'hs_25_hours'                  => $hs25Hours,
            'hs_25_amount'                 => $hs25Amount,
            'hs_50_hours'                  => $hs50Hours,
            'hs_50_amount'                 => $hs50Amount,
            'hs_nuit_hours'                => $hsNuitHours,
            'hs_nuit_amount'               => $hsNuitAmount,
            // ─ Absences
            'absence_days'                 => $absenceDays,
            'absence_amount'               => $absenceAmount,
            // ─ Ponctuel
            'primes_exceptionnelles'       => $primesExcept,
            'autres_gains'                 => $autresGains,
            'avances_deductions'           => $avancesDeductions,
            'loan_deductions'              => $loanDeductions,
            'autres_retenues'              => $autresRetenues,
            // ─ Cotisations
            'salaire_brut'                 => $salaireBrut,
            'cnss_base'                    => $cnssBase,
            'cnss_employee'                => $cnssEmployee,
            'cnss_employer'                => $cnssEmployer,
            'cnss_employer_pension'        => $cnssEmployerPension,
            'cnss_employer_rp'             => $cnssEmployerRp,
            'cnss_employer_pf'             => $cnssEmployerPf,
            'salaire_imposable'            => $salaireImposable,
            'nb_parts'                     => 0, // legacy quotient familial — plus utilisé
            'family_charges'               => $familyCharges,
            'iuts_amount'                  => $iuts,
            'iuts_detail'                  => $iutsDetail,
            'effort_paix_amount'           => $effortPaix,   // [P3.B]
            'salaire_net'                  => max(0, $salaireNet),
            'cout_employeur'               => $coutEmployeur,
            // ─ YTD
            'cumul_brut_ytd'               => $cumulBrutYtd,
            'cumul_cnss_ytd'               => $cumulCnssYtd,
            'cumul_iuts_ytd'               => $cumulIutsYtd,
            'cumul_net_ytd'                => $cumulNetYtd,
        ];
    }

    /**
     * Cumuls YTD : somme des bulletins validés/payés du même exercice
     * (même année civile), antérieurs au mois en cours.
     */
    private function getYtdCumuls(PayrollRun $run): array
    {
        $rows = DB::table('payroll_items')
            ->join('payroll_runs', 'payroll_items.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $run->company_id)
            ->where('payroll_runs.period_year', $run->period_year)
            ->where('payroll_runs.period_month', '<', $run->period_month)
            ->whereIn('payroll_runs.status', ['valide', 'paye'])
            ->select(
                'payroll_items.employee_id',
                DB::raw('SUM(salaire_brut + COALESCE(total_allowances_non_taxable, 0)) as brut'),
                DB::raw('SUM(cnss_employee)  as cnss'),
                DB::raw('SUM(iuts_amount)    as iuts'),
                DB::raw('SUM(salaire_net)    as net')
            )
            ->groupBy('payroll_items.employee_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->employee_id] = [
                'brut' => (int) $row->brut,
                'cnss' => (int) $row->cnss,
                'iuts' => (int) $row->iuts,
                'net'  => (int) $row->net,
            ];
        }
        return $result;
    }

    /**
     * Marque les avances approuvées comme récupérées dans ce bulletin.
     */
    /**
     * [P1] Crée les lignes EmployeeLoanPayment et met à jour le solde/statut
     * de chaque prêt à partir du buffer rempli par calculateItem().
     * Appelée dans une transaction DB (héritée de calculate()).
     */
    private function processLoanPayments(PayrollRun $run): void
    {
        if (empty($this->pendingLoanPayments)) {
            return;
        }

        foreach ($this->pendingLoanPayments as $entry) {
            // Verrouillage pour éviter les doubles traitements (recalcul concurrent)
            $loan = EmployeeLoan::lockForUpdate()->find($entry['loan_id']);
            if (! $loan || $loan->status !== 'actif' || $loan->remaining_balance <= 0) {
                continue;
            }

            $actualAmount = min((int) $entry['amount'], (int) $loan->remaining_balance);
            $balanceAfter = max(0, (int) $loan->remaining_balance - $actualAmount);

            EmployeeLoanPayment::create([
                'employee_loan_id' => $loan->id,
                'payroll_run_id'   => $run->id,
                'period_month'     => $run->period_month,
                'period_year'      => $run->period_year,
                'amount'           => $actualAmount,
                'balance_after'    => $balanceAfter,
                'notes'            => "Déduction automatique — paie {$run->period_label}",
                'created_by'       => Auth::id(),
            ]);

            $loan->remaining_balance = $balanceAfter;
            if ($balanceAfter <= 0) {
                $loan->status   = 'rembourse';
                $loan->end_date = now()->toDateString();
            }
            $loan->save();
        }

        $this->pendingLoanPayments = [];
    }

    private function recoverAdvances(PayrollRun $run): void
    {
        // Les avances récupérées sont saisies manuellement via PayrollVariable (avance_deduction).
        // On marque ici les SalaryAdvance correspondantes comme remboursées.
        $empIds = $run->items()->pluck('employee_id');

        SalaryAdvance::where('company_id', $run->company_id)
            ->whereIn('employee_id', $empIds)
            ->where('status', 'approuve')
            ->whereNull('recovered_in_run_id')
            ->whereHas('employee', function ($q) use ($run) {
                // Vérifie que la variable avance_deduction existe pour cet employé dans ce run
                $q->whereHas('payrollVariables', fn($pv) =>
                    $pv->where('payroll_run_id', $run->id)->where('type', 'avance_deduction')
                );
            })
            ->update([
                'status'               => 'rembourse',
                'recovered_in_run_id'  => $run->id,
                'recovered_at'         => now()->toDateString(),
            ]);
    }
}
