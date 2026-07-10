<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use App\Models\JournalType;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// [Maquette X3] Paramètres comptables — écran de configuration centralisé.
class AccountingSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.manage');
    }

    public function edit(): View
    {
        $company  = currentCompany();
        $settings = AccountingSetting::current();

        return view('comptabilite.parametres.edit', [
            'settings'     => $settings,
            'company'      => $company,
            'accounts'     => Account::where('company_id', $company->id)->where('is_detail', true)
                                ->orderBy('code')->get(['id', 'code', 'name']),
            'fiscalYears'  => FiscalYear::orderByDesc('starts_at')->get(['id', 'label']),
            'costCenters'  => CostCenter::orderBy('code')->get(['id', 'code', 'name']),
            'journalTypes' => JournalType::where('company_id', $company->id)->orderBy('code')->get(),
            'taxRates'     => TaxRate::orderBy('rate')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'            => ['nullable', 'string', 'max:30'],
            'referentiel'     => ['required', 'string', 'max:30'],
            'regime_fiscal'   => ['nullable', 'string', 'max:40'],
            'plan_comptable'  => ['nullable', 'string', 'max:60'],
            'base_currency'   => ['nullable', 'string', 'max:10'],
            'fiscal_year_id'  => ['nullable', 'exists:fiscal_years,id'],
            'effective_date'  => ['nullable', 'date'],
            'status'          => ['nullable', 'in:brouillon,actif,archive'],
            'comment'         => ['nullable', 'string', 'max:1000'],
            'account_client_collectif'      => ['nullable', 'exists:accounts,id'],
            'account_fournisseur_collectif' => ['nullable', 'exists:accounts,id'],
            'account_ventes'                => ['nullable', 'exists:accounts,id'],
            'account_achats'                => ['nullable', 'exists:accounts,id'],
            'account_tva_collectee'         => ['nullable', 'exists:accounts,id'],
            'account_tva_deductible'        => ['nullable', 'exists:accounts,id'],
            'account_stock_mp'              => ['nullable', 'exists:accounts,id'],
            'account_stock_pf'              => ['nullable', 'exists:accounts,id'],
            'account_variation_stock'       => ['nullable', 'exists:accounts,id'],
            'account_caisse'                => ['nullable', 'exists:accounts,id'],
            'account_banque'                => ['nullable', 'exists:accounts,id'],
            'centre_cout_defaut_id'         => ['nullable', 'exists:cost_centers,id'],
            'axe_analytique_1'              => ['nullable', 'string', 'max:40'],
            'axe_analytique_2'              => ['nullable', 'string', 'max:40'],
            'axe_analytique_3'              => ['nullable', 'string', 'max:40'],
        ]);

        foreach ([
            'auto_ecriture_vente', 'auto_ecriture_achat', 'auto_comptabilisation_stock',
            'validation_obligatoire', 'interdire_periode_cloturee', 'lettrage_auto',
            'rapprochement_actif', 'analytique_obligatoire', 'section_analytique_obligatoire',
        ] as $b) {
            $data[$b] = $request->boolean($b);
        }

        $settings = AccountingSetting::current();
        $data['created_by'] = $settings->created_by ?? Auth::id();
        $settings->update($data);

        return back()->with('success', 'Paramètres comptables enregistrés.');
    }
}
