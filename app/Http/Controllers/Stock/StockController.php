<?php

namespace App\Http\Controllers\Stock;

use App\Exports\StockExport;
use App\Exports\StockMovementsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreMovementRequest;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * Display the stock levels (niveaux de stock).
     */
    public function index(Request $request, \App\Services\StockInsightsService $insights): View
    {
        $this->authorize('viewAny', StockMovement::class);
        $filters = $request->only(['search', 'warehouse_id', 'low_stock']);

        $stocks     = $this->stockService->getStockSummary($filters);
        $warehouses = Warehouse::active()->orderBy('name')->get();

        // [Cohérence stock] Ces deux compteurs étaient calculés ici avec leurs
        // propres formules : `<=` au lieu de `<`, et des LIGNES de stock au lieu
        // d'ARTICLES. Le tableau de bord répondait donc autrement à la même
        // question, et un article à zéro se comptait deux fois. Source unique
        // désormais : StockInsightsService.
        $lowStockCount = $insights->compter($insights->sousMinimumQuery());
        $ruptureCount  = $insights->compter($insights->ruptureQuery());

        return view('stocks.index', compact('stocks', 'warehouses', 'filters', 'lowStockCount', 'ruptureCount'));
    }

    /**
     * Export stock état complet en Excel.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $warehouseId  = $request->input('warehouse_id') ?: null;
        $search       = $request->input('search') ?: null;
        $lowStockOnly = $request->boolean('low_stock');

        $filename = 'etat-stock-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new StockExport($warehouseId, $search, $lowStockOnly),
            $filename
        );
    }

    public function exportPdf(Request $request): mixed
    {
        $company     = currentCompany();
        $warehouseId = $request->input('warehouse_id') ?: null;
        $search      = $request->input('search') ?: null;
        $lowStock    = $request->boolean('low_stock');

        $stocks = ProductStock::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search, fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')
            ))
            ->when($lowStock, fn($q) => $q->whereHas('product', fn($p) =>
                $p->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= products.stock_min')
            ))
            ->orderBy('product_id')
            ->get();

        $warehouses = Warehouse::active()->orderBy('name')->get();
        $warehouseName = $warehouseId ? optional(Warehouse::find($warehouseId))->name : null;

        $pdf = Pdf::loadView('stocks.pdf.index', compact(
            'company', 'stocks', 'warehouseName', 'search', 'lowStock'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('etat_stock_' . now()->format('Ymd_His') . '.pdf');
    }

    /** [Maquette X3] Formulaire de mouvement manuel de stock. */
    public function createManualMovement(): View
    {
        $this->authorize('create', StockMovement::class);

        $company    = currentCompany();
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'code', 'name']);
        $products   = Product::where('is_active', true)->where('is_stockable', true)
            ->orderBy('name')->get(['id', 'reference', 'name', 'weighted_avg_cost']);

        // [Finition X3] Stock disponible par article/dépôt (dispo = quantité − réservé),
        // affiché en direct sur chaque ligne pour prévenir la saisie d'une sortie
        // supérieure au stock (le mouvement est bloqué côté service sinon).
        $stockMap = \App\Models\ProductStock::query()
            ->selectRaw('product_id, warehouse_id, SUM(quantity - reserved_quantity) AS available')
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [(int) $r->warehouse_id => (float) $r->available]));

        $svc  = app(\App\Services\DocumentSequenceService::class);
        $seq  = \App\Models\DocumentSequence::firstOrCreate(
            ['company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id, 'document_type' => 'mouvement_stock'],
            $svc->defaultConfig('mouvement_stock')
        );
        $nextNumber = $svc->format($seq, $seq->last_number + 1);

        return view('stock.manual-movement', compact('warehouses', 'products', 'nextNumber', 'stockMap'));
    }

    /** [Maquette X3] Enregistre le mouvement manuel : entête + lignes stock signées. */
    public function storeManualMovement(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', StockMovement::class);

        $data = $request->validate([
            'occurred_at'       => ['required', 'date'],
            'occurred_time'     => ['nullable', 'date_format:H:i'],
            'warehouse_from_id' => ['nullable', 'exists:warehouses,id'],
            'warehouse_to_id'   => ['required', 'exists:warehouses,id'],
            'location_from'     => ['nullable', 'string', 'max:50'],
            'location_to'       => ['nullable', 'string', 'max:50'],
            'reason'            => ['required', 'in:ajustement,correction,don,perte,casse,autre'],
            'comment'           => ['nullable', 'string', 'max:500'],
            'project_reference' => ['nullable', 'string', 'max:60'],
            'analytic_section'  => ['nullable', 'string', 'max:60'],
            'accounting_date'   => ['nullable', 'date'],
            'is_blocked'        => ['boolean'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'numeric', 'not_in:0'],
            'items.*.unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'items.*.lot_number'    => ['nullable', 'string', 'max:100'],
        ], [
            'items.required'          => "Ajoutez au moins une ligne d'article.",
            'items.*.quantity.not_in' => "La quantité d'une ligne ne peut pas être nulle (positif = entrée, négatif = sortie).",
        ]);

        $company = currentCompany();

        try {
            $header = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $company) {
                $header = \App\Models\ManualStockMovement::create([
                    'company_id'        => $company->id,
                    'number'            => app(\App\Services\DocumentSequenceService::class)->nextNumber($company, 'mouvement_stock'),
                    'movement_type'     => 'manuel',
                    'occurred_at'       => $data['occurred_at'],
                    'occurred_time'     => $data['occurred_time'] ?? null,
                    'status'            => $request->boolean('is_blocked') ? 'bloque' : 'saisi',
                    'currency_code'     => 'XOF',
                    'warehouse_from_id' => $data['warehouse_from_id'] ?? null,
                    'warehouse_to_id'   => $data['warehouse_to_id'],
                    'location_from'     => $data['location_from'] ?? null,
                    'location_to'       => $data['location_to'] ?? null,
                    'reason'            => $data['reason'],
                    'comment'           => $data['comment'] ?? null,
                    'project_reference' => $data['project_reference'] ?? null,
                    'analytic_section'  => $data['analytic_section'] ?? null,
                    'accounting_date'   => $data['accounting_date'] ?? null,
                    'is_blocked'        => $request->boolean('is_blocked'),
                    'lines'             => $data['items'],
                    'created_by'        => \Illuminate\Support\Facades\Auth::id(),
                ]);

                // [FIX mouvement bloqué] Un mouvement BLOQUÉ est enregistré mais PAS
                // appliqué au stock — ses lignes attendent le déblocage explicite.
                if (!$header->is_blocked) {
                    $this->applyManualMovementLines($header);
                }

                return $header;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stocks.movements')
            ->with('success', $header->is_blocked
                ? 'Mouvement manuel ' . $header->number . ' enregistré BLOQUÉ — le stock ne sera impacté qu\'au déblocage.'
                : 'Mouvement manuel ' . $header->number . ' enregistré — ' . count($data['items']) . ' ligne(s).');
    }

    /**
     * [FIX mouvement bloqué] Débloque un mouvement manuel : applique ses lignes
     * au stock (jusque-là conservées sans effet) puis passe le statut à « saisi ».
     */
    public function unblockManualMovement(\App\Models\ManualStockMovement $movement): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', StockMovement::class);

        if (!$movement->is_blocked) {
            return back()->with('error', "Le mouvement {$movement->number} n'est pas bloqué.");
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($movement) {
                $this->applyManualMovementLines($movement);
                $movement->update([
                    'is_blocked'   => false,
                    'status'       => 'saisi',
                    'unblocked_at' => now(),
                    'unblocked_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Mouvement {$movement->number} débloqué — stock mis à jour.");
    }

    /**
     * Applique les lignes (JSON) d'un mouvement manuel au stock.
     * [Sync ERP] journalisé + idempotent — qté signée : positif = entrée (dépôt
     * destination), négatif = sortie (dépôt origine sinon destination).
     */
    private function applyManualMovementLines(\App\Models\ManualStockMovement $header): void
    {
        app(\App\Services\Sync\SyncOrchestrator::class)->run(
            sourceModule: 'stock',
            targetModule: 'stock',
            eventName: 'manual_movement.created',
            action: 'create_stock_adjustments',
            source: $header,
            callback: function () use ($header) {
                $total = 0;
                foreach ($header->lines ?? [] as $line) {
                    $qty = (float) $line['quantity'];
                    $warehouseId = $qty >= 0
                        ? $header->warehouse_to_id
                        : ($header->warehouse_from_id ?? $header->warehouse_to_id);
                    $unitCost = (float) ($line['unit_cost'] ?? 0);

                    // Type « ajustement » : StockService attend une quantite SIGNEE.
                    $this->stockService->recordMovement([
                        'product_id'     => $line['product_id'],
                        'warehouse_id'   => $warehouseId,
                        'type'           => 'ajustement',
                        'quantity'       => $qty,
                        'unit_cost'      => $unitCost,
                        'occurred_at'    => $header->occurred_at->toDateString(),
                        'reference_type' => 'mouvement_manuel',
                        'reference_id'   => $header->id,
                        'lot_number'     => $line['lot_number'] ?? null,
                        'notes'          => 'Mouvement manuel ' . $header->number . ' — ' . $header->reason,
                    ]);
                    $total += $qty * $unitCost;
                }
                $header->update(['total_value' => $total]);
            },
            payload: ['lines' => count($header->lines ?? [])],
        );
    }

    public function movementsPdf(Request $request): mixed
    {
        $company = currentCompany();
        $filters = $request->only(['search', 'product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

        $movements = StockMovement::with(['product.family', 'warehouse', 'createdBy'])
            ->when(!empty($filters['product_id']),   fn($q) => $q->where('product_id', $filters['product_id']))
            ->when(!empty($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(!empty($filters['type']),         fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['date_from']),    fn($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),      fn($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),       fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', '%'.$filters['search'].'%')
                  ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->get();

        $typeLabels = [
            'entree'             => 'Entrée',
            'sortie'             => 'Sortie',
            'transfert'          => 'Transfert',
            'ajustement'         => 'Ajustement',
            'inventaire'         => 'Inventaire',
            'retour_client'      => 'Retour client',
            'retour_fournisseur' => 'Retour fournisseur',
        ];

        $pdf = Pdf::loadView('stocks.pdf.movements', compact(
            'company', 'movements', 'filters', 'typeLabels'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('mouvements_stock_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Display the stock movement history.
     */
    public function movements(Request $request): View|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filters = $request->only(['search', 'product_id', 'warehouse_id', 'type', 'date_from', 'date_to', 'lot', 'user_id', 'ref']);

        if ($request->boolean('export')) {
            return Excel::download(
                new StockMovementsExport($filters),
                'mouvements-stock-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $movements  = $this->stockService->getMovements($filters);
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $products   = Product::active()->orderBy('name')->get(['id', 'name', 'reference']);
        $users      = \App\Models\User::orderBy('name')->get(['id', 'name']);

        $kpiQuery = StockMovement::query()
            ->when(!empty($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(!empty($filters['date_from']),    fn($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),      fn($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']));

        $kpiByType = (clone $kpiQuery)->selectRaw('type, COUNT(*) as nb')->groupBy('type')->pluck('nb', 'type');
        $kpis = [
            'entrees'     => ($kpiByType['entree'] ?? 0) + ($kpiByType['retour_client'] ?? 0),
            'sorties'     => ($kpiByType['sortie'] ?? 0) + ($kpiByType['retour_fournisseur'] ?? 0),
            'transferts'  => $kpiByType['transfert'] ?? 0,
            'ajustements' => ($kpiByType['ajustement'] ?? 0) + ($kpiByType['inventaire'] ?? 0),
            'aujourdhui'  => StockMovement::whereDate('occurred_at', today())->count(),
        ];

        $mouvementsCritiques = StockMovement::with(['product', 'warehouse'])
            ->whereIn('type', ['ajustement', 'inventaire', 'retour_client', 'retour_fournisseur'])
            ->orderByDesc('occurred_at')->limit(5)->get();

        $tracabilite = StockMovement::with(['product', 'warehouse', 'createdBy'])
            ->whereNotNull('reference_type')->whereNotNull('reference_id')
            ->orderByDesc('occurred_at')->limit(6)->get();

        // Stock avant / après par ligne (cumul signé des mouvements du produit+dépôt)
        $signExpr = "CASE WHEN type IN ('entree','retour_client') THEN quantity"
                  . " WHEN type IN ('ajustement','inventaire') THEN quantity"
                  . " ELSE -ABS(quantity) END";
        $stockApres = [];
        $stockAvant = [];
        foreach ($movements as $m) {
            $after = (float) StockMovement::where('product_id', $m->product_id)
                ->where('warehouse_id', $m->warehouse_id)
                ->where(function ($q) use ($m) {
                    $q->where('occurred_at', '<', $m->occurred_at)
                      ->orWhere(fn ($w) => $w->where('occurred_at', $m->occurred_at)->where('id', '<=', $m->id));
                })
                ->selectRaw("COALESCE(SUM($signExpr), 0) as s")->value('s');
            $signed = in_array($m->type, ['entree', 'retour_client']) ? (float) $m->quantity
                : (in_array($m->type, ['ajustement', 'inventaire']) ? (float) $m->quantity : -abs((float) $m->quantity));
            $stockApres[$m->id] = $after;
            $stockAvant[$m->id] = $after - $signed;
        }

        // [FIX mouvement bloqué] mouvements manuels en attente de déblocage
        $blockedManuals = \App\Models\ManualStockMovement::with('warehouseTo', 'creator')
            ->where('is_blocked', true)->orderByDesc('id')->get();

        return view('stocks.movements', compact(
            'movements', 'warehouses', 'products', 'users', 'filters',
            'kpis', 'mouvementsCritiques', 'tracabilite', 'stockAvant', 'stockApres', 'blockedManuals'
        ));
    }

    /**
     * Show the form for creating a manual stock movement.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', StockMovement::class);
        $products   = Product::active()->where('is_stockable', true)->orderBy('name')
            ->get(['id', 'name', 'reference', 'purchase_price', 'type', 'has_lot_number', 'has_serial_number', 'has_expiry_date']);
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']);

        $movementTypes = [
            'entree'             => 'Entrée en stock',
            'sortie'             => 'Sortie de stock',
            'transfert'          => 'Transfert',
            'ajustement'         => 'Ajustement de stock',
            'retour_client'      => 'Retour client',
            'retour_fournisseur' => 'Retour fournisseur',
        ];

        return view('stocks.movement-create', compact('products', 'warehouses', 'movementTypes'));
    }

    /**
     * Display stock lots (traceability: lot numbers, serial numbers, expiry dates).
     */
    public function lots(Request $request): View
    {
        $filters = $request->only(['search', 'warehouse_id', 'status', 'expiring_soon']);

        $query = StockLot::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true));

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'disponible'); // default: show only available
        }
        if (!empty($filters['expiring_soon'])) {
            $query->expiringSoon(30);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(fn($q) =>
                $q->where('lot_number', 'like', $s)
                  ->orWhere('serial_number', 'like', $s)
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', $s)->orWhere('reference', 'like', $s))
            );
        }

        // Mark expired lots
        StockLot::expired()->update(['status' => 'expire']);

        $lots       = $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC')
                            ->paginate(25)->withQueryString();
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        return view('stocks.lots', compact('lots', 'warehouses', 'filters'));
    }

    /**
     * [§8 CDC] Traçabilité inverse : lot → OF de production → factures → clients impactés.
     * Permet de retrouver tous les clients livrés avec un lot de matière donné.
     */
    public function lotTraceability(\App\Models\StockLot $lot): \Illuminate\View\View
    {
        $lot->load(['product', 'warehouse']);

        // 1. Mouvements de stock liés à ce lot
        $movements = \App\Models\StockMovement::where('lot_number', $lot->lot_number)
            ->where('product_id', $lot->product_id)
            ->with(['warehouse', 'createdBy:id,name'])
            ->orderByDesc('occurred_at')
            ->get();

        // 2. OF ayant consommé des bobines avec ce lot (si c'est une matière première bobine)
        $productionOrders = collect();
        $coilLotNumbers   = collect();
        $coilsFromLot     = collect();
        if (class_exists(\App\Modules\Production\Models\Coil::class)) {
            $coilsFromLot = \App\Modules\Production\Models\Coil::with('supplier:id,name')
                ->where('lot_number', $lot->lot_number)
                ->orWhere('reference', 'like', '%' . $lot->lot_number . '%')
                ->get();
            if ($coilsFromLot->isNotEmpty()) {
                $coilIds = $coilsFromLot->pluck('id');
                $coilLotNumbers = $coilsFromLot->pluck('lot_number', 'id');
                $productionOrders = \App\Modules\Production\Models\ProductionOrder::whereHas(
                    'consumptions', fn ($q) => $q->whereIn('coil_id', $coilIds)
                )->with(['client', 'order', 'product'])->get();
            }
        }

        // [CDC §9.2] Fournisseur (via la bobine si trouvée) + certificat qualité du lot —
        // requis dans la fiche de traçabilité matière (fournisseur, poids, contrôle qualité).
        $supplier = $coilsFromLot->first()?->supplier;
        $certificates = \App\Models\QualityCertificate::where('lot_number', $lot->lot_number)
            ->with(['controleur:id,name', 'validateur:id,name'])
            ->orderByDesc('date_certificat')
            ->get();

        // 3. Clients impactés via les OF trouvés
        $impactedClients = collect();
        $impactedInvoices = collect();
        if ($productionOrders->isNotEmpty()) {
            $orderIds = $productionOrders->pluck('order_id')->filter()->unique();
            if ($orderIds->isNotEmpty()) {
                $impactedInvoices = \App\Models\Invoice::whereIn('order_id', $orderIds)
                    ->with('client:id,name,code')
                    ->get();
                $impactedClients = $impactedInvoices->pluck('client')->filter()->unique('id');
            }
        }

        // 4. Clients livrés directement via mouvements de stock (sorties sur BL)
        $deliveryMovements = $movements->where('type', 'sortie');

        return view('stocks.lot-traceability', compact(
            'lot', 'movements', 'productionOrders',
            'impactedClients', 'impactedInvoices', 'deliveryMovements', 'coilLotNumbers',
            'supplier', 'certificates', 'coilsFromLot'
        ));
    }

    /**
     * Display detailed stock view for a single product (all warehouses + movement history).
     */
    public function show(Product $product): View
    {
        // Stock across all warehouses for this product
        $stocks = ProductStock::where('product_id', $product->id)
            ->with('warehouse')
            ->orderByDesc('quantity')
            ->get();

        // Totals
        $totalQty      = $stocks->sum(fn($s) => (float) $s->quantity);
        $totalReserved = $stocks->sum(fn($s) => (float) $s->reserved_quantity);
        $totalAvailable = $totalQty - $totalReserved;
        $avgCost       = $stocks->where('quantity', '>', 0)->avg('avg_cost') ?? 0;

        // Recent movement history (paginated)
        $movements = StockMovement::where('product_id', $product->id)
            ->with(['warehouse', 'createdBy:id,name'])
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString();

        return view('stocks.show', compact(
            'product', 'stocks',
            'totalQty', 'totalReserved', 'totalAvailable', 'avgCost',
            'movements'
        ));
    }

    /**
     * Stock valuation report — total value per product/warehouse using avg_cost.
     */
    public function valuation(Request $request): View
    {
        $warehouseId = $request->input('warehouse_id');
        $familyId    = $request->input('family_id');
        $method      = $request->input('method', ''); // '' = all methods

        $query = ProductStock::with(['product.family', 'product.unit', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true)->where('is_stockable', true))
            ->where('quantity', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($familyId) {
            $query->whereHas('product', fn($q) => $q->where('family_id', $familyId));
        }
        if ($method) {
            $query->whereHas('product', fn($q) => $q->where('valuation_method', $method));
        }

        $stocks = $query->orderBy('warehouse_id')
                        ->orderByRaw('(SELECT name FROM products WHERE id = product_stocks.product_id)')
                        ->get();

        $totalValue     = $stocks->sum(fn($s) => (float)$s->quantity * (float)$s->avg_cost);
        $byWarehouse    = $stocks->groupBy('warehouse_id');
        $warehouses     = Warehouse::active()->orderBy('name')->get(['id', 'name']);
        $families       = \App\Models\ProductFamily::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('stocks.valuation', compact(
            'stocks', 'totalValue', 'byWarehouse',
            'warehouses', 'families',
            'warehouseId', 'familyId', 'method'
        ));
    }

    /**
     * [STOCK-PRO] Batch seuils min / max / réappro editor.
     */
    public function seuils(Request $request): View
    {
        $this->authorize('create', \App\Models\StockMovement::class);

        $search     = $request->input('search');
        $familyId   = $request->input('family_id');
        $alertOnly  = $request->boolean('alert_only');

        $query = Product::active()->where('is_stockable', true)
            ->with(['family', 'unit', 'productStocks'])
            ->orderBy('name');

        if ($search) {
            $query->where(fn($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('reference', 'like', '%'.$search.'%'));
        }
        if ($familyId) {
            $query->where('family_id', $familyId);
        }
        if ($alertOnly) {
            $query->where(function ($q) {
                $q->whereRaw('stock_min > 0')
                  ->whereHas('productStocks', fn($s) =>
                      $s->whereRaw('(quantity - reserved_quantity) <= (SELECT stock_min FROM products WHERE id = product_stocks.product_id)')
                  );
            });
        }

        $products = $query->paginate(50)->withQueryString();
        $families = \App\Models\ProductFamily::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('stocks.seuils', compact('products', 'families', 'search', 'familyId', 'alertOnly'));
    }

    /**
     * [STOCK-PRO] Save batch seuils update.
     */
    public function seuilsUpdate(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\StockMovement::class);

        $data = $request->input('seuils', []);

        foreach ($data as $productId => $values) {
            $product = Product::find((int) $productId);
            if (! $product) {
                continue;
            }
            $product->update([
                'stock_min'    => isset($values['stock_min'])    && $values['stock_min']    !== '' ? (float) $values['stock_min']    : null,
                'stock_max'    => isset($values['stock_max'])    && $values['stock_max']    !== '' ? (float) $values['stock_max']    : null,
                'reorder_point'=> isset($values['reorder_point'])&& $values['reorder_point']!== '' ? (float) $values['reorder_point']: null,
            ]);
        }

        return back()->with('success', count($data) . ' article(s) mis à jour.');
    }

    /**
     * Store a new manual stock movement.
     */
    public function storeMovement(StoreMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\StockMovement::class);
        try {
            $this->stockService->recordMovement($request->validated());

            return redirect()
                ->route('stocks.movements')
                ->with('success', 'Mouvement de stock enregistré avec succès.');
        } catch (ValidationException $e) {
            // Stock insuffisant or other business-rule validation — show field errors
            throw $e;
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }
}
