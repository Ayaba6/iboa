<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionTracking;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * [Parité SAGE X3 — Suivi de fabrication] Écran de saisie unique regroupant,
 * sur un OF en cours, le pointage des opérations de gamme, la déclaration de
 * production (entrée stock PF) et le suivi matière (consommation bobine).
 * Chaque saisie est journalisée dans production_trackings avec un numéro.
 * Les effets réels passent par les services existants — aucun flux dupliqué.
 */
class ProductionTrackingController extends Controller
{
    public function __construct(
        private ProductionStockService $stock,
        private CoilConsumptionService $coils,
    ) {
        $this->middleware('permission:production.view')->only('index');
        $this->middleware('permission:production.update')->only(['create', 'store']);
    }

    public function index(Request $request): View
    {
        $trackings = ProductionTracking::with('productionOrder:id,number,status')
            ->when($request->input('q'), fn ($q, $v) => $q->where('number', 'like', "%$v%")
                ->orWhereHas('productionOrder', fn ($o) => $o->where('number', 'like', "%$v%")))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('production.trackings.index', compact('trackings'));
    }

    public function create(Request $request): View
    {
        $orders = ProductionOrder::whereIn('status', ['en_cours', 'termine_partiellement'])
            ->orderByDesc('id')->get(['id', 'number', 'quantity_requested', 'quantity_produced', 'length', 'site_production']);

        $order = null;
        if ($request->input('order_id')) {
            $order = $orders->firstWhere('id', (int) $request->input('order_id'))
                ? ProductionOrder::with(['operations.workCenter', 'billOfMaterial.lines.product.unit', 'product'])->find($request->input('order_id'))
                : null;
        }

        $coils      = Coil::where('status', '!=', 'epuisee')->orderBy('reference')->get(['id', 'reference', 'remaining_weight', 'cost_per_kg']);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $nextNumber = 'SUIVI-' . now()->year . '-' . str_pad((string) (ProductionTracking::whereYear('created_at', now()->year)->count() + 1), 4, '0', STR_PAD_LEFT);

        return view('production.trackings.create', compact('orders', 'order', 'coils', 'warehouses', 'nextNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'production_order_id' => ['required', 'integer', 'exists:production_orders,id'],
            'tracking_date'       => ['nullable', 'date'],
            'site'                => ['nullable', 'string', 'max:40'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'track_operations'    => ['nullable', 'boolean'],
            'track_production'    => ['nullable', 'boolean'],
            'track_materials'     => ['nullable', 'boolean'],
            // Opérations : minutes réelles saisies par opération
            'operations'                 => ['nullable', 'array'],
            'operations.*.id'            => ['required_with:operations', 'integer'],
            'operations.*.real_minutes'  => ['nullable', 'numeric', 'min:0'],
            // Production
            'quantity'     => ['nullable', 'numeric', 'min:0'],
            'length'       => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'lot_number'   => ['nullable', 'string', 'max:60'],
            // Matière (bobine)
            'coil_id'         => ['nullable', 'integer', 'exists:coils,id'],
            'weight_consumed' => ['nullable', 'numeric', 'min:0'],
            'length_consumed' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = ProductionOrder::with('operations')->findOrFail($data['production_order_id']);
        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['production_order_id' => 'Le suivi n\'est possible que sur un OF « en cours ».']);
        }

        $trackOps  = $request->boolean('track_operations');
        $trackProd = $request->boolean('track_production');
        $trackMat  = $request->boolean('track_materials');
        if (! $trackOps && ! $trackProd && ! $trackMat) {
            throw ValidationException::withMessages(['track_operations' => 'Cochez au moins un type de suivi (opérations, production ou matière).']);
        }
        if ($trackProd && (float) ($data['quantity'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Déclaration production cochée : la quantité réalisée est obligatoire.']);
        }
        if ($trackMat && ((int) ($data['coil_id'] ?? 0) <= 0 || (float) ($data['weight_consumed'] ?? 0) <= 0)) {
            throw ValidationException::withMessages(['coil_id' => 'Suivi matière coché : bobine et poids consommé sont obligatoires.']);
        }

        $tracking = DB::transaction(function () use ($request, $data, $order, $trackOps, $trackProd, $trackMat) {
            // 1. Pointage des opérations (minutes réelles → done)
            if ($trackOps) {
                foreach ((array) $data['operations'] ?? [] as $row) {
                    $op = $order->operations->firstWhere('id', (int) ($row['id'] ?? 0));
                    if (! $op) {
                        continue;
                    }
                    $minutes = (float) ($row['real_minutes'] ?? 0);
                    if ($minutes > 0) {
                        $op->update(['status' => 'done', 'real_minutes' => $minutes, 'ended_at' => now()]);
                    }
                }
            }

            // 2. Déclaration de production (entrée stock PF + conso auto composants)
            if ($trackProd) {
                $this->stock->recordOutput($order, [
                    'quantity'     => (float) $data['quantity'],
                    'length'       => (float) ($data['length'] ?? 0),
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'unit_cost'    => (float) ($data['unit_cost'] ?? 0),
                    'lot_number'   => $data['lot_number'] ?? null,
                    'notes'        => $data['notes'] ?? null,
                ]);
            }

            // 3. Suivi matière — consommation bobine
            if ($trackMat) {
                $coil = Coil::findOrFail((int) $data['coil_id']);
                $this->coils->consume($order, $coil, (float) $data['weight_consumed'], (float) ($data['length_consumed'] ?? 0) ?: null);
            }

            return ProductionTracking::create([
                'company_id'          => $order->company_id,
                'number'              => 'SUIVI-' . now()->year . '-' . str_pad((string) (ProductionTracking::withoutGlobalScopes()->whereYear('created_at', now()->year)->lockForUpdate()->count() + 1), 4, '0', STR_PAD_LEFT),
                'production_order_id' => $order->id,
                'tracking_date'       => $data['tracking_date'] ?? now()->toDateString(),
                'track_operations'    => $trackOps,
                'track_production'    => $trackProd,
                'track_materials'     => $trackMat,
                'site'                => $data['site'] ?? $order->site_production,
                'notes'               => $data['notes'] ?? null,
                'created_by'          => Auth::id(),
            ]);
        });

        if ($request->boolean('save_and_new')) {
            return redirect()->route('production.trackings.create', ['order_id' => $order->id])
                ->with('success', "Suivi {$tracking->number} enregistré — nouveau suivi.");
        }

        return redirect()->route('production.orders.show', $order)
            ->with('success', "Suivi {$tracking->number} enregistré sur l'OF {$order->number}.");
    }
}
