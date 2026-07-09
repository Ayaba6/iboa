<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Repositories\CashAccountRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public function __construct(protected CashAccountRepository $repository)
    {
        $this->middleware('can:cash_accounts.view')->only(['index', 'show']);
        $this->middleware('can:cash_accounts.manage')->except(['index', 'show']);
    }

    public function index(): View
    {
        $accounts = CashAccount::with('paymentMethod')
            ->where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get();

        return view('tresorerie.caisses.index', compact('accounts'));
    }

    public function create(): View
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
        return view('tresorerie.caisses.create', compact('paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:cash_accounts,code'],
            'type'              => ['required', 'in:caisse,banque,mobile_money'],
            'bank_name'         => ['nullable', 'string', 'max:150'],
            'bank_branch'       => ['nullable', 'string', 'max:150'],
            'account_number'    => ['nullable', 'string', 'max:50'],
            'iban'              => ['nullable', 'string', 'max:34'],
            'swift_bic'         => ['nullable', 'string', 'max:11'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'     => ['required', 'string', 'size:3'],
            'opening_balance'   => ['required', 'integer'],
            'min_balance'       => ['nullable', 'integer', 'min:0'],
            'is_default'        => ['boolean'],
            // [PARITÉ SAGE X3] Champs descriptifs
            'account_group'        => ['nullable', 'string', 'max:60'],
            'category'             => ['nullable', 'string', 'max:40'],
            'general_account'      => ['nullable', 'string', 'max:20'],
            'site'                 => ['nullable', 'string', 'max:40'],
            'manager_name'         => ['nullable', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:500'],
            'country_code'         => ['nullable', 'string', 'size:2'],
            'bank_code'            => ['nullable', 'string', 'max:20'],
            'branch_code'          => ['nullable', 'string', 'max:20'],
            'rib_key'              => ['nullable', 'string', 'max:4'],
            'overdraft_limit'      => ['nullable', 'integer', 'min:0'],
            'overdraft_currency'   => ['nullable', 'string', 'size:3'],
            'transaction_ceiling'  => ['nullable', 'integer', 'min:0'],
            'operation_ceiling'    => ['nullable', 'integer', 'min:0'],
            'entry_generation'     => ['nullable', 'in:automatique,manuelle'],
            'include_in_forecast'  => ['boolean'],
            'is_regularization'    => ['boolean'],
            'opened_at'            => ['nullable', 'date'],
            'closes_at'            => ['nullable', 'date', 'after_or_equal:opened_at'],
            'statement_format'     => ['nullable', 'string', 'max:20'],
            'statement_frequency'  => ['nullable', 'string', 'max:20'],
            'last_statement_at'    => ['nullable', 'date'],
            'forecast_horizon_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'forecast_currency'    => ['nullable', 'string', 'size:3'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $company = currentCompany();
        $data['company_id']      = $company->id;
        $data['current_balance'] = (int) $data['opening_balance'];
        $data['is_default']          = $request->boolean('is_default');
        $data['is_active']           = true;
        $data['include_in_forecast'] = $request->boolean('include_in_forecast', true);
        $data['is_regularization']   = $request->boolean('is_regularization');

        $account = CashAccount::create($data);

        // [SAGE X3] « Enregistrer et créer » : rebascule sur un formulaire vierge.
        if ($request->boolean('save_and_new')) {
            return redirect()
                ->route('tresorerie.caisses.create')
                ->with('success', 'Compte ' . $account->name . ' créé. Nouvelle saisie.');
        }

        return redirect()
            ->route('tresorerie.caisses.show', $account)
            ->with('success', 'Compte ' . $account->name . ' créé.');
    }

    public function show(CashAccount $caisse): View
    {
        $account      = $this->repository->findWithTransactions($caisse->id, 20);
        $transactions = $account->getRelation('transactions');

        return view('tresorerie.caisses.show', compact('account', 'transactions'));
    }

    public function edit(CashAccount $caisse): View
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
        return view('tresorerie.caisses.edit', compact('caisse', 'paymentMethods'));
    }

    public function update(Request $request, CashAccount $caisse): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:cash_accounts,code,' . $caisse->id],
            'type'              => ['required', 'in:caisse,banque,mobile_money'],
            'bank_name'         => ['nullable', 'string', 'max:150'],
            'bank_branch'       => ['nullable', 'string', 'max:150'],
            'account_number'    => ['nullable', 'string', 'max:50'],
            'iban'              => ['nullable', 'string', 'max:34'],
            'swift_bic'         => ['nullable', 'string', 'max:11'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'     => ['required', 'string', 'size:3'],
            'min_balance'       => ['nullable', 'integer', 'min:0'],
            'is_default'        => ['boolean'],
            'is_active'         => ['boolean'],
            // [PARITÉ SAGE X3] Champs descriptifs
            'account_group'        => ['nullable', 'string', 'max:60'],
            'category'             => ['nullable', 'string', 'max:40'],
            'general_account'      => ['nullable', 'string', 'max:20'],
            'site'                 => ['nullable', 'string', 'max:40'],
            'manager_name'         => ['nullable', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:500'],
            'country_code'         => ['nullable', 'string', 'size:2'],
            'bank_code'            => ['nullable', 'string', 'max:20'],
            'branch_code'          => ['nullable', 'string', 'max:20'],
            'rib_key'              => ['nullable', 'string', 'max:4'],
            'overdraft_limit'      => ['nullable', 'integer', 'min:0'],
            'overdraft_currency'   => ['nullable', 'string', 'size:3'],
            'transaction_ceiling'  => ['nullable', 'integer', 'min:0'],
            'operation_ceiling'    => ['nullable', 'integer', 'min:0'],
            'entry_generation'     => ['nullable', 'in:automatique,manuelle'],
            'include_in_forecast'  => ['boolean'],
            'is_regularization'    => ['boolean'],
            'opened_at'            => ['nullable', 'date'],
            'closes_at'            => ['nullable', 'date', 'after_or_equal:opened_at'],
            'statement_format'     => ['nullable', 'string', 'max:20'],
            'statement_frequency'  => ['nullable', 'string', 'max:20'],
            'last_statement_at'    => ['nullable', 'date'],
            'forecast_horizon_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'forecast_currency'    => ['nullable', 'string', 'size:3'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_default']          = $request->boolean('is_default');
        $data['is_active']           = $request->boolean('is_active');
        $data['include_in_forecast'] = $request->boolean('include_in_forecast', true);
        $data['is_regularization']   = $request->boolean('is_regularization');

        $caisse->update($data);

        return redirect()
            ->route('tresorerie.caisses.show', $caisse)
            ->with('success', 'Compte mis à jour.');
    }
}
