<?php

namespace App\Http\Controllers\Accounting;

use App\Exports\Accounting\BalanceExport;
use App\Exports\Accounting\GrandLivreExport;
use App\Exports\Accounting\JournauxExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\JournalType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class LedgerController extends Controller
{
    // -------------------------------------------------------------------------
    // Grand livre
    // -------------------------------------------------------------------------

    public function grandLivre(Request $request): View
    {
        $company   = currentCompany();
        $accountId = $request->input('account_id');
        $classId   = $request->input('class_id');
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $search    = $request->input('search');

        // [Maquette X3] Filtres avancés
        $filters = [
            'journal_type_id' => $request->input('journal_type_id'),
            'partner'         => $request->input('partner'),
            'piece_from'      => $request->input('piece_from'),
            'piece_to'        => $request->input('piece_to'),
            // Par défaut : écritures validées uniquement (comportement historique)
            'validated_only'  => $request->input('validated_only', '1') === '1',
            'account_from'    => $request->input('account_from'),
            'account_to'      => $request->input('account_to'),
            'period_from'     => $request->input('period_from'),
            'period_to'       => $request->input('period_to'),
        ];

        // [Maquette X3] Période du/au (mois de l'exercice) → bornes de dates
        // si les dates comptables ne sont pas saisies explicitement.
        $fyYear = $company->currentFiscalYear?->starts_at?->year ?? (int) date('Y');
        if ($filters['period_from'] && ! $dateFrom) {
            $dateFrom = sprintf('%d-%02d-01', $fyYear, (int) $filters['period_from']);
        }
        if ($filters['period_to'] && ! $dateTo) {
            $dateTo = \Carbon\Carbon::create($fyYear, (int) $filters['period_to'], 1)->endOfMonth()->format('Y-m-d');
        }

        $classes = AccountClass::where('company_id', $company->id)
            ->orderBy('number')
            ->get();

        $journalTypes = JournalType::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        // Liste complète (datalist / sous-libellés) — le filtrage par plage se fait sur $rangeAccounts.
        $accounts = Account::postable()
            ->where('company_id', $company->id)
            ->when($classId, fn($q) => $q->where('account_class_id', $classId))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_class_id']);

        // Single account selected → classic single-account view
        $account        = $accountId ? Account::find($accountId) : null;
        $lines          = collect();
        $accountGroups  = collect();  // used in multi-account (class) mode
        $openingBalance = null;       // [Maquette X3] solde d'ouverture (mono-compte, si date_from)

        // [Maquette X3] Par défaut : UN seul compte affiché (le premier mouvementé),
        // l'utilisateur choisit ensuite sa plage via « Compte général du/au ».
        if (! $accountId && ! $filters['account_from'] && ! $filters['account_to']) {
            $defaultCode = Account::postable()
                ->where('company_id', $company->id)
                ->whereIn('id', JournalEntryLine::query()->select('account_id'))
                ->orderBy('code')
                ->value('code')
                ?? Account::postable()->where('company_id', $company->id)->orderBy('code')->value('code');

            if ($defaultCode) {
                $filters['account_from'] = $defaultCode;
                $filters['account_to']   = $defaultCode;
            }
        }

        // Comptes de la plage sélectionnée (pour le chargement des lignes)
        $rangeAccounts = $accounts
            ->when($filters['account_from'], fn($c, $v) => $c->where('code', '>=', $v))
            ->when($filters['account_to'],   fn($c, $v) => $c->where('code', '<=', $v))
            ->values();

        // Mode plat permanent : liste unique triée par date, colonnes Compte/Intitulé.
        $flatMode = ! $accountId;

        if ($flatMode) {
            $accountIds = $rangeAccounts->pluck('id')->toArray();
            $lines = $this->loadLinesForAccounts($accountIds, $dateFrom, $dateTo, $search, $filters);

            if ($dateFrom && ! empty($accountIds)) {
                $openingBalance = (int) JournalEntryLine::whereIn('account_id', $accountIds)
                    ->whereHas('journalEntry', fn($q) => $q
                        ->where('status', 'valide')
                        ->whereDate('entry_date', '<', $dateFrom))
                    ->selectRaw('COALESCE(SUM(debit - credit), 0) as bal')
                    ->value('bal');
            }
        } elseif ($accountId) {
            $lines = $this->loadLines($accountId, $dateFrom, $dateTo, $search, $filters);

            if ($dateFrom) {
                $openingBalance = (int) JournalEntryLine::where('account_id', $accountId)
                    ->whereHas('journalEntry', fn($q) => $q
                        ->where('status', 'valide')
                        ->whereDate('entry_date', '<', $dateFrom))
                    ->selectRaw('COALESCE(SUM(debit - credit), 0) as bal')
                    ->value('bal');
            }
        } else {
            // Bulk-load all lines for the account set in one query, then group in PHP
            $allLines = $this->loadLinesForAccounts(
                $accounts->pluck('id')->toArray(),
                $dateFrom, $dateTo, $search, $filters
            );
            $grouped = $allLines->groupBy('account_id');
            $accountMap = $accounts->keyBy('id');

            foreach ($grouped as $accId => $accLines) {
                if (! isset($accountMap[$accId])) continue;
                $accountGroups->push([
                    'account'      => $accountMap[$accId],
                    'lines'        => $accLines,
                    'total_debit'  => $accLines->sum('debit'),
                    'total_credit' => $accLines->sum('credit'),
                ]);
            }

            // Sort groups by account code
            $accountGroups = $accountGroups->sortBy(fn($g) => $g['account']->code)->values();
        }

        return view('comptabilite.grand-livre', compact(
            'accounts', 'classes', 'journalTypes',
            'account', 'accountId',
            'classId', 'lines', 'accountGroups',
            'dateFrom', 'dateTo', 'search', 'filters', 'openingBalance', 'flatMode'
        ));
    }

    public function grandLivrePdf(Request $request): mixed
    {
        $company   = currentCompany();
        $accountId = $request->input('account_id');
        $classId   = $request->input('class_id');
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $search    = $request->input('search');

        // Determine which accounts to include
        $accounts = Account::postable()
            ->where('company_id', $company->id)
            ->when($classId,    fn($q) => $q->where('account_class_id', $classId))
            ->when($accountId,  fn($q) => $q->where('id', $accountId))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_class_id']);

        // Load all lines in one query, then group per account
        $allLines = $this->loadLinesForAccounts(
            $accounts->pluck('id')->toArray(),
            $dateFrom, $dateTo, $search
        );

        $grouped    = $allLines->groupBy('account_id');
        $accountMap = $accounts->keyBy('id');

        $accountGroups = collect();
        foreach ($grouped as $accId => $accLines) {
            if (! isset($accountMap[$accId])) continue;

            // Compute running balance for each line
            $running = 0;
            $linesWithBalance = $accLines->map(function ($line) use (&$running) {
                $running += $line->debit - $line->credit;
                $line->running_balance = $running;
                return $line;
            });

            $accountGroups->push([
                'account'      => $accountMap[$accId],
                'lines'        => $linesWithBalance,
                'total_debit'  => $accLines->sum('debit'),
                'total_credit' => $accLines->sum('credit'),
                'balance'      => $accLines->sum('debit') - $accLines->sum('credit'),
            ]);
        }

        $accountGroups = $accountGroups->sortBy(fn($g) => $g['account']->code)->values();

        $grandDebit   = $accountGroups->sum('total_debit');
        $grandCredit  = $accountGroups->sum('total_credit');
        $grandBalance = $grandDebit - $grandCredit;

        $title = $accountId && $accounts->first()
            ? 'Grand Livre — ' . $accounts->first()->code . ' ' . $accounts->first()->name
            : ($classId
                ? 'Grand Livre — Classe ' . optional(AccountClass::find($classId))->number
                : 'Grand Livre général');

        $pdf = Pdf::loadView('comptabilite.pdf.grand-livre', compact(
            'company', 'accountGroups',
            'grandDebit', 'grandCredit', 'grandBalance',
            'dateFrom', 'dateTo', 'title'
        ))->setPaper('a4', 'landscape');

        $filename = 'grand_livre_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function grandLivreExport(Request $request): mixed
    {
        $company   = currentCompany();
        $accountId = $request->input('account_id');
        $classId   = $request->input('class_id');
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $search    = $request->input('search');

        // Determine which accounts to export
        $accountIds = collect();
        if ($accountId) {
            $accountIds = collect([$accountId]);
        } elseif ($classId) {
            $accountIds = Account::postable()
                ->where('company_id', $company->id)
                ->where('account_class_id', $classId)
                ->pluck('id');
        } else {
            // Export all postable accounts that have movements
            $accountIds = Account::postable()
                ->where('company_id', $company->id)
                ->pluck('id');
        }

        $filename = 'grand_livre_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new GrandLivreExport($accountIds->toArray(), $dateFrom, $dateTo, $search),
            $filename
        );
    }

    // -------------------------------------------------------------------------
    // Balance générale
    // -------------------------------------------------------------------------

    public function balance(Request $request): View
    {
        $company  = currentCompany();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $classId  = $request->input('class_id');

        $accounts = Account::with('accountClass')
            ->where('company_id', $company->id)
            ->where('is_detail', true)
            ->when($classId, fn($q) => $q->where('account_class_id', $classId))
            ->orderBy('code')
            ->get();

        $accountIds = $accounts->pluck('id');

        // Opening balances = all validated movements strictly before date_from
        $openings = [];
        if ($dateFrom) {
            $openings = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as open_debit, SUM(credit) as open_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')
                                                        ->whereDate('entry_date', '<', $dateFrom))
                ->groupBy('account_id')
                ->get()->keyBy('account_id')
                ->toArray();
        }

        // Period movements
        if ($dateFrom || $dateTo) {
            $movements = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'valide');
                    if ($dateFrom) $q->whereDate('entry_date', '>=', $dateFrom);
                    if ($dateTo)   $q->whereDate('entry_date', '<=', $dateTo);
                })
                ->groupBy('account_id')
                ->get()->keyBy('account_id')
                ->toArray();

            $accounts = $accounts->map(function ($account) use ($movements, $openings) {
                $account->open_debit    = (int) ($openings[$account->id]['open_debit']    ?? 0);
                $account->open_credit   = (int) ($openings[$account->id]['open_credit']   ?? 0);
                $account->period_debit  = (int) ($movements[$account->id]['total_debit']  ?? 0);
                $account->period_credit = (int) ($movements[$account->id]['total_credit'] ?? 0);
                return $account;
            });
        } else {
            $accounts = $accounts->map(function ($account) {
                $account->open_debit    = 0;
                $account->open_credit   = 0;
                $account->period_debit  = $account->debit_balance;
                $account->period_credit = $account->credit_balance;
                return $account;
            });
        }

        $accounts = $accounts->filter(
            fn($a) => $a->open_debit > 0 || $a->open_credit > 0
                   || $a->period_debit > 0 || $a->period_credit > 0
        );

        // Solde final = ouverture + période
        $accounts = $accounts->map(function ($account) {
            $finalDebit  = $account->open_debit  + $account->period_debit;
            $finalCredit = $account->open_credit + $account->period_credit;
            $balance     = $finalDebit - $finalCredit;
            $account->solde_debiteur  = $balance > 0 ? $balance      : 0;
            $account->solde_crediteur = $balance < 0 ? abs($balance) : 0;
            return $account;
        });

        $totals = [
            'open_debit'       => $accounts->sum('open_debit'),
            'open_credit'      => $accounts->sum('open_credit'),
            'period_debit'     => $accounts->sum('period_debit'),
            'period_credit'    => $accounts->sum('period_credit'),
            'solde_debiteur'   => $accounts->sum('solde_debiteur'),
            'solde_crediteur'  => $accounts->sum('solde_crediteur'),
        ];

        $classes = AccountClass::where('company_id', $company->id)->orderBy('number')->get();

        return view('comptabilite.balance', compact('accounts', 'totals', 'dateFrom', 'dateTo', 'classId', 'classes'));
    }

    public function balanceExport(Request $request): mixed
    {
        $company  = currentCompany();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $classId  = $request->input('class_id');

        $filename = 'balance_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new BalanceExport($company->id, $dateFrom, $dateTo, $classId),
            $filename
        );
    }

    public function balancePdf(Request $request): mixed
    {
        $company  = currentCompany();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $classId  = $request->input('class_id');

        $accounts = Account::with(['accountClass'])
            ->where('company_id', $company->id)
            ->where('is_detail', true)
            ->when($classId, fn($q) => $q->where('account_class_id', $classId))
            ->orderBy('code')
            ->get();

        $accountIds = $accounts->pluck('id');

        $openings = [];
        if ($dateFrom) {
            $openings = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as open_debit, SUM(credit) as open_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')
                                                        ->whereDate('entry_date', '<', $dateFrom))
                ->groupBy('account_id')
                ->get()->keyBy('account_id')->toArray();
        }

        if ($dateFrom || $dateTo) {
            $movements = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'valide');
                    if ($dateFrom) $q->whereDate('entry_date', '>=', $dateFrom);
                    if ($dateTo)   $q->whereDate('entry_date', '<=', $dateTo);
                })
                ->groupBy('account_id')
                ->get()->keyBy('account_id')->toArray();

            $accounts = $accounts->map(function ($account) use ($movements, $openings) {
                $account->open_debit    = (int) ($openings[$account->id]['open_debit']    ?? 0);
                $account->open_credit   = (int) ($openings[$account->id]['open_credit']   ?? 0);
                $account->period_debit  = (int) ($movements[$account->id]['total_debit']  ?? 0);
                $account->period_credit = (int) ($movements[$account->id]['total_credit'] ?? 0);
                return $account;
            });
        } else {
            $accounts = $accounts->map(function ($account) {
                $account->open_debit    = 0; $account->open_credit  = 0;
                $account->period_debit  = $account->debit_balance;
                $account->period_credit = $account->credit_balance;
                return $account;
            });
        }

        $accounts = $accounts->filter(fn($a) =>
            $a->open_debit > 0 || $a->open_credit > 0 || $a->period_debit > 0 || $a->period_credit > 0
        )->map(function ($account) {
            $finalDebit  = $account->open_debit  + $account->period_debit;
            $finalCredit = $account->open_credit + $account->period_credit;
            $balance     = $finalDebit - $finalCredit;
            $account->solde_debiteur  = $balance > 0 ? $balance      : 0;
            $account->solde_crediteur = $balance < 0 ? abs($balance) : 0;
            return $account;
        });

        $totals = [
            'open_debit'      => $accounts->sum('open_debit'),
            'open_credit'     => $accounts->sum('open_credit'),
            'period_debit'    => $accounts->sum('period_debit'),
            'period_credit'   => $accounts->sum('period_credit'),
            'solde_debiteur'  => $accounts->sum('solde_debiteur'),
            'solde_crediteur' => $accounts->sum('solde_crediteur'),
        ];
        $hasPeriod  = (bool) ($dateFrom || $dateTo);
        $isBalanced = abs($totals['solde_debiteur'] - $totals['solde_crediteur']) < 1;

        $pdf = Pdf::loadView('comptabilite.pdf.balance', compact(
            'company', 'accounts', 'totals', 'dateFrom', 'dateTo', 'classId', 'hasPeriod', 'isBalanced'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('balance_' . now()->format('Ymd_His') . '.pdf');
    }

    // -------------------------------------------------------------------------
    // Export journaux
    // -------------------------------------------------------------------------

    public function journauxExport(Request $request): mixed
    {
        $filename = 'journaux_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new JournauxExport($request->only(['search', 'journal_type_id', 'status', 'date_from', 'date_to'])),
            $filename
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Brouillard comptable — draft entries pending validation
    // -------------------------------------------------------------------------

    public function brouillard(Request $request): View
    {
        $company      = currentCompany();
        $journalTypes = JournalType::orderBy('code')->get(['id', 'code', 'name']);
        $filters      = $request->only(['journal_type_id', 'date_from', 'date_to', 'search']);

        $entries = JournalEntry::with(['journalType', 'lines.account', 'createdBy'])
            ->where('company_id', $company->id)
            ->where('status', 'brouillon')
            ->when(!empty($filters['journal_type_id']), fn($q) => $q->where('journal_type_id', $filters['journal_type_id']))
            ->when(!empty($filters['date_from']),       fn($q) => $q->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),         fn($q) => $q->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),          fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhere('description', 'like', '%'.$filters['search'].'%')
                   ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->orderBy('entry_date')
            ->orderBy('number')
            ->paginate(30)
            ->withQueryString();

        return view('comptabilite.brouillard', compact('entries', 'filters', 'journalTypes'));
    }

    // -------------------------------------------------------------------------
    // Livre journal — chronological book of all validated entries
    // -------------------------------------------------------------------------

    public function livreJournal(Request $request): View
    {
        $company      = currentCompany();
        $journalTypes = JournalType::orderBy('code')->get(['id', 'code', 'name']);
        $filters      = $request->only(['journal_type_id', 'date_from', 'date_to', 'search']);

        $entries = JournalEntry::with(['journalType', 'lines.account'])
            ->where('company_id', $company->id)
            ->where('status', 'valide')
            ->when(!empty($filters['journal_type_id']), fn($q) => $q->where('journal_type_id', $filters['journal_type_id']))
            ->when(!empty($filters['date_from']),       fn($q) => $q->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),         fn($q) => $q->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),          fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhere('description', 'like', '%'.$filters['search'].'%')
                   ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->orderBy('entry_date')
            ->orderBy('number')
            ->paginate(20)
            ->withQueryString();

        $totalDebit  = $entries->sum('total_debit');
        $totalCredit = $entries->sum('total_credit');

        return view('comptabilite.livre-journal', compact('entries', 'filters', 'journalTypes', 'totalDebit', 'totalCredit'));
    }

    // -------------------------------------------------------------------------
    // Balance auxiliaire — sub-ledger balance by tiers account
    // -------------------------------------------------------------------------

    public function balanceAuxiliaire(Request $request): View
    {
        $company  = currentCompany();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $type     = $request->input('type', 'all'); // 'clients', 'fournisseurs', 'all'

        // Load tiers accounts (class 4 detail accounts)
        // Pas de filtre is_active : un tiers désactivé avec un solde impayé doit
        // apparaître dans la balance (relance, lettrage). Les comptes sans mouvement
        // sont écartés plus bas par le filtre debit/credit > 0.
        $accountsQuery = Account::where('company_id', $company->id)
            ->where('code', 'like', '4%')
            ->where('is_detail', true);

        if ($type === 'clients') {
            $accountsQuery->where('code', 'like', '41%');
        } elseif ($type === 'fournisseurs') {
            $accountsQuery->where('code', 'like', '40%');
        }

        $accounts   = $accountsQuery->orderBy('code')->get();
        $accountIds = $accounts->pluck('id');

        $movements = JournalEntryLine::query()
            ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('status', 'valide');
                if ($dateFrom) $q->whereDate('entry_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('entry_date', '<=', $dateTo);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = $accounts->map(function ($account) use ($movements) {
            $mv = $movements[$account->id] ?? null;
            $account->total_debit  = (int) ($mv->total_debit  ?? 0);
            $account->total_credit = (int) ($mv->total_credit ?? 0);
            $balance = $account->total_debit - $account->total_credit;
            $account->solde_debiteur  = $balance > 0 ? $balance      : 0;
            $account->solde_crediteur = $balance < 0 ? abs($balance) : 0;
            return $account;
        })->filter(fn($a) => $a->total_debit > 0 || $a->total_credit > 0);

        $totals = [
            'total_debit'      => $accounts->sum('total_debit'),
            'total_credit'     => $accounts->sum('total_credit'),
            'solde_debiteur'   => $accounts->sum('solde_debiteur'),
            'solde_crediteur'  => $accounts->sum('solde_crediteur'),
        ];

        return view('comptabilite.balance-auxiliaire', compact(
            'accounts', 'totals', 'dateFrom', 'dateTo', 'type'
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadLinesForAccounts(array $accountIds, ?string $dateFrom, ?string $dateTo, ?string $search, array $filters = [])
    {
        if (empty($accountIds)) {
            return collect();
        }

        return $this->linesQuery($dateFrom, $dateTo, $search, $filters)
            ->whereIn('account_id', $accountIds)
            ->get();
    }

    private function loadLines(int $accountId, ?string $dateFrom, ?string $dateTo, ?string $search, array $filters = [])
    {
        return $this->linesQuery($dateFrom, $dateTo, $search, $filters)
            ->where('account_id', $accountId)
            ->get();
    }

    /**
     * Requête de base des lignes du grand livre — factorise les deux modes.
     * [Maquette X3] filtres : journal, tiers, plage de pièces, validées uniquement.
     */
    private function linesQuery(?string $dateFrom, ?string $dateTo, ?string $search, array $filters = [])
    {
        $validatedOnly = $filters['validated_only'] ?? true;

        return JournalEntryLine::with(['journalEntry.journalType', 'account:id,code,name'])
            ->when($dateFrom, fn($q) => $q->whereHas('journalEntry', fn($je) => $je->whereDate('entry_date', '>=', $dateFrom)))
            ->when($dateTo,   fn($q) => $q->whereHas('journalEntry', fn($je) => $je->whereDate('entry_date', '<=', $dateTo)))
            ->when($search,   fn($q) => $q->where(fn($sq) =>
                $sq->where('label', 'like', '%'.$search.'%')
                   ->orWhereHas('journalEntry', fn($je) => $je->where('number', 'like', '%'.$search.'%')
                                                              ->orWhere('reference', 'like', '%'.$search.'%'))
            ))
            ->when($filters['journal_type_id'] ?? null, fn($q, $v) =>
                $q->whereHas('journalEntry', fn($je) => $je->where('journal_type_id', $v)))
            ->when($filters['partner'] ?? null, fn($q, $v) =>
                $q->where(fn($sq) => $sq->where('partner_name', 'like', '%'.$v.'%')
                    ->orWhereHas('journalEntry', fn($je) => $je->where('partner_name', 'like', '%'.$v.'%'))))
            ->when($filters['piece_from'] ?? null, fn($q, $v) =>
                $q->whereHas('journalEntry', fn($je) => $je->where('number', '>=', $v)))
            ->when($filters['piece_to'] ?? null, fn($q, $v) =>
                $q->whereHas('journalEntry', fn($je) => $je->where('number', '<=', $v)))
            ->when($validatedOnly,
                fn($q) => $q->whereHas('journalEntry', fn($je) => $je->where('status', 'valide')),
                fn($q) => $q->whereHas('journalEntry', fn($je) => $je->whereIn('status', ['valide', 'brouillon'])))
            ->orderBy(
                JournalEntry::select('entry_date')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
                    ->limit(1)
            );
    }
}
