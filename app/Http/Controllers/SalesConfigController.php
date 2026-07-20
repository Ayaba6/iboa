<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\ProductFamily;
use App\Models\SalesDiscount;
use App\Models\SalesSetting;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// [Paramétrage Vente X3] Hub + paramètres généraux + remises + modes de règlement.
class SalesConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage');
    }

    /** Hub Paramétrage Vente — regroupe tous les écrans de configuration. */
    public function hub(): View
    {
        $counts = [
            'discounts' => SalesDiscount::where('is_active', true)->count(),
            'methods'   => PaymentMethod::where('is_active', true)->count(),
        ];

        return view('settings.sales.hub', compact('counts'));
    }

    // ── Paramètres généraux vente ────────────────────────────────────────────

    public function settings(): View
    {
        return view('settings.sales.settings', [
            'settings'   => SalesSetting::current(),
            'warehouses' => Warehouse::active()->where('can_sale', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reserve_stock_on_quote'        => ['boolean'],
            'allow_direct_invoicing'        => ['boolean'],
            'enforce_price_floor'           => ['boolean'],
            'block_sales_on_overdue'        => ['boolean'],
            'require_order_for_delivery'    => ['boolean'],
            'discount_validation_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_margin_min'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_required_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quote_validity_days'           => ['required', 'integer', 'min:1', 'max:365'],
            'default_sales_warehouse_id'    => ['nullable', 'exists:warehouses,id'],
            'quote_footer_note'             => ['nullable', 'string', 'max:1000'],
            'invoice_footer_note'           => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['reserve_stock_on_quote', 'allow_direct_invoicing', 'enforce_price_floor', 'block_sales_on_overdue', 'require_order_for_delivery'] as $b) {
            $data[$b] = $request->boolean($b);
        }

        SalesSetting::current()->update($data);

        return back()->with('success', 'Paramètres généraux vente enregistrés.');
    }

    // ── Remises commerciales ─────────────────────────────────────────────────

    public function discounts(): View
    {
        return view('settings.sales.discounts', [
            'discounts' => SalesDiscount::with(['client:id,name', 'product:id,name', 'productFamily:id,name'])
                ->orderByDesc('is_active')->orderBy('name')->paginate(30),
            'clients'   => \App\Models\Client::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products'  => \App\Models\Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'families'  => ProductFamily::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        SalesDiscount::create($this->discountData($request) + [
            'company_id' => currentCompany()->id,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Remise commerciale créée.');
    }

    public function updateDiscount(Request $request, SalesDiscount $discount): RedirectResponse
    {
        $discount->update($this->discountData($request));

        return back()->with('success', 'Remise mise à jour.');
    }

    public function destroyDiscount(SalesDiscount $discount): RedirectResponse
    {
        $discount->delete();

        return back()->with('success', 'Remise supprimée.');
    }

    private function discountData(Request $request): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'discount_type'       => ['required', 'in:client,groupe_client,categorie_client,article,famille_article,volume,promotionnelle,exceptionnelle'],
            'client_id'           => ['nullable', 'exists:clients,id'],
            'client_group'        => ['nullable', 'string', 'max:60'],
            'client_category'     => ['nullable', 'string', 'max:60'],
            'product_id'          => ['nullable', 'exists:products,id'],
            'product_family_id'   => ['nullable', 'exists:product_families,id'],
            'rate_percent'        => ['required', 'numeric', 'min:0', 'max:100'],
            'min_quantity'        => ['nullable', 'numeric', 'min:0'],
            'cap_amount'          => ['nullable', 'numeric', 'min:0'],
            'starts_at'           => ['nullable', 'date'],
            'ends_at'             => ['nullable', 'date', 'after_or_equal:starts_at'],
            'requires_validation' => ['boolean'],
            'is_active'           => ['boolean'],
        ]);
        $data['requires_validation'] = $request->boolean('requires_validation');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    // ── Modes de règlement ───────────────────────────────────────────────────

    public function methods(): View
    {
        return view('settings.sales.payment-methods', [
            'methods'      => PaymentMethod::orderBy('sort_order')->orderBy('name')->get(),
            'cashAccounts' => CashAccount::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        PaymentMethod::create($this->methodData($request));

        return back()->with('success', 'Mode de règlement créé.');
    }

    public function updateMethod(Request $request, PaymentMethod $method): RedirectResponse
    {
        $method->update($this->methodData($request));

        return back()->with('success', 'Mode de règlement mis à jour.');
    }

    private function methodData(Request $request): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:60'],
            'code'                => ['required', 'string', 'max:20'],
            'type'                => ['nullable', 'string', 'max:30'],
            'cash_account_id'     => ['nullable', 'exists:cash_accounts,id'],
            'journal_code'        => ['nullable', 'string', 'max:10'],
            'requires_reference'  => ['boolean'],
            'attachment_required' => ['boolean'],
            'is_active'           => ['boolean'],
        ]);
        foreach (['requires_reference', 'attachment_required'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
