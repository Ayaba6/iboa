<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [RH-COMPTA] Génère l'écriture comptable de paie selon le plan SYSCOHADA.
 *
 * Écriture type lors de la validation d'un bulletin :
 *
 *   D 661 — Appointements et salaires          = Σ salaires bruts taxables
 *   D 664 — Charges sociales patronales         = Σ CNSS patronal
 *   D 671 — Indemnités et allocations diverses  = Σ indemnités non imposables (transport, logement…)
 *   ─────────────────────────────────────────────────────────────────
 *   C 422 — Personnel, rémunérations dues       = Σ nets à payer (inclut indemnités non imposables)
 *   C 451 — CNSS (salarié + patronal)           = Σ CNSS total
 *   C 447 — État, impôts retenus à la source    = Σ IUTS + Effort de paix salarié
 *
 * Vérification :
 *   D661 + D664 + D671 = C422 + C451 + C447
 *   ⟺ brut + CNSS_pat + nonTax = net + CNSS_tot + IUTS + EP   ✓
 */
class PayrollAccountingService
{
    // ─── Correspondance code → libellé par défaut ────────────────────────────
    private const ACCOUNT_DEFAULTS = [
        '661' => ['name' => 'Appointements et salaires',                    'type' => 'charge'],
        '664' => ['name' => 'Charges sociales patronales',                  'type' => 'charge'],
        '671' => ['name' => 'Indemnités et allocations diverses du personnel','type' => 'charge'],
        '422' => ['name' => 'Personnel, rémunérations dues',                'type' => 'passif'],
        '451' => ['name' => 'Caisse nationale de sécurité sociale',         'type' => 'passif'],
        '447' => ['name' => 'État — impôts retenus à la source',            'type' => 'passif'],
    ];

