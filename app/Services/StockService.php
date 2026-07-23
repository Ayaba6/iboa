<?php

namespace App\Services;

use App\Events\StockAlertTriggered;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Repositories\StockMovementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        private StockMovementRepository $movementRepository,
    ) {}

    /**
     * Get paginated stock levels for all products across warehouses.
     */
    public function getStockSummary(array $filters = []): LengthAwarePaginator
    {
        $query = ProductStock::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true));

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->whereHas('product', fn($q) =>
                $q->where('name', 'like', $s)->orWhere('reference', 'like', $s)
            );
        }

        if (!empty($filters['low_stock'])) {
            $query->whereHas('product', function ($q) {
                $q->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= products.stock_min');
            });
        }

        return $query->orderBy('product_id')->paginate(20)->withQueryString();
    }

    /**
     * Record a manual stock movement and update ProductStock accordingly.
     *
     * Handles:
     *  - CMP (Coût Moyen Pondéré) recalculation for inbound movements
     *  - FIFO / LIFO lot consumption for outbound movements (per product setting)
     *  - Lot/serial/expiry tracking via StockLot
     *  - Transfert: decrement source warehouse, increment destination
     *  - Kit BOM: auto-consume components when kit is moved out
     *
     * @throws \Illuminate\Validation\ValidationException when available stock is insufficient
     */
    public function recordMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            // [Sync coils/lots] Idempotence : la même clé métier ne peut produire
            // qu'un seul mouvement — un double clic / retry renvoie l'existant.
            if (! empty($data['idempotency_key'])) {
                $existing = StockMovement::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $data['created_by'] = Auth::id();

            // Normalise field names (form sends movement_type / movement_date)
            // [FIX-BUG-02] Default to null when neither key is set, instead of triggering
            // "Undefined array key" warnings on movement_type-only or type-only payloads.
            $data['type']        = $data['movement_type'] ?? $data['type']        ?? null;
            $data['occurred_at'] = $data['movement_date']  ?? $data['occurred_at'] ?? now();
            $data['unit_cost']   = (float) ($data['unit_cost'] ?? 0);
            $data['quantity']    = (float) ($data['quantity']  ?? 0);

            // [Sync coils/lots — unité de tenue de stock] La saisie opérationnelle
            // (quantity/uom, ex. mètres linéaires) est conservée telle quelle ;
            // la quantité qui meut product_stocks et le lot est
            // quantity_in_stock_uom (KG pour les matières en bobines).
            // Sans conversion fournie, comportement historique : quantity.
            $stockQty = isset($data['quantity_in_stock_uom'])
                ? (float) $data['quantity_in_stock_uom']
                : $data['quantity'];
            $data['total_cost']  = $stockQty * $data['unit_cost'];
            unset($data['movement_type'], $data['movement_date']);

            // Guard: product_id and warehouse_id are mandatory
            if (empty($data['product_id'])) {
                throw new \InvalidArgumentException('Mouvement de stock : product_id est requis.');
            }
            if (empty($data['warehouse_id'])) {
                throw new \InvalidArgumentException('Mouvement de stock : warehouse_id est requis.');
            }

            // [CDC §8.5] Bloque tout mouvement sur un dépôt en cours d'inventaire —
            // sinon le comptage physique et le stock théorique divergent pendant la
            // session. InventoryService::validate() n'appelle jamais recordMovement()
            // (il manipule ProductStock directement), donc la clôture n'est pas affectée.
            $hasOpenInventory = InventorySession::where('warehouse_id', $data['warehouse_id'])
                ->where('status', 'en_cours')
                ->exists();
            if ($hasOpenInventory) {
                throw new \RuntimeException(
                    "Mouvement bloqué : un inventaire est en cours sur ce dépôt. Validez ou annulez la session avant tout mouvement de stock."
                );
            }

            $type     = $data['type'];
            // Delta appliqué au stock agrégé et aux lots : l'unité de tenue
            // de stock (quantity_in_stock_uom si fournie, sinon quantity).
            $qty      = $stockQty;
            $unitCost = $data['unit_cost'];

            // ---------------------------------------------------------------
            // 1. Resolve the product's valuation method (cmp / fifo / lifo)
            // ---------------------------------------------------------------
            $product          = Product::find($data['product_id']);
            $valuationMethod  = $product?->valuation_method ?? 'cmp';

            // ---------------------------------------------------------------
            // 2. Get / create source ProductStock
            // [ARCH-C3] Use DB-level lock to prevent race conditions on concurrent movements.
            // ---------------------------------------------------------------
            $stock = ProductStock::firstOrCreate(
                [
                    'product_id'   => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                ],
                [
                    'quantity'          => 0,
                    'reserved_quantity' => 0,
                    'avg_cost'          => 0,
                ]
            );
            // Re-fetch with lock to prevent TOCTOU race condition
            $stock = ProductStock::lockForUpdate()->find($stock->id);

            // ---------------------------------------------------------------
            // 3. Apply stock delta + valuation
            // ---------------------------------------------------------------
            $inboundTypes  = ['entree', 'retour_client'];
            $outboundTypes = ['sortie', 'retour_fournisseur'];

            if ($type === 'transfert') {
                // -- Validate available quantity on source
                $this->assertSufficientStock($stock, $qty);

                // Decrement source using FIFO/LIFO lot selection when applicable
                if (in_array($valuationMethod, ['fifo', 'lifo'])) {
                    $lotCost = $this->consumeLotsFifoLifo(
                        (int) $data['product_id'],
                        (int) $data['warehouse_id'],
                        $qty,
                        $valuationMethod
                    );
                    // Use lot-weighted cost for the destination CMP update
                    $unitCost = $lotCost > 0 ? $lotCost : ($stock->avg_cost ?: $unitCost);
                }

                $stock->decrement('quantity', $qty);
                $data['from_warehouse_id'] = $data['warehouse_id'];
                $data['to_warehouse_id']   = $data['dest_warehouse_id'] ?? null;

                // Increment destination using CMP (destination accumulates at whatever cost arrives)
                if (!empty($data['dest_warehouse_id'])) {
                    $destStock = ProductStock::firstOrCreate(
                        [
                            'product_id'   => $data['product_id'],
                            'warehouse_id' => $data['dest_warehouse_id'],
                        ],
                        ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0]
                    );
                    // [ARCH-C3] Lock destination row before applying inbound CMP.
                    $destStock = ProductStock::lockForUpdate()->find($destStock->id);
                    $this->applyInboundCmp($destStock, $qty, $stock->avg_cost ?: $unitCost);
                }

            } elseif ($type === 'ajustement') {
                // qty is signed: positive = add, negative = remove
                if ($qty > 0) {
                    $this->applyInboundCmp($stock, $qty, $unitCost);
                } else {
                    $absQty = abs($qty);
                    $this->assertSufficientStock($stock, $absQty);
                    $stock->decrement('quantity', $absQty);
                }

            } elseif (in_array($type, $inboundTypes)) {
                $this->applyInboundCmp($stock, $qty, $unitCost);

            } elseif (in_array($type, $outboundTypes)) {
                // -- Validate available quantity
                // [PRODUCTION] allow_negative : consommation de composants BOM sur un
                // article autorisant le stock négatif — la sortie n'est pas bloquée.
                if (empty($data['allow_negative'])) {
                    $this->assertSufficientStock($stock, $qty);
                }

                // -- FIFO / LIFO: consume lots and derive weighted unit cost
                if (in_array($valuationMethod, ['fifo', 'lifo'])) {
                    $lotCost = $this->consumeLotsFifoLifo(
                        (int) $data['product_id'],
                        (int) $data['warehouse_id'],
                        $qty,
                        $valuationMethod
                    );
                    if ($lotCost > 0) {
                        $data['unit_cost']  = $lotCost;
                        $data['total_cost'] = $qty * $lotCost;
                    }
                }

                $stock->decrement('quantity', $qty);

            } else {
                // Fallback for any unknown type — just decrement
                $this->assertSufficientStock($stock, $qty);
                $stock->decrement('quantity', $qty);
            }

            $data['avg_cost_after']   = (float) $stock->fresh()->avg_cost;
            $data['valuation_method'] = $valuationMethod;

            $stock->touch('last_movement_at');
            $stock->save();

            // Fire stock alert if available qty dropped below stock_min
            $freshStock = $stock->fresh();
            $stockMin   = (float) ($product?->stock_min ?? 0);
            $available  = (float) $freshStock->quantity - (float) $freshStock->reserved_quantity;
            if ($stockMin > 0 && $available <= $stockMin && in_array($type, ['sortie', 'retour_fournisseur', 'transfert', 'ajustement'])) {
                event(new StockAlertTriggered($freshStock, $available, $stockMin));
            }

            // ---------------------------------------------------------------
            // 4. Remove dest_warehouse_id before creating movement (not a DB column)
            // ---------------------------------------------------------------
            unset($data['dest_warehouse_id'], $data['allow_negative']);

            // ---------------------------------------------------------------
            // 4b. [Sync coils/lots] Delta sur le lot explicitement ciblé.
            // quantity (stock_lots) = quantité RESTANTE, tenue en stock_uom.
            // ---------------------------------------------------------------
            if (!empty($data['stock_lot_id'])) {
                $lot = StockLot::lockForUpdate()->find($data['stock_lot_id']);
                if ($lot) {
                    $delta = match (true) {
                        in_array($type, $inboundTypes)  => $qty,
                        in_array($type, $outboundTypes) => -$qty,
                        $type === 'ajustement'          => $qty, // signé
                        default                         => 0,
                    };
                    $newQty = round((float) $lot->quantity + $delta, 4);
                    if ($newQty < -0.001) {
                        throw new \RuntimeException(
                            "Lot {$lot->lot_number} : quantité restante insuffisante ("
                            . $lot->quantity . ' disponible, ' . abs($delta) . ' demandée).'
                        );
                    }
                    $newQty = max(0, $newQty);
                    // [FIX enum] stock_lots.status = enum(disponible|reserve|expire|consomme).
                    // 'epuise'/'partiellement_consomme' n'existaient pas → MySQL strict
                    // tronquait (« Data truncated ») et cassait la consommation bobine.
                    // Sémantique alignée : lot vidé = consomme ; reliquat = disponible.
                    $lot->update([
                        'quantity' => $newQty,
                        'status'   => $newQty <= 0.001 ? 'consomme' : 'disponible',
                    ]);
                }
            }

            // ---------------------------------------------------------------
            // 5. Create the movement record
            // ---------------------------------------------------------------
            $movement = StockMovement::create($data);

            // ---------------------------------------------------------------
            // 6. Lot / serial tracking (manual lot number provided by user)
            // [Sync coils/lots] Ignoré si un stock_lot_id explicite a déjà été
            // mouvementé ci-dessus (sinon double delta sur le même lot).
            // ---------------------------------------------------------------
            if (!empty($data['lot_number']) && empty($data['stock_lot_id'])) {
                $this->upsertLot($data, $type, $qty, $unitCost);
            }

            // ---------------------------------------------------------------
            // 7. Kit BOM expansion: auto-consume components on outbound
            // ---------------------------------------------------------------
            if (in_array($type, ['sortie', 'retour_fournisseur', 'transfert'])) {
                $this->expandKitComponents($data, $qty);
            }

            return $movement;
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Apply inbound quantity and recalculate CMP (weighted average cost).
     */
    private function applyInboundCmp(ProductStock $stock, float $qty, float $unitCost): void
    {
        $oldQty  = (float) $stock->quantity;
        $oldAvg  = (float) $stock->avg_cost;

        $newQty = $oldQty + $qty;
        if ($newQty > 0 && $unitCost > 0) {
            $newAvg = (($oldQty * $oldAvg) + ($qty * $unitCost)) / $newQty;
            $stock->avg_cost = round($newAvg, 2);
        }
        $stock->quantity = $newQty;
        $stock->save();

        // [CMP-SYNC] Propager le CMP au niveau produit (moyenne pondérée tous dépôts)
        // pour que les rapports (marges) et fallbacks disposent d'un coût fiable.
        $this->syncProductWeightedAvgCost((int) $stock->product_id);
    }

    /**
     * Recalcule product.weighted_avg_cost = CMP pondéré par quantité sur tous les dépôts.
     * Champ dénormalisé utilisé par les rapports de marge et comme fallback de coût.
     */
    private function syncProductWeightedAvgCost(int $productId): void
    {
        $agg = ProductStock::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * avg_cost) AS total_cost, SUM(quantity) AS total_qty')
            ->first();

        if ($agg && (float) $agg->total_qty > 0 && (float) $agg->total_cost > 0) {
            Product::where('id', $productId)->update([
                'weighted_avg_cost' => round((float) $agg->total_cost / (float) $agg->total_qty, 2),
            ]);
        }
    }

    /**
     * Create or update a StockLot record to track lot quantities.
     */
    private function upsertLot(array $data, string $type, float $qty, float $unitCost): void
    {
        $lotData = [
            'product_id'   => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'lot_number'   => $data['lot_number'],
        ];

        $lot = StockLot::firstOrNew($lotData);
        $lot->serial_number = $data['serial_number'] ?? $lot->serial_number;
        $lot->unit_cost     = $unitCost ?: $lot->unit_cost;
        $lot->received_at   = $lot->received_at ?? now()->toDateString();

        if (!empty($data['expiry_date'])) {
            $lot->expiry_date = $data['expiry_date'];
        }

        $inboundTypes = ['entree', 'retour_client'];
        if (in_array($type, $inboundTypes) || ($type === 'ajustement' && $qty > 0)) {
            $lot->quantity = (float) $lot->quantity + $qty;
            $lot->status   = 'disponible';
        } else {
            $newQty = max(0, (float) $lot->quantity - abs($qty));
            $lot->quantity = $newQty;
            if ($newQty <= 0) {
                $lot->status = 'consomme';
            }
        }

        $lot->save();
    }

    /**
     * Assert that the available stock (quantity − reserved) covers the requested qty.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertSufficientStock(ProductStock $stock, float $qty): void
    {
        $available = (float) $stock->quantity - (float) $stock->reserved_quantity;

        if ($qty > $available) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => sprintf(
                    'Stock insuffisant : %s unité(s) disponible(s), %s demandée(s).',
                    number_format($available, 2, ',', ' '),
                    number_format($qty,       2, ',', ' ')
                ),
            ]);
        }
    }

    /**
     * Consume stock lots in FIFO (oldest first) or LIFO (newest first) order
     * and return the weighted average unit cost of the consumed lots.
     *
     * Lot quantities are decremented in-place and their status set to 'consomme'
     * when exhausted. Only lots with status = 'disponible' and quantity > 0 are
     * considered. Lots without a lot_number are skipped (CMP products typically
     * won't have lot records — no harm done).
     *
     * @param  int    $productId
     * @param  int    $warehouseId
     * @param  float  $qty          Total quantity to consume
     * @param  string $method       'fifo' or 'lifo'
     * @return float                Weighted average unit cost (0 if no lots found)
     */
    private function consumeLotsFifoLifo(
        int    $productId,
        int    $warehouseId,
        float  $qty,
        string $method
    ): float {
        $order = $method === 'fifo' ? 'asc' : 'desc';

        /** @var \Illuminate\Database\Eloquent\Collection<int, StockLot> $lots */
        $lots = StockLot::where('product_id',   $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status',       'disponible')
            ->where('quantity',     '>', 0)
            ->orderBy('received_at', $order)
            ->orderBy('id',          $order)   // tiebreaker: stable insertion order
            ->lockForUpdate()
            ->get();

        if ($lots->isEmpty()) {
            return 0.0;
        }

        $remaining     = $qty;
        $totalCost     = 0.0;
        $totalConsumed = 0.0;

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $consume       = min((float) $lot->quantity, $remaining);
            $lotUnitCost   = (float) $lot->unit_cost;

            $totalCost    += $consume * $lotUnitCost;
            $totalConsumed += $consume;
            $remaining    -= $consume;

            $newLotQty   = (float) $lot->quantity - $consume;
            $lot->quantity = $newLotQty;
            $lot->status   = $newLotQty <= 0 ? 'consomme' : $lot->status;
            $lot->save();
        }

        return $totalConsumed > 0 ? round($totalCost / $totalConsumed, 4) : 0.0;
    }

    /**
     * If the product is a composed article (kit), auto-consume components
     * by recording outbound movements for each component.
     */
    private function expandKitComponents(array $data, float $kitQty): void
    {
        /** @var Product $product */
        $product = Product::with('components.component')->find($data['product_id']);

        if (!$product || $product->type !== 'compose' || $product->components->isEmpty()) {
            return;
        }

        foreach ($product->components as $bom) {
            // Skip BOM lines with no component linked (orphaned or misconfigured)
            if (empty($bom->component_product_id)) {
                continue;
            }

            $componentQty     = (float) $bom->quantity * $kitQty;
            $compValuation    = $bom->component?->valuation_method ?? 'cmp';

            $compStock = ProductStock::firstOrCreate(
                [
                    'product_id'   => $bom->component_product_id,
                    'warehouse_id' => $data['warehouse_id'],
                ],
                ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0]
            );

            // Check stock availability before consuming
            $this->assertSufficientStock($compStock, $componentQty);

            // Determine unit cost: use FIFO/LIFO lot cost when applicable
            $compUnitCost = (float) $compStock->avg_cost;
            if (in_array($compValuation, ['fifo', 'lifo'])) {
                $lotCost = $this->consumeLotsFifoLifo(
                    (int) $bom->component_product_id,
                    (int) $data['warehouse_id'],
                    $componentQty,
                    $compValuation
                );
                if ($lotCost > 0) {
                    $compUnitCost = $lotCost;
                }
            }

            $compStock->decrement('quantity', $componentQty);
            $compStock->touch('last_movement_at');

            StockMovement::create([
                'product_id'       => $bom->component_product_id,
                'warehouse_id'     => $data['warehouse_id'],
                'type'             => 'sortie',
                'quantity'         => $componentQty,
                'unit_cost'        => $compUnitCost,
                'total_cost'       => $componentQty * $compUnitCost,
                'valuation_method' => $compValuation,
                'avg_cost_after'   => (float) $compStock->fresh()->avg_cost,
                'occurred_at'      => $data['occurred_at'],
                'reference_type'   => 'kit',
                'reference_id'     => $data['product_id'],
                'notes'            => 'Consommation automatique — kit ' . $product->name,
                'created_by'       => $data['created_by'] ?? null,
            ]);
        }
    }

    /**
     * Paginated movement history with optional filters.
     */
    public function getMovements(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->movementRepository->search($filters, $perPage);
    }
}
