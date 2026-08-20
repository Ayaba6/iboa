<?php
namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Account;
use App\Models\Brand;
use App\Models\ProductFamily;
use App\Models\ProductPromotion;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct(
        private ProductService $service,
        private ProductRepository $repository
    ) {}

    public function index(Request $request): View|BinaryFileResponse
    {
        $this->authorize('viewAny', Product::class);
        if ($request->boolean('export')) {
            return Excel::download(
                new ProductsExport($request->only(['search', 'family_id', 'brand_id', 'type', 'item_category_id'])),
                'articles-' . now()->format('Ymd') . '.xlsx'
            );
        }

        $products  = $this->repository->search($request->all(), 20);
        $families  = ProductFamily::whereNull('parent_id')->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('name')->get();
        $familiesFlat = ProductFamily::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $brands    = Brand::where('is_active', true)->orderBy('name')->get();
        $itemCategories = \App\Models\ItemCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']);

        $summary = [
            'total'     => Product::count(),
            'active'    => Product::where('is_active', true)->count(),
            'sellable'  => Product::where('is_active', true)->where('is_sellable', true)->count(),
            'purchasable'=> Product::where('is_active', true)->where('is_purchasable', true)->count(),
        ];

        return view('products.index', compact('products', 'families', 'familiesFlat', 'brands', 'itemCategories', 'summary'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);
        [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts] =
            $this->loadFormReferenceData();
        $warehouses        = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_production', 'can_sale', 'can_purchase', 'can_stock']);
        $familiesFlat      = ProductFamily::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $typeArticleOptions = Product::typeArticleOptions();
        return view('products.create', array_merge(compact(
            'families', 'brands', 'units', 'taxRates', 'suppliers', 'accounts', 'componentProducts',
            'warehouses', 'familiesFlat', 'typeArticleOptions'
        ), $this->sageReferenceData()));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validated();
        unset($data['depots'], $data['documents']);

        $product = $this->service->create($data, $request->file('image'));
        $this->syncDepots($product, $request);
        $this->uploadDocuments($product, $request);

        return redirect()->route('products.show', $product)->with('success', 'Article créé avec succès.');
    }

    /** Données de référence SAGE partagées create/edit (centres de coût, machines, articles liés). */
    private function sageReferenceData(): array
    {
        return [
            'costCenters' => \App\Models\CostCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'machines'    => \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'linkables'   => Product::where('is_active', true)->orderBy('name')->get(['id', 'code_article', 'name']),
            // [SAGE parité] Panneau gauche « sélection ».
            //
            // Le panneau reste PLAFONNÉ : le catalogue d'articles grandit, le
            // charger entièrement à chaque ouverture de fiche ne tiendrait pas.
            // Mais son champ « Filtrer » est un x-show Alpine, purement local :
            // il ne cherche que dans les lignes chargées. Le pied du panneau le
            // dit désormais et renvoie vers la liste, qui cherche côté serveur.
            //
            // Tri par CODE et non par id décroissant : « les vingt derniers créés »
            // est un ordre que l'utilisateur ne peut ni prévoir ni parcourir.
            'selectorProducts' => Product::with('family:id,code,name')
                ->orderBy('code_article')->limit(50)
                ->get(['id', 'code_article', 'reference', 'name', 'family_id']),
            'selectorProductsTotal' => Product::count(),
        ];
    }

    /** Synchronise le pivot des dépôts autorisés (4 capacités par dépôt). */
    private function syncDepots(Product $product, Request $request): void
    {
        $sync = [];
        foreach ((array) $request->input('depots', []) as $warehouseId => $caps) {
            $sync[(int) $warehouseId] = [
                'can_production' => ! empty($caps['can_production']),
                'can_sale'       => ! empty($caps['can_sale']),
                'can_purchase'   => ! empty($caps['can_purchase']),
                'can_stock'      => ! empty($caps['can_stock']),
            ];
        }
        $product->warehouses()->sync($sync);
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);
        $product = $this->repository->findWithDetails($product->id);

        $promotions = ProductPromotion::where(function ($q) use ($product) {
            $q->where('product_id', $product->id);
            if ($product->family_id) {
                $q->orWhere('family_id', $product->family_id);
            }
        })->orderByDesc('starts_at')->get();

        $recentMovements = $product->stockMovements()
            ->with('warehouse')
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        return view('products.show', compact('product', 'promotions', 'recentMovements'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);
        $product = $this->repository->findWithDetails($product->id);
        // [Bobines → article] les bobines physiques (lots matière) s'affichent sur la fiche
        $product->load(['warehouses', 'attachments', 'coils' => fn ($q) => $q->latest('received_at')->limit(15)]);
        [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts] =
            $this->loadFormReferenceData($product->id);
        $warehouses         = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_production', 'can_sale', 'can_purchase', 'can_stock']);
        $familiesFlat       = ProductFamily::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $typeArticleOptions = Product::typeArticleOptions();
        return view('products.edit', array_merge(compact(
            'product', 'families', 'brands', 'units', 'taxRates', 'suppliers', 'accounts', 'componentProducts',
            'warehouses', 'familiesFlat', 'typeArticleOptions'
        ), $this->sageReferenceData()));
    }

    /**
     * Charge les données de référence partagées par create/edit.
     * @return array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed,5:mixed,6:mixed}
     */
    private function loadFormReferenceData(?int $excludeProductId = null): array
    {
        $families = $this->service->getFamiliesTree();
        $brands   = Brand::where('is_active', true)->orderBy('name')->get();
        $units    = Unit::where('is_active', true)->orderBy('name')->get();
        $taxRates = TaxRate::where('is_active', true)->orderBy('rate')->get();

        $suppliers = Supplier::orderBy('name')->get(['id', 'code', 'name']);

        // Plan comptable filtré pour ventes (7xx), achats (6xx), stock (3xx)
        $accounts = Account::orderBy('code')->get(['id', 'code', 'name', 'company_id']);

        $componentProductsQ = Product::where('is_active', true)
            ->whereIn('type', ['simple', 'service']);
        if ($excludeProductId) {
            $componentProductsQ->where('id', '!=', $excludeProductId);
        }
        $componentProducts = $componentProductsQ->orderBy('name')->get(['id', 'name', 'reference']);

        return [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts];
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $data = $request->validated();
        unset($data['depots'], $data['documents']);

        $this->service->update($product, $data, $request->file('image'));
        $this->syncDepots($product, $request);
        $this->uploadDocuments($product, $request);

        return redirect()->route('products.show', $product)->with('success', 'Article mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->service->delete($product);
        return redirect()->route('products.index')->with('success', 'Article supprimé.');
    }

    /** [X3 §10] Ajoute/actualise la déclinaison article-site (upsert par site). */
    public function storeSite(\Illuminate\Http\Request $request, Product $product): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $product);
        $data = $request->validate([
            'site_id'              => 'required|exists:warehouses,id',
            'mp_warehouse_id'      => 'nullable|exists:warehouses,id',
            'pf_warehouse_id'      => 'nullable|exists:warehouses,id',
            'receipt_warehouse_id' => 'nullable|exists:warehouses,id',
            'production_line_id'   => 'nullable|exists:production_lines,id',
            'lead_time_days'       => 'nullable|integer|min:0|max:365',
            'stock_min'            => 'nullable|numeric|min:0',
            'stock_max'            => 'nullable|numeric|min:0',
            'stock_securite'       => 'nullable|numeric|min:0',
        ]);

        $product->productSites()->updateOrCreate(['site_id' => $data['site_id']], $data);

        return back()->with('success', 'Déclinaison site enregistrée.');
    }

    public function destroySite(Product $product, \App\Models\ProductSite $site): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($site->product_id === $product->id, 404);
        $site->delete();

        return back()->with('success', 'Déclinaison site supprimée.');
    }
}
