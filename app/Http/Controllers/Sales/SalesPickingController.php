<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\SalesPickingControl;
use App\Models\SalesPickingItem;
use App\Models\StockLot;
use App\Modules\Production\Models\Coil;
use App\Services\DeliveryNoteService;
use App\Services\Sales\SalesPickingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [Ventes §18] Bons de préparation QUANTIFIÉS.
 *
 * Le contrôleur ne contient AUCUNE règle métier : il valide la forme des
 * entrées, appelle SalesPickingService, et restitue le message. Les reliquats,
 * les interdictions d'allocation, les écarts, la séparation des acteurs et
 * l'idempotence vivent dans le service.
 *
 * Permissions — trois niveaux distincts, jamais un « update » fourre-tout :
 *   bon_preparations.view      consultation
 *   bon_preparations.update    créer / lancer / allouer / prélever / annuler
 *   bon_preparations.control   contrôler
 *   bon_preparations.validate  valider et créer le bon de livraison
 */
class SalesPickingController extends Controller
{
    public function __construct(private SalesPickingService $service)
    {
        $this->middleware('permission:bon_preparations.view')->only(['index', 'show']);
        $this->middleware('permission:bon_preparations.update')
            ->only(['create', 'store', 'start', 'allocate', 'pick', 'cancel']);
        $this->middleware('permission:bon_preparations.control')->only(['control']);
        $this->middleware('permission:bon_preparations.validate')->only(['validatePicking', 'createDeliveryNote']);
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'search', 'warehouse_id']);

        $pickings = SalesPicking::with(['order.client', 'warehouse', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($sq) => $sq
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$s}%"))
                ->orWhereHas('order.client', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => SalesPicking::count(),
            'a_preparer' => SalesPicking::whereIn('status', [
                SalesPicking::STATUS_BROUILLON, SalesPicking::STATUS_A_PREPARER,
            ])->count(),
            'en_cours' => SalesPicking::whereIn('status', [
                SalesPicking::STATUS_EN_PREPARATION, SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
            ])->count(),
            'a_controler' => SalesPicking::where('status', SalesPicking::STATUS_PREPARE)->count(),
            'a_valider' => SalesPicking::where('status', SalesPicking::STATUS_CONTROLE)->count(),
            'valides' => SalesPicking::where('status', SalesPicking::STATUS_VALIDE)->count(),
        ];

        return view('ventes.preparations.index', compact('pickings', 'filters', 'summary'));
    }

    public function create(Request $request): View
    {
        $orders = Order::with('client')
            ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])
            ->latest('id')->limit(100)->get();

        $order = $request->filled('order_id')
            ? Order::with(['client', 'items.product'])->find($request->integer('order_id'))
            : null;

        // Le reliquat affiché vient du SERVICE : l'écran ne le recalcule jamais.
        $remaining = [];
        if ($order) {
            foreach ($order->items as $item) {
                $remaining[$item->id] = $this->service->remainingToPick($item);
            }
        }

        return view('ventes.preparations.create', compact('orders', 'order', 'remaining'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'priority' => ['nullable', 'string', 'max:20'],
            'requested_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $order = Order::findOrFail($data['order_id']);

        try {
            $picking = $this->service->create($order, $data['lines'], [
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'priority' => $data['priority'] ?? 'normale',
                'requested_date' => $data['requested_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                // Clé durable : un double envoi du formulaire ne crée qu'un bon.
                'idempotency_key' => $request->input('idempotency_key'),
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ventes.preparations.show', $picking)
            ->with('success', "Bon de préparation {$picking->number} créé.");
    }

    public function show(SalesPicking $preparation): View
    {
        $preparation->load([
            'order.client', 'warehouse',
            'items.product', 'items.orderItem', 'items.allocations.stockLot', 'items.allocations.coil',
            'controls',
        ]);

        // Stock proposable : lots libérés et valorisés du dépôt du bon.
        $lots = $preparation->warehouse_id
            ? StockLot::with('product')
                ->where('warehouse_id', $preparation->warehouse_id)
                ->where('quality_status', 'libere')
                ->where('valuation_status', 'valorisation_definitive')
                ->where('quantity', '>', 0)
                ->orderBy('received_at')
                ->get()
            : collect();

        $coils = $preparation->warehouse_id && \Illuminate\Support\Facades\Schema::hasTable('coils')
            ? Coil::where('warehouse_id', $preparation->warehouse_id)
                ->where('quality_status', Coil::QUALITY_RELEASED)
                ->where('transformation_status', '!=', Coil::TRANSFO_SPLIT)
                ->where('remaining_weight', '>', 0)
                ->orderBy('received_at')
                ->get()
            : collect();

        return view('ventes.preparations.show', compact('preparation', 'lots', 'coils'));
    }

    public function start(SalesPicking $preparation): RedirectResponse
    {
        return $this->run(fn () => $this->service->start($preparation), 'Préparation lancée.');
    }

    public function allocate(Request $request, SalesPicking $preparation): RedirectResponse
    {
        $data = $request->validate([
            'sales_picking_item_id' => ['required', 'integer', 'exists:sales_picking_items,id'],
            'stock_lot_id' => ['nullable', 'integer', 'exists:stock_lots,id'],
            'coil_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $item = SalesPickingItem::where('sales_picking_id', $preparation->id)
            ->findOrFail($data['sales_picking_item_id']);

        return $this->run(fn () => $this->service->allocate($item, $data), 'Allocation enregistrée.');
    }

    public function pick(Request $request, SalesPicking $preparation): RedirectResponse
    {
        $data = $request->validate([
            'allocation_id' => ['required', 'integer', 'exists:sales_picking_allocations,id'],
            'picked_quantity' => ['required', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = SalesPickingAllocation::whereHas(
            'item', fn ($q) => $q->where('sales_picking_id', $preparation->id)
        )->findOrFail($data['allocation_id']);

        return $this->run(
            fn () => $this->service->pick($allocation, (float) $data['picked_quantity'], $data['variance_reason'] ?? null),
            'Prélèvement enregistré.'
        );
    }

    public function control(Request $request, SalesPicking $preparation): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', 'in:conforme,ecart'],
            'checkpoints' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(
            fn () => $this->service->control($preparation, $data['checkpoints'] ?? [], $data['result'], $data['notes'] ?? null),
            'Contrôle enregistré.'
        );
    }

    public function validatePicking(SalesPicking $preparation): RedirectResponse
    {
        return $this->run(fn () => $this->service->validate($preparation), 'Bon de préparation validé.');
    }

    public function cancel(Request $request, SalesPicking $preparation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->run(fn () => $this->service->cancel($preparation, $data['reason']), 'Bon de préparation annulé.');
    }

    public function createDeliveryNote(SalesPicking $preparation, DeliveryNoteService $deliveryNotes): RedirectResponse
    {
        try {
            $dn = $deliveryNotes->createFromPicking($preparation);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ventes.bons-livraison.show', $dn)
            ->with('success', "Bon de livraison {$dn->number} créé depuis la préparation {$preparation->number}.");
    }

    /**
     * Exécute une action de service et restitue son refus métier tel quel.
     *
     * Le message du service est affiché SANS reformulation : il porte les
     * chiffres exacts (reliquat, disponible, déjà alloué) dont l'utilisateur a
     * besoin pour corriger. Une erreur reformulée en « opération impossible »
     * ferait perdre cette information.
     */
    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
