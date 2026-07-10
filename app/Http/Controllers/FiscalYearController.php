<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function __construct()
    {
        // Fiscal year management is strictly admin-only.
        $this->middleware('can:settings.manage');
    }

    public function index(): View
    {
        $years = FiscalYear::orderByDesc('starts_at')->get();
        return view('settings.fiscal-years', compact('years'));
    }

    // [Maquette Exercices fiscaux] fiche de création complète
    public function create(): View
    {
        return view('settings.fiscal-years-form', $this->formData(null));
    }

    // [Maquette Exercices fiscaux] fiche de modification avec périodes calculées
    public function edit(FiscalYear $fiscalYear): View
    {
        return view('settings.fiscal-years-form', $this->formData($fiscalYear));
    }

    private function formData(?FiscalYear $fiscalYear): array
    {
        return [
            'fiscalYear'    => $fiscalYear,
            'users'         => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'currencies'    => \App\Models\Currency::orderBy('code')->get(['id', 'code', 'name']),
            'previousYears' => FiscalYear::when($fiscalYear, fn ($q) => $q->where('id', '!=', $fiscalYear->id))
                                   ->orderByDesc('starts_at')->get(['id', 'code', 'label']),
            'periods'       => $fiscalYear ? $this->buildPeriods($fiscalYear) : collect(),
        ];
    }

    /**
     * [Maquette] Périodes mensuelles dérivées de l'exercice, croisées avec les
     * verrouillages comptables (accounting_period_locks) et les déclarations TVA.
     */
    private function buildPeriods(FiscalYear $fy): \Illuminate\Support\Collection
    {
        $locks = \App\Models\AccountingPeriodLock::query()
            ->get(['year', 'month'])
            ->mapWithKeys(fn ($l) => [$l->year . '-' . str_pad((string) $l->month, 2, '0', STR_PAD_LEFT) => true]);
        $declared = DB::table('vat_declarations')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->get(['period_start', 'period_end']);

        $periods = collect();
        $cursor = $fy->starts_at->copy()->startOfMonth();
        $end = $fy->ends_at->copy()->endOfMonth();
        $n = 1;
        while ($cursor <= $end && $n <= 24) {
            $pStart = $cursor->copy()->max($fy->starts_at);
            $pEnd = $cursor->copy()->endOfMonth()->min($fy->ends_at);
            $periods->push((object) [
                'number'    => $n,
                'label'     => ucfirst($cursor->translatedFormat('F Y')),
                'starts_at' => $pStart,
                'ends_at'   => $pEnd,
                'is_locked' => $locks->has($cursor->format('Y-m')),
                'vat_done'  => $declared->contains(fn ($d) => $d->period_start <= $pEnd->toDateString() && $d->period_end >= $pStart->toDateString()),
            ]);
            $cursor->addMonth();
            $n++;
        }

        return $periods;
    }

    // [Maquette] champs additionnels communs création/modification
    private function maquetteData(Request $request): array
    {
        $data = $request->validate([
            'code'               => ['nullable', 'string', 'max:20'],
            'actual_close_date'  => ['nullable', 'date'],
            'periodicity'        => ['nullable', 'in:mensuelle,trimestrielle,annuelle'],
            'exercise_type'      => ['nullable', 'in:normal,premier,cloture'],
            'responsible_id'     => ['nullable', 'exists:users,id'],
            'fiscal_regime'      => ['nullable', 'string', 'max:50'],
            'base_currency'      => ['nullable', 'string', 'max:10'],
            'previous_reference' => ['nullable', 'string', 'max:30'],
            'next_reference'     => ['nullable', 'string', 'max:30'],
            'comment'            => ['nullable', 'string', 'max:500'],
            'internal_notes'     => ['nullable', 'string', 'max:500'],
            'allow_entries_after_provisional_close' => ['boolean'],
            'monthly_close_required'    => ['boolean'],
            'auto_centralization'       => ['boolean'],
            'analytics_active'          => ['boolean'],
            'vat_lock_after_validation' => ['boolean'],
            'tolerated_days'            => ['nullable', 'integer', 'min:0', 'max:60'],
            'last_monthly_close'        => ['nullable', 'date'],
        ]);
        foreach (['allow_entries_after_provisional_close', 'monthly_close_required', 'auto_centralization', 'analytics_active', 'vat_lock_after_validation'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:50'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after:starts_at'],
            'is_current' => ['boolean'],
        ]);
        $extra = $this->maquetteData($request);

        DB::transaction(function () use ($data, $extra, $request) {
            if ($request->boolean('is_current')) {
                FiscalYear::where('is_current', true)->update(['is_current' => false]);
            }
            $fy = FiscalYear::create($extra + [
                'label'      => $data['label'],
                'starts_at'  => $data['starts_at'],
                'ends_at'    => $data['ends_at'],
                'status'     => 'ouvert',
                'is_current' => $request->boolean('is_current'),
            ]);

            // [FIX-FY-SYNC] Si on marque ce FY comme courant, propage sur companies.current_fiscal_year_id.
            // Sinon, si AUCUN FY courant n'existe et que c'est le seul → on prend celui-ci par défaut
            // (évite que le système soit bloqué après une purge complète).
            if ($request->boolean('is_current') || FiscalYear::where('is_current', true)->doesntExist()) {
                if (!$fy->is_current) $fy->update(['is_current' => true]);
                \App\Models\Company::query()->update(['current_fiscal_year_id' => $fy->id]);
            }
        });

        return redirect()->route('settings.fiscal-years.index')->with('success', "Exercice « {$data['label']} » créé.");
    }

    public function update(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $data = $request->validate([
            'label'     => ['required', 'string', 'max:50'],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
        ]);
        $extra = $this->maquetteData($request);

        if ($fiscalYear->status !== 'ouvert') {
            return back()->with('error', 'Seul un exercice ouvert peut être modifié.');
        }

        $fiscalYear->update($extra + $data);
        return back()->with('success', "Exercice mis à jour.");
    }

    public function setCurrent(FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->status === 'archive') {
            return back()->with('error', 'Un exercice archivé ne peut pas être l\'exercice courant.');
        }

        DB::transaction(function () use ($fiscalYear) {
            FiscalYear::where('is_current', true)->update(['is_current' => false]);
            $fiscalYear->update(['is_current' => true]);

            // [FIX-FY-SYNC] Propage sur companies.current_fiscal_year_id pour que
            // les séquences documents et les services métier suivent ce changement.
            \App\Models\Company::query()->update(['current_fiscal_year_id' => $fiscalYear->id]);
        });

        return back()->with('success', "« {$fiscalYear->label} » défini comme exercice courant.");
    }

    public function close(FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->status !== 'ouvert') {
            return back()->with('error', 'Cet exercice n\'est pas ouvert.');
        }

        // [FIX-EXERCICE-01] Block closure if brouillon entries remain — the ledger must
        // be fully validated before the year can be closed.
        $brouillonCount = \App\Models\JournalEntry::where('fiscal_year_id', $fiscalYear->id)
            ->where('status', 'brouillon')
            ->count();

        if ($brouillonCount > 0) {
            return back()->with('error',
                "Impossible de clôturer : {$brouillonCount} écriture(s) en brouillon "
                . "doivent être validées ou supprimées avant la clôture."
            );
        }

        $fiscalYear->update(['status' => 'cloture']);
        return back()->with('success', "Exercice « {$fiscalYear->label} » clôturé. Pensez à générer le Report à nouveau depuis cette page.");
    }

    public function archive(FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->status !== 'cloture') {
            return back()->with('error', 'Seul un exercice clôturé peut être archivé.');
        }
        if ($fiscalYear->is_current) {
            return back()->with('error', 'Impossible d\'archiver l\'exercice courant.');
        }

        $fiscalYear->update(['status' => 'archive']);
        return back()->with('success', "Exercice « {$fiscalYear->label} » archivé.");
    }

    /**
     * Generate "Report à nouveau" journal entries for a closed fiscal year.
     *
     * Carries forward class 1-5 account balances as opening entries for the next fiscal year.
     * Classes 6 & 7 (P&L) of the closing year are aggregated and posted to account 13.
     *
     * [I-FIX-01] Balances computed per fiscal year from journal_entry_lines (validated only),
     *            instead of accounts.debit_balance which is a lifetime cumulative and would
     *            double-count prior years' Report à nouveau entries.
     * [I-FIX-02] Idempotent: refuses to regenerate if RAN-<label> already exists.
     * [I-FIX-03] Uses the dedicated "AN" (a_nouveau) journal type rather than OD.
     */
    public function reportANouveau(FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->status !== 'cloture') {
            return back()->with('error', 'Seul un exercice clôturé peut faire l\'objet d\'un report à nouveau.');
        }

        $nextYear = FiscalYear::where('starts_at', '>', $fiscalYear->ends_at)
            ->orderBy('starts_at')
            ->first();

        if (!$nextYear) {
            return back()->with('error', 'Aucun exercice suivant trouvé. Créez d\'abord le nouvel exercice.');
        }

        if ($nextYear->status !== 'ouvert') {
            return back()->with('error', "L'exercice suivant « {$nextYear->label} » doit être ouvert pour recevoir le report à nouveau.");
        }

        // [I-FIX-02] Idempotence — refuse to regenerate.
        $existing = \App\Models\JournalEntry::where('fiscal_year_id', $nextYear->id)
            ->where('reference', 'RAN-'.$fiscalYear->label)
            ->first();
        if ($existing) {
            return back()->with('info', "Le report à nouveau a déjà été généré pour l'exercice « {$fiscalYear->label} » : écriture {$existing->number}.");
        }

        return DB::transaction(function () use ($fiscalYear, $nextYear) {
            $company = \App\Models\currentCompany();

            // [I-FIX-01] Compute balances from validated journal entry lines for all
            // fiscal years up to and including the year being closed. This includes
            // prior RAN entries already posted (they live under their target year's id).
            $cumulativeYearIds = FiscalYear::where('ends_at', '<=', $fiscalYear->ends_at)
                ->pluck('id');

            // Classes 1-5 : carry forward (excluding 13 — handled separately below)
            $balanceSheet = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('accounts as a', 'a.id', '=', 'l.account_id')
                ->whereIn('e.fiscal_year_id', $cumulativeYearIds)
                ->where('e.status', '!=', 'brouillon')
                ->whereNull('e.deleted_at')
                ->where('a.company_id', $company->id)
                ->whereRaw("(a.code LIKE '1%' OR a.code LIKE '2%' OR a.code LIKE '3%' OR a.code LIKE '4%' OR a.code LIKE '5%')")
                ->where('a.code', 'not like', '13%')
                ->groupBy('l.account_id')
                ->selectRaw('l.account_id, SUM(l.debit) as total_debit, SUM(l.credit) as total_credit')
                ->get();

            // Classes 6 & 7 : P&L for the CLOSING year only → net result → account 13
            $pl = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('accounts as a', 'a.id', '=', 'l.account_id')
                ->where('e.fiscal_year_id', $fiscalYear->id)
                ->where('e.status', '!=', 'brouillon')
                ->whereNull('e.deleted_at')
                ->where('a.company_id', $company->id)
                ->whereRaw("(a.code LIKE '6%' OR a.code LIKE '7%')")
                ->selectRaw("
                    SUM(CASE WHEN a.code LIKE '6%' THEN (l.debit - l.credit) ELSE 0 END) as net_charges,
                    SUM(CASE WHEN a.code LIKE '7%' THEN (l.credit - l.debit) ELSE 0 END) as net_produits
                ")
                ->first();

            $resultatNet = (int) (($pl->net_produits ?? 0) - ($pl->net_charges ?? 0));

            $lines = [];
            $runningDebit  = 0;
            $runningCredit = 0;

            foreach ($balanceSheet as $row) {
                $net = (int) ($row->total_debit - $row->total_credit);
                if ($net === 0) continue;

                if ($net > 0) {
                    $lines[] = ['account_id' => $row->account_id, 'label' => 'Report à nouveau '.$fiscalYear->label, 'debit' => $net, 'credit' => 0];
                    $runningDebit += $net;
                } else {
                    $lines[] = ['account_id' => $row->account_id, 'label' => 'Report à nouveau '.$fiscalYear->label, 'debit' => 0, 'credit' => abs($net)];
                    $runningCredit += abs($net);
                }
            }

            // Account 13 — net P&L of closing year
            if ($resultatNet !== 0) {
                $account13 = \App\Models\Account::where('company_id', $company->id)
                    ->where('code', 'like', '13%')
                    ->where('is_detail', true)
                    ->first();

                if (!$account13) {
                    $class1Id = \App\Models\AccountClass::where('company_id', $company->id)
                        ->where('number', 1)->value('id');
                    $account13 = \App\Models\Account::create([
                        'company_id'       => $company->id,
                        'account_class_id' => $class1Id,
                        'code'             => '13',
                        'name'             => "Résultat de l'exercice (reporté)",
                        'type'             => 'passif',
                        'is_detail'        => true,
                        'is_active'        => true,
                        'debit_balance'    => 0,
                        'credit_balance'   => 0,
                    ]);
                }

                if ($resultatNet > 0) {
                    $lines[] = ['account_id' => $account13->id, 'label' => "Résultat exercice {$fiscalYear->label} (bénéfice)", 'debit' => 0, 'credit' => $resultatNet];
                    $runningCredit += $resultatNet;
                } else {
                    $lines[] = ['account_id' => $account13->id, 'label' => "Résultat exercice {$fiscalYear->label} (perte)", 'debit' => abs($resultatNet), 'credit' => 0];
                    $runningDebit += abs($resultatNet);
                }
            }

            if (empty($lines)) {
                return back()->with('info', 'Aucun solde à reporter (tous les comptes sont à zéro).');
            }

            // Sanity check : if unbalanced, abort cleanly with diagnostic.
            if ($runningDebit !== $runningCredit) {
                throw new \RuntimeException(sprintf(
                    "Report à nouveau déséquilibré (D=%s, C=%s, écart=%s). "
                    . "Vérifiez les écritures de l'exercice « %s » avant la clôture.",
                    number_format($runningDebit, 0, ',', ' '),
                    number_format($runningCredit, 0, ',', ' '),
                    number_format(abs($runningDebit - $runningCredit), 0, ',', ' '),
                    $fiscalYear->label
                ));
            }

            // [I-FIX-03] Use AN (à-nouveau) journal type
            $journalType = \App\Models\JournalType::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'AN'],
                ['name' => "Journal d'À-Nouveau", 'type' => 'a_nouveau', 'is_active' => true]
            );

            $entry = \App\Models\JournalEntry::create([
                'company_id'      => $company->id,
                'journal_type_id' => $journalType->id,
                'fiscal_year_id'  => $nextYear->id,
                'number'          => app(\App\Services\DocumentSequenceService::class)->nextNumber($company, 'ecriture_comptable'),
                'entry_date'      => $nextYear->starts_at,
                'value_date'      => $nextYear->starts_at,
                'reference'       => 'RAN-'.$fiscalYear->label,
                'description'     => 'Report à nouveau — Exercice '.$fiscalYear->label,
                'status'          => 'valide',
                'total_debit'     => $runningDebit,
                'total_credit'    => $runningCredit,
                'created_by'      => auth()->id(),
                'validated_by'    => auth()->id(),
                'validated_at'    => now(),
            ]);

            foreach ($lines as $i => $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'label'      => $line['label'],
                    'debit'      => $line['debit'],
                    'credit'     => $line['credit'],
                    'sort_order' => $i,
                ]);
                \App\Models\Account::where('id', $line['account_id'])
                    ->increment('debit_balance', $line['debit']);
                \App\Models\Account::where('id', $line['account_id'])
                    ->increment('credit_balance', $line['credit']);
            }

            $count = count($lines);
            return back()->with('success',
                "Report à nouveau généré : {$entry->number} — {$count} ligne(s), total "
                . number_format($runningDebit, 0, ',', ' ') . ' FCFA.'
            );
        });
    }
}
