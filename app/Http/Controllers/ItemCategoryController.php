<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Services\CategoryPropagationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * [X3] Catégories d'article — modèle de gestion (≠ familles).
 * Liste, fiche à onglets, duplication, désactivation (jamais de suppression
 * si utilisée), propagation contrôlée vers les articles (§8).
 */
class ItemCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:categories.view')->only(['index', 'show']);
        $this->middleware('permission:categories.create')->only(['create', 'store', 'duplicate']);
        $this->middleware('permission:categories.update')->only(['edit', 'update']);
        $this->middleware('permission:categories.disable')->only('disable');
        $this->middleware('permission:categories.propagate')->only(['propagatePreview', 'propagate']);
    }

    public function index(Request $request): View
    {
        $categories = ItemCategory::withCount('products')
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('code', 'like', "%$v%")->orWhere('name', 'like', "%$v%")))
            ->when($request->input('strategy'), fn ($q, $v) => $q->where('strategy', $v))
            ->when($request->filled('actif'), fn ($q) => $q->where('is_active', $request->boolean('actif')))
            ->when($request->input('flux') === 'achete', fn ($q) => $q->where('is_purchasable', true))
            ->when($request->input('flux') === 'vendu', fn ($q) => $q->where('is_sellable', true))
            ->when($request->input('flux') === 'fabrique', fn ($q) => $q->where('is_manufactured', true))
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return view('articles.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('articles.categories.form', ['category' => new ItemCategory(), 'formData' => $this->formData()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $category = ItemCategory::create($data);
        Log::info('[X3] Catégorie créée', ['code' => $category->code, 'user_id' => auth()->id()]);

        return redirect()->route('articles.categories.show', $category)->with('success', 'Catégorie ' . $category->code . ' créée.');
    }

    public function show(ItemCategory $category): View
    {
        $category->loadCount('products')->load(['sites.site', 'products' => fn ($q) => $q->orderBy('name')->limit(100)]);
        $propagatable = app(CategoryPropagationService::class)->propagatableFields($category);

        return view('articles.categories.show', compact('category', 'propagatable'));
    }

    public function edit(ItemCategory $category): View
    {
        return view('articles.categories.form', ['category' => $category, 'formData' => $this->formData()]);
    }

    public function update(Request $request, ItemCategory $category): RedirectResponse
    {
        $data = $this->validateData($request, $category);
        if (array_key_exists('coil_managed', $data)) {
            $category->assertCoilManagementToggleAllowed((bool) $data['coil_managed']);
        }
        $old = $category->only(array_keys($data));
        $category->update($data);
        Log::info('[X3] Catégorie modifiée', ['code' => $category->code, 'user_id' => auth()->id(), 'avant' => $old, 'apres' => $data]);

        return redirect()->route('articles.categories.show', $category)
            ->with('success', 'Catégorie mise à jour — les articles existants ne sont PAS modifiés (utiliser « Propager » si besoin).');
    }

    /** Désactivation (jamais de suppression si utilisée — X3 §15). */
    public function disable(ItemCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);
        Log::info('[X3] Catégorie ' . ($category->is_active ? 'réactivée' : 'désactivée'), ['code' => $category->code, 'user_id' => auth()->id()]);

        return back()->with('success', 'Catégorie ' . ($category->is_active ? 'réactivée' : 'désactivée') . '.');
    }

    public function duplicate(ItemCategory $category): RedirectResponse
    {
        $copy = $category->replicate();
        $copy->code = $category->code . '-COPIE';
        $copy->name = $category->name . ' (copie)';
        $copy->is_active = false;
        $copy->save();

        return redirect()->route('articles.categories.edit', $copy)->with('success', 'Catégorie dupliquée — ajustez le code puis activez-la.');
    }

    /** [X3 §8] Aperçu de propagation (rien n'est modifié). */
    public function propagatePreview(Request $request, ItemCategory $category): View
    {
        $fields = (array) $request->input('fields', []);
        $preview = app(CategoryPropagationService::class)->preview($category, $fields);

        return view('articles.categories.propagate', compact('category', 'preview', 'fields'));
    }

    /** [X3 §8] Exécution de la propagation (transaction + journal). */
    public function propagate(Request $request, ItemCategory $category): RedirectResponse
    {
        $request->validate(['fields' => 'required|array|min:1']);
        $report = app(CategoryPropagationService::class)->propagate($category, (array) $request->input('fields'));

        return redirect()->route('articles.categories.show', $category)
            ->with('success', 'Propagation effectuée : ' . $report['count'] . ' article(s) mis à jour (' . implode(', ', $report['fields']) . ').');
    }

    private function validateData(Request $request, ?ItemCategory $category = null): array
    {
        $data = $request->validate([
            'code'        => 'required|string|max:30|unique:item_categories,code' . ($category ? ',' . $category->id : ''),
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
            'sort_order'  => 'nullable|integer|min:0|max:65000',
            'nature'      => 'required|in:matiere_premiere,semi_fini,produit_fini,marchandise,consommable,service,sous_produit,chute,rebut',
            'strategy'    => 'nullable|in:mto,mts,achat_revente,service,conso_interne',
            'is_purchasable' => 'boolean', 'is_sellable' => 'boolean', 'is_stockable' => 'boolean',
            'is_manufactured' => 'boolean', 'is_subcontracted' => 'boolean',
            'usable_in_bom' => 'boolean', 'usable_as_finished' => 'boolean',
            'allow_negative_stock' => 'boolean', 'lot_managed' => 'boolean', 'serial_managed' => 'boolean',
            'coil_managed' => 'boolean', 'expiry_managed' => 'boolean', 'qc_on_receipt' => 'boolean',
            'qc_required' => 'boolean', 'bom_required' => 'boolean', 'routing_required' => 'boolean',
            'auto_of' => 'boolean', 'mrp_planned' => 'boolean', 'floor_price_required' => 'boolean',
            'exempt_allowed' => 'boolean', 'deposit_required' => 'boolean', 'credit_check' => 'boolean',
            'offcut_managed' => 'boolean', 'cutting_optimized' => 'boolean', 'site_declinable' => 'boolean',
            'valuation_method' => 'nullable|in:cmp,fifo',
            'default_stock_min' => 'nullable|numeric|min:0',
            'default_stock_max' => 'nullable|numeric|min:0',
            'default_stock_securite' => 'nullable|numeric|min:0',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'receipt_tolerance_percent' => 'nullable|numeric|min:0|max:100',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'setup_loss' => 'nullable|numeric|min:0',
            'scrap_rate_percent' => 'nullable|numeric|min:0|max:100',
            'default_sale_unit_id' => 'nullable|exists:units,id',
            'default_purchase_unit_id' => 'nullable|exists:units,id',
            'default_pricing_unit_id' => 'nullable|exists:units,id',
            'default_tax_rate_id' => 'nullable|exists:tax_rates,id',
            'default_receipt_warehouse_id' => 'nullable|exists:warehouses,id',
            'default_mp_warehouse_id' => 'nullable|exists:warehouses,id',
            'default_pf_warehouse_id' => 'nullable|exists:warehouses,id',
            'default_production_line_id' => 'nullable|exists:production_lines,id',
            'stock_account_id' => 'nullable|exists:accounts,id',
            'purchase_account_id' => 'nullable|exists:accounts,id',
            'sale_account_id' => 'nullable|exists:accounts,id',
            'variation_account_id' => 'nullable|exists:accounts,id',
            'consumption_account_id' => 'nullable|exists:accounts,id',
            'scrap_account_id' => 'nullable|exists:accounts,id',
            'finished_account_id' => 'nullable|exists:accounts,id',
            'cost_method' => 'nullable|in:standard,moyen',
            'overridable_fields' => 'nullable|array',
            'overridable_fields.*' => 'string|max:60',
        ]);

        if (isset($data['default_stock_min'], $data['default_stock_max'])
            && (float) $data['default_stock_max'] < (float) $data['default_stock_min']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_stock_max' => 'Le stock maxi par défaut doit être supérieur ou égal au stock mini.',
            ]);
        }

        return $data;
    }

    private function formData(): array
    {
        return [
            'units'      => \App\Models\Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'taxRates'   => \App\Models\TaxRate::where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'lines'      => \App\Modules\Production\Models\ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'accounts'   => \App\Models\Account::orderBy('code')->limit(500)->get(['id', 'code', 'name']),
            // [SAGE parité] Panneau gauche « sélection » des formulaires.
            // Sans plafond : son champ « Filtrer » est un x-show Alpine, qui ne
            // cherche que dans les lignes déjà chargées. Une catégorie hors des
            // vingt premières serait introuvable, et l'utilisateur en conclurait
            // qu'elle n'existe pas. Même correction que le panneau des familles.
            // Onze catégories aujourd'hui : le défaut est dormant, pas absent.
            'selectorCategories' => ItemCategory::where('is_active', true)
                ->orderBy('sort_order')->orderBy('code')
                ->get(['id', 'code', 'name', 'strategy']),
        ];
    }
}