    /**
     * Génère ou régénère l'écriture de paie pour un PayrollRun validé.
     *
     * @throws \RuntimeException si le run n'est pas validé
     */
    public function generateForRun(PayrollRun $run): JournalEntry
    {
        if (! in_array($run->status, ['valide', 'paye'])) {
            throw new \RuntimeException('Seuls les bulletins validés peuvent être journalisés.');
        }

        return DB::transaction(function () use ($run) {

            // ── 1. Retrouver les totaux ───────────────────────────────────────
            $totalBrut       = (int) ($run->total_brut          ?? 0);
            $totalCnssEmp    = (int) ($run->total_cnss_employee  ?? 0);
            $totalCnssPat    = (int) ($run->total_cnss_employer  ?? 0);
            $totalIuts       = (int) ($run->total_iuts           ?? 0);
            $totalNet        = (int) ($run->total_net            ?? 0);

            // Agréger les éléments non-imposables et effort de paix depuis les items
            // (absents de payroll_runs, mais nécessaires pour équilibrer l'écriture SYSCOHADA)
            $itemAgg = DB::table('payroll_items')
                ->where('payroll_run_id', $run->id)
                ->selectRaw('COALESCE(SUM(total_allowances_non_taxable), 0) as non_taxable,
                             COALESCE(SUM(effort_paix_amount), 0)            as effort_paix,
                             COALESCE(SUM(cnss_employer_pension), 0)         as cnss_pension,
                             COALESCE(SUM(cnss_employer_rp), 0)              as cnss_rp,
                             COALESCE(SUM(cnss_employer_pf), 0)              as cnss_pf')
                ->first();
            $totalNonTaxable  = (int) ($itemAgg->non_taxable  ?? 0);
            $totalEffortPaix  = (int) ($itemAgg->effort_paix  ?? 0);
            // Ventilation patronale BF (pension / risques pro / prestations familiales).
            // Un run calculé avant la ventilation (colonnes à 0) retombe sur la
            // ligne globale legacy.
            $cnssPension = (int) ($itemAgg->cnss_pension ?? 0);
            $cnssRp      = (int) ($itemAgg->cnss_rp      ?? 0);
            $cnssPf      = (int) ($itemAgg->cnss_pf      ?? 0);
            $hasVentilation = ($cnssPension + $cnssRp + $cnssPf) === $totalCnssPat
                && $totalCnssPat > 0;

            // Équation SYSCOHADA :
            //   D = brut + CNSS_pat + nonTaxable
            //   C = net  + CNSS_tot + IUTS + EP
            //   D = C  (car net = brut + nonTax − CNSS_emp − IUTS − EP)
            $debitTotal  = $totalBrut + $totalCnssPat + $totalNonTaxable;
            $creditTotal = $totalNet  + ($totalCnssEmp + $totalCnssPat) + $totalIuts + $totalEffortPaix;

            // Tolérance ±1 FCFA pour les arrondis entiers XOF uniquement
            if (abs($debitTotal - $creditTotal) > 1) {
                Log::warning("[PayrollAccounting] Run #{$run->id} — déséquilibre résiduel: D={$debitTotal} C={$creditTotal} (vérifier effort_paix et non_taxable)");
            }

            // ── 2. Charger / créer les comptes ────────────────────────────────
            $accounts = $this->resolveAccounts($run->company_id);

            // ── 3. Journal OD et exercice ─────────────────────────────────────
            $journalType = JournalType::where('company_id', $run->company_id)
                ->where(fn($q) => $q->where('code','OD')
                    ->orWhere('type','operations_diverses'))
                ->first();

            if (! $journalType) {
                // Créer un journal OD si inexistant
                $journalType = JournalType::create([
                    'company_id' => $run->company_id,
                    'code'       => 'OD',
                    'name'       => 'Opérations Diverses',
                    'type'       => 'operations_diverses',
                    'is_active'  => true,
                ]);
            }

            $fiscalYear = FiscalYear::where('is_current', true)->first()
                ?? FiscalYear::orderByDesc('id')->first();

            // ── 4. Supprimer une écriture existante (re-journalisation) ───────
            if ($run->journal_entry_id) {
                $old = JournalEntry::find($run->journal_entry_id);
                if ($old && $old->status === 'brouillon') {
                    $old->lines()->delete();
                    $old->delete();
                }
            }

            // ── 5. Numéro d'écriture ──────────────────────────────────────────
            $number = $this->nextNumber($run->company_id, $journalType->id);

            // ── 6. Créer l'écriture ───────────────────────────────────────────
            $entryDate = now()->setDay(min(
                now()->daysInMonth,
                $run->period_month == now()->month ? now()->day : 28
            ))->setMonth($run->period_month)->setYear($run->period_year)->toDateString();

            $entry = JournalEntry::create([
                'company_id'      => $run->company_id,
                'journal_type_id' => $journalType->id,
                'fiscal_year_id'  => $fiscalYear?->id,
                'number'          => $number,
                'entry_date'      => $entryDate,
                'value_date'      => $entryDate,
                'reference'       => "PAIE-{$run->period_year}-{$run->period_month}",
                'description'     => "Paie du mois de {$run->period_label} — {$run->employee_count} employé(s)",
                'status'          => 'brouillon',
                'total_debit'     => $debitTotal,   // brut + CNSS_pat + nonTaxable
                'total_credit'    => $debitTotal,   // forcé égal (l'arrondi ±1 est traité post-insert)
                'created_by'      => Auth::id(),
            ]);

            // ── 7. Lignes de l'écriture ───────────────────────────────────────
            $lines = [];
            $sort  = 1;

            // Débits
            if ($totalBrut > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['661']->id,
                    'label'             => "Salaires bruts — {$run->period_label}",
                    'debit'             => $totalBrut,
                    'credit'            => 0,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
            if ($hasVentilation) {
                // Trois lignes distinctes : la traçabilité comptable et
                // déclarative de la ventilation patronale est obligatoire.
                foreach ([
                    ['montant' => $cnssPension, 'label' => 'CNSS patronal — pension'],
                    ['montant' => $cnssRp,      'label' => 'CNSS patronal — risques professionnels'],
                    ['montant' => $cnssPf,      'label' => 'CNSS patronal — prestations familiales'],
                ] as $composante) {
                    if ($composante['montant'] <= 0) {
                        continue;
                    }
                    $lines[] = [
                        'journal_entry_id'  => $entry->id,
                        'account_id'        => $accounts['664']->id,
                        'label'             => "{$composante['label']} — {$run->period_label}",
                        'debit'             => $composante['montant'],
                        'credit'            => 0,
                        'sort_order'        => $sort++,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }
            } elseif ($totalCnssPat > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['664']->id,
                    'label'             => "CNSS patronal — {$run->period_label}",
                    'debit'             => $totalCnssPat,
                    'credit'            => 0,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
            // [FIX-COMPTA] Indemnités non imposables (transport, logement, repas…)
            // incluses dans le net à payer (C422) mais doivent être débitées en charges (D671)
            if ($totalNonTaxable > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['671']->id,
                    'label'             => "Indemnités non imposables — {$run->period_label}",
                    'debit'             => $totalNonTaxable,
                    'credit'            => 0,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            // Crédits
            if ($totalNet > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['422']->id,
                    'label'             => "Nets à payer — {$run->period_label}",
                    'debit'             => 0,
                    'credit'            => $totalNet,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
            $totalCnssAll = $totalCnssEmp + $totalCnssPat;
            if ($totalCnssAll > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['451']->id,
                    'label'             => "CNSS (salarié + patronal) — {$run->period_label}",
                    'debit'             => 0,
                    'credit'            => $totalCnssAll,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
            // [FIX-COMPTA] IUTS + effort de paix salarié → C 447 (État, impôts retenus)
            $totalEtatRetenu = $totalIuts + $totalEffortPaix;
            if ($totalEtatRetenu > 0) {
                $lines[] = [
                    'journal_entry_id'  => $entry->id,
                    'account_id'        => $accounts['447']->id,
                    'label'             => "IUTS + Effort de paix — {$run->period_label}",
                    'debit'             => 0,
                    'credit'            => $totalEtatRetenu,
                    'sort_order'        => $sort++,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            // Ajustement strict ±1 FCFA pour arrondis uniquement
            $diff = $debitTotal - $creditTotal;
            if ($diff !== 0 && abs($diff) <= 1 && count($lines) > 0) {
                foreach ($lines as &$line) {
                    if ($line['account_id'] === $accounts['422']->id && $line['credit'] > 0) {
                        $line['credit'] += $diff;
                        $entry->update(['total_credit' => $debitTotal]);
                        break;
                    }
                }
                unset($line);
            }

            \Illuminate\Support\Facades\DB::table('journal_entry_lines')->insert($lines);

            // ── 8. Lier l'écriture au run ─────────────────────────────────────
            $run->update(['journal_entry_id' => $entry->id]);

            return $entry->fresh('lines.account');
        });
    }

    // ─── Privé ───────────────────────────────────────────────────────────────

    /**
     * Résout (ou crée) les 5 comptes nécessaires pour la paie.
     * Retourne un tableau [code => Account].
     */
    private function resolveAccounts(int $companyId): array
    {
        $codes    = array_keys(self::ACCOUNT_DEFAULTS);
        $existing = Account::where('company_id', $companyId)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        // Pré-charger les classes de comptes pour cette société (keyed par numéro de classe)
        $accountClasses = DB::table('account_classes')
            ->where('company_id', $companyId)
            ->pluck('id', 'number');  // ['4' => 9, '6' => 15, ...]

        $result = [];
        foreach ($codes as $code) {
            if ($existing->has($code)) {
                $result[$code] = $existing[$code];
                continue;
            }

            // Auto-créer le compte manquant
            $defaults  = self::ACCOUNT_DEFAULTS[$code];
            $classNum  = (int) substr((string) $code, 0, 1); // premier chiffre = numéro de classe
            $classId   = $accountClasses->get($classNum);

            if (! $classId) {
                throw new \RuntimeException(
                    "Classe de comptes #{$classNum} introuvable pour la société #{$companyId}. "
                    . "Créez d'abord la classe dans le plan comptable."
                );
            }

            $result[$code] = Account::create([
                'company_id'       => $companyId,
                'account_class_id' => $classId,
                'code'             => $code,
                'name'             => $defaults['name'],
                'type'             => $defaults['type'],
                'is_detail'        => true,
                'is_active'        => true,
                'debit_balance'    => 0,
                'credit_balance'   => 0,
            ]);

            Log::info("[PayrollAccounting] Compte {$code} créé automatiquement pour company #{$companyId}.");
        }

        return $result;
    }

    /**
     * Numéro d'écriture séquentiel : OD-YYYY-NNN.
     *
     * withTrashed : une écriture soft-supprimée occupe toujours son numéro
     * dans l'index unique — un numéro n'est JAMAIS réutilisé (sinon
     * « Duplicate entry » à l'insert, et une piste d'audit ambiguë).
     */
    private function nextNumber(int $companyId, int $journalTypeId): string
    {
        $year  = now()->year;
        $prefix = "OD-{$year}-";
        $last  = JournalEntry::withTrashed()
            ->where('company_id', $companyId)
            ->where('journal_type_id', $journalTypeId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
