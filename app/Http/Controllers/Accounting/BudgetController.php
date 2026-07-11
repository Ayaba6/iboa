<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingBudget;
use App\Models\AccountingBudgetLine;
use App\Models\FiscalYear;
use App\Models\JournalEntryLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * [Maquette X3] Budgets comptables par compte général.
 * Réalisé calculé depuis les écritures validées (classe 6 : D−C ; classe 7 : C−D).
 */
class BudgetController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.view')->only('index');
        $this->middleware('permission:accounting.manage')->except('index');
    }

    public function index(Request $request): View
    {
        $company     = currentCompany();
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $budgets     = AccountingBudget::orderByDesc('created_at')->get();

        $budget = $request->input('budget_id')
            ? AccountingBudget::with('lines.account', 'fiscalYear')->find($request->input('budget_id'))
            : AccountingBudget::with('lines.account', 'fiscalYear')->latest()->first();

        $lines = collect();
        if ($budget) {
            $fy = $budget->fiscalYear;
            // Réalisé par compte sur la période du budget (écritures validées)
            $realises = JournalEntryLine::selectRaw('account_id, SUM(debit) as sd, SUM(credit) as sc')
                ->whereIn('account_id', $budget->lines->pluck('account_id'))
                ->whereHas('journalEntry', function ($q) use ($fy, $budget) {
                    $q->where('status', 'valide');
                    if ($fy) {
                        $year = $fy->starts_at->year;
                        $q->whereBetween('entry_date', [
                            sprintf('%d-%02d-01', $year, $budget->period_from),
                            \Carbon\Carbon::create($year, $budget->period_to, 1)->endOfMonth()->toDateString(),
                        ]);
                    }
                })
                ->groupBy('account_id')
                ->get()
                ->keyBy('account_id');

            $lines = $budget->lines->map(function ($l) use ($realises) {
                $r = $realises[$l->account_id] ?? null;
                $isProduit = str_starts_with($l->account?->code ?? '', '7');
                $l->realise = $r
                    ? (int) ($isProduit ? $r->sc - $r->sd : $r->sd - $r->sc)
                    : 0;
                $l->disponible = $l->revised_amount - $l->realise - $l->committed_amount;
                $l->ecart      = ($l->realise + $l->committed_amount) - $l->revised_amount;
                return $l;
            })->sortBy(fn ($l) => $l->account?->code)->values();
        }

        // Comptes budgétables : classes 6 et 7 (charges / produits)
        $accounts = Account::postable()
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->where('code', 'like', '6%')->orWhere('code', 'like', '7%'))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('comptabilite.budgets.index', compact(
            'company', 'fiscalYears', 'budgets', 'budget', 'lines', 'accounts'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'           => ['required', 'string', 'max:30'],
            'label'          => ['required', 'string', 'max:120'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'version'        => ['nullable', 'string', 'max:10'],
            'period_from'    => ['required', 'integer', 'min:1', 'max:12'],
            'period_to'      => ['required', 'integer', 'min:1', 'max:12', 'gte:period_from'],
        ]);

        $data['company_id'] = currentCompany()->id;
        $data['created_by'] = Auth::id();
        $data['version']    = $data['version'] ?: 'V1';

        $budget = AccountingBudget::create($data);

        return redirect()
            ->route('comptabilite.budgets.index', ['budget_id' => $budget->id])
            ->with('success', "Budget {$budget->code} ({$budget->version}) créé — ajoutez les lignes budgétaires.");
    }

    public function storeLine(Request $request, AccountingBudget $budget): RedirectResponse
    {
        abort_unless($budget->status === 'en_cours', 403, 'Budget validé — lignes verrouillées.');

        $data = $request->validate([
            'account_id'       => ['required', 'integer', 'exists:accounts,id'],
            'cost_center'      => ['nullable', 'string', 'max:30'],
            'axe'              => ['nullable', 'string', 'max:30'],
            'initial_amount'   => ['required', 'integer', 'min:0'],
            'revised_amount'   => ['nullable', 'integer', 'min:0'],
            'committed_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['revised_amount'] = $data['revised_amount'] ?? $data['initial_amount'];

        $budget->lines()->updateOrCreate(
            ['account_id' => $data['account_id'], 'cost_center' => $data['cost_center'] ?? null],
            $data
        );

        return redirect()
            ->route('comptabilite.budgets.index', ['budget_id' => $budget->id])
            ->with('success', 'Ligne budgétaire enregistrée.');
    }

    public function destroyLine(AccountingBudget $budget, AccountingBudgetLine $line): RedirectResponse
    {
        abort_unless($budget->status === 'en_cours', 403, 'Budget validé — lignes verrouillées.');
        abort_unless($line->accounting_budget_id === $budget->id, 404);
        $line->delete();

        return redirect()
            ->route('comptabilite.budgets.index', ['budget_id' => $budget->id])
            ->with('success', 'Ligne supprimée.');
    }

    public function validateBudget(AccountingBudget $budget): RedirectResponse
    {
        abort_unless($budget->status === 'en_cours', 403, 'Seul un budget en cours peut être validé.');
        abort_if($budget->lines()->count() === 0, 403, 'Aucune ligne budgétaire — validation impossible.');

        $budget->update(['status' => 'valide']);

        return redirect()
            ->route('comptabilite.budgets.index', ['budget_id' => $budget->id])
            ->with('success', "Budget {$budget->code} validé — les montants sont figés.");
    }
}
