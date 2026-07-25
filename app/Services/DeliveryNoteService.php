<?php

namespace App\Services;

use App\Events\DeliveryNoteValidated;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Modules\Production\Services\ReservationService;
use App\Repositories\DeliveryNoteRepository;
use App\Services\Sync\Handlers\ReplayDeliveryStockSync;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    public function __construct(
        public readonly DeliveryNoteRepository $repository,
        private DocumentSequenceService $sequenceService,
        private StockService $stockService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    /**
     * Create a delivery note from all items of an order.
     */
    public function createFromOrder(Order $order): DeliveryNote
    {
        return DB::transaction(function () use ($order) {
            $company = currentCompany();

            $dn = DeliveryNote::create([
                'company_id' => $company->id,
                'client_id' => $order->client_id,
                'order_id' => $order->id,
                'number' => $this->sequenceService->nextNumber($company, 'bon_livraison'),
                'issued_at' => now()->toDateString(),
                'status' => 'brouillon',
                // Dépôt de livraison explicite si défini, sinon le dépôt qui détient
                // réellement le stock du premier produit livrable (produit fini de l'OF).
                'warehouse_id' => $order->delivery_warehouse_id
                                       ?? $this->resolveOrderStockWarehouse($order),
                'delivery_address' => $order->delivery_address,
                'currency_code' => $order->currency_code,
                'created_by' => Auth::id(),
            ]);

            $totalQty = 0;
            foreach ($order->items as $i => $item) {
                // [FIX rapport MTO #4] Quantité proposée = reliquat non livré,
                // plafonné au stock réellement disponible pour cette commande au
                // dépôt du BL (dispo général + réservations propres de la commande).
                // Évite de proposer 50 quand 40 seulement sont en stock (survente
                // si l'utilisateur valide sans corriger). La ligne reste éditable.
                $remaining = max(0, (float) $item->quantity - (float) $item->delivered_quantity);
                $qty = $remaining;

                if ($item->product_id && $dn->warehouse_id) {
                    $stock = ProductStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $dn->warehouse_id)->first();
                    if ($stock) {
                        $ownReserved = (float) StockReservation::where('order_id', $order->id)
                            ->where('product_id', $item->product_id)
                            ->where('warehouse_id', $dn->warehouse_id)
                            ->where('status', 'reserved')->sum('quantity');
                        $available = max(0, (float) $stock->quantity - (float) $stock->reserved_quantity) + $ownReserved;
                        if ($available > 0 && $available < $remaining) {
                            $qty = round($available, 4);
                        }
                    }
                }

                $dn->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'unit_id' => $item->unit_id,
                    'quantity' => $qty,
                    // [§5 TÔLE BAC] nb tôles / longueur unitaire hérités de la commande
                    'nb_toles' => $item->nb_toles,
                    'metrage_par_tole' => $item->metrage_par_tole,
                    'unit_price' => $item->unit_price,
                    'sort_order' => $i,
                ]);
                $totalQty += $qty;
            }

            $dn->update(['total_quantity' => $totalQty]);

            return $dn;
        });
    }

    /**
     * Validate a delivery note: status -> valide, create stock-out movements,
     * update delivered_quantity on order items, and advance the order status.
     */
    public function validate(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être validés.');
        }

        // [VENTE↔PRODUCTION] Blocages livraison pour commandes fabriquées (QC + qté produite).
        app(ProductionDeliveryGuard::class)->assertDeliverable($dn);

        return DB::transaction(function () use ($dn) {
            $dn->update([
                'status' => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $this->applyStockOut($dn);

            // [Sync ERP] event domaine apres commit — point d'extension decouple
            DB::afterCommit(fn () => event(new DeliveryNoteValidated($dn)));

            return $dn->fresh();
        });
    }

    /**
     * Applique les mouvements de sortie de stock pour un BL validé.
     * Méthode publique appelée à la fois par validate() et par CommercialWorkflowService::validateDeliveryNote()
     * pour le circuit interne (brouillon → en_attente_validation → valide).
     *
     * [Sync ERP] journalisée + idempotente + relançable via sync_logs.
     */
    public function applyStockOut(DeliveryNote $dn): void
    {
        app(SyncOrchestrator::class)->run(
            sourceModule: 'ventes',
            targetModule: 'stock',
            eventName: 'delivery_note.validated',
            action: 'create_stock_exits',
            source: $dn,
            callback: fn () => $this->applyStockOutInner($dn),
            handlerClass: ReplayDeliveryStockSync::class,
        );
    }

    /** Logique brute des sorties stock — appelée par le flux nominal ET la relance. */
    public function applyStockOutInner(DeliveryNote $dn): void
    {
        $dn->load('items.product', 'order.items');

        $defaultWarehouseId = $dn->warehouse_id
            ?? Warehouse::where('is_default', true)->value('id')
            ?? Warehouse::value('id');

        // [Sync ERP] Idempotence par comptage : nb de sorties déjà créées pour ce BL
        // et ce produit vs nb de lignes — gère les relances ET les doubles appels
        // internes (validate + workflow) sans jamais doubler une sortie.
        $existingByProduct = StockMovement::where('reference_type', 'delivery_note')
            ->where('reference_id', $dn->id)
            ->selectRaw('product_id, COUNT(*) as nb')
            ->groupBy('product_id')
            ->pluck('nb', 'product_id');
        $seenByProduct = [];

        foreach ($dn->items as $item) {
            if (! $item->product_id || ! ($item->product?->is_stockable ?? true)) {
                continue;
            }

            $seenByProduct[$item->product_id] = ($seenByProduct[$item->product_id] ?? 0) + 1;
            if (($existingByProduct[$item->product_id] ?? 0) >= $seenByProduct[$item->product_id]) {
                continue; // sortie déjà enregistrée pour cette ligne
            }

            $deliveredQty = abs((float) $item->quantity);

            // [FIX livraison dépôt] Le dépôt du BL peut être vide (BL créé depuis une
            // commande sans dépôt de livraison) : on cible alors le dépôt qui détient
            // réellement le stock du produit fini — là où l'OF l'a entré — plutôt que
            // le dépôt par défaut, qui n'aurait aucune ligne (« 0 disponible »).
            $warehouseId = $dn->warehouse_id
                ?? $this->resolveStockWarehouse($item->product_id)
                ?? $defaultWarehouseId;

            // [FIX réservation] Consommer la réservation de la commande AVANT la sortie.
            // Sinon la propre réservation de la commande (reserved = qté commandée)
            // ramène le disponible à 0 (qté − réservé) et bloque sa propre livraison.
            // On FERME la ligne stock_reservations (status=released) en plus de
            // décrémenter product_stocks.reserved_quantity : les deux couches doivent
            // rester synchrones, sinon un recompute rederive une réservation fantôme.
            $this->consumeReservations($dn, (int) $item->product_id, (int) $warehouseId, $deliveredQty);

            $hasFormalLots = $item->stock_lot_id || StockLot::where('product_id', $item->product_id)
                ->where('warehouse_id', $warehouseId)->where('quantity', '>', 0)->exists();

            if ($hasFormalLots) {
                // Une sortie distincte par lot fige le coût historique réellement livré.
                $allocations = app(DeliveryLotAllocationService::class)->allocate($item, (int) $warehouseId);
                foreach ($allocations as $allocation) {
                    $lot = $allocation->stockLot()->firstOrFail();
                    $movement = $this->stockService->recordMovement([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'sortie',
                        'quantity' => (float) $allocation->quantity,
                        'unit_cost' => (float) $allocation->unit_cost_snapshot,
                        'occurred_at' => now(),
                        'reference_type' => 'delivery_note',
                        'reference_id' => $dn->id,
                        'stock_lot_id' => $lot->id,
                        'lot_number' => $lot->lot_number,
                        'idempotency_key' => 'delivery-note:'.$dn->id.':'.$item->id.':lot:'.$lot->id,
                        'notes' => 'BL '.$dn->number.' — lot '.$lot->lot_number,
                    ]);
                    $allocation->update(['stock_movement_id' => $movement->id]);
                }
            } else {
                $avgCost = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)->value('avg_cost') ?? 0;
                $this->stockService->recordMovement([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'sortie',
                    'quantity' => $deliveredQty,
                    'unit_cost' => (float) $avgCost,
                    'occurred_at' => now(),
                    'reference_type' => 'delivery_note',
                    'reference_id' => $dn->id,
                    'idempotency_key' => 'delivery-note:'.$dn->id.':'.$item->id,
                    'notes' => 'BL '.$dn->number,
                ]);
            }
            // Update delivered_quantity on the linked order item
            if ($item->order_item_id) {
                $orderItem = OrderItem::find($item->order_item_id);
                if ($orderItem) {
                    $orderItem->increment('delivered_quantity', (float) $item->quantity);
                }
            }
        }

        // Advance order status based on delivery progress
        $this->syncOrderDeliveryStatus($dn->order);
    }

    /**
     * Consomme la/les réservation(s) de la commande pour un produit livré.
     *
     * Ferme les lignes stock_reservations (status=released) via ReservationService,
     * ce qui décrémente aussi product_stocks.reserved_quantity — garde les deux
     * couches cohérentes. Sans cette fermeture, un recompute de reserved_quantity
     * depuis les lignes encore « reserved » ressuscite une réservation fantôme qui
     * masque le stock fraîchement produit pour les commandes suivantes.
     *
     * Repli : si aucune ligne stock_reservations n'existe pour la commande (BL
     * historiques), on décrémente directement reserved_quantity au dépôt livré.
     */
    private function consumeReservations(DeliveryNote $dn, int $productId, int $warehouseId, float $qty): void
    {
        $rows = StockReservation::where('order_id', $dn->order_id)
            ->where('product_id', $productId)
            ->where('status', 'reserved')
            ->orderBy('id')
            ->get();

        if ($rows->isNotEmpty()) {
            $reservationService = app(ReservationService::class);
            $remaining = $qty;
            foreach ($rows as $r) {
                if ($remaining <= 0) {
                    break;
                }

                // [FIX désynchro dépôt] release() décrémente reserved_quantity AU dépôt
                // stocké sur la réservation. Si ce dépôt diverge du dépôt réellement livré
                // (ex. stock PF déplacé par une fusion/transfert de dépôts sans repointer
                // la réservation), release() décrémenterait le mauvais dépôt et laisserait
                // le réservé du dépôt livré intact → sa propre livraison se bloquerait sur
                // « 0 disponible ». On réaligne la réservation sur le dépôt livré quand
                // c'est bien lui qui détient le stock réservé.
                if ((int) $r->warehouse_id !== $warehouseId) {
                    $hasReservedHere = ProductStock::where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('reserved_quantity', '>', 0)
                        ->exists();
                    if ($hasReservedHere) {
                        $r->update(['warehouse_id' => $warehouseId]);
                    }
                }

                $reservationService->release($r);   // ferme la ligne + décrémente reserved_quantity
                $remaining -= (float) $r->quantity;
            }

            return;
        }

        // Repli : réservation non tracée en ligne — décrément direct au dépôt livré.
        $remaining = $qty;
        ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('reserved_quantity', '>', 0)
            ->orderBy('id')
            ->each(function ($stockRow) use (&$remaining) {
                if ($remaining <= 0) {
                    return false;
                }
                $release = min($remaining, (float) $stockRow->reserved_quantity);
                $stockRow->decrement('reserved_quantity', $release);
                $remaining -= $release;
            });
    }

    /**
     * Dépôt détenant le stock d'un produit (quantité ou réservation la plus élevée).
     * Sert de repli quand le BL n'a pas de dépôt explicite : on livre depuis là où
     * le stock existe réellement plutôt que depuis le dépôt par défaut.
     */
    private function resolveStockWarehouse(int $productId): ?int
    {
        return ProductStock::where('product_id', $productId)
            ->whereRaw('(quantity + reserved_quantity) > 0')
            ->orderByRaw('(quantity + reserved_quantity) DESC')
            ->value('warehouse_id');
    }

    /** Dépôt de repli pour un BL : premier produit de la commande ayant du stock. */
    private function resolveOrderStockWarehouse(Order $order): ?int
    {
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            if ($wid = $this->resolveStockWarehouse($item->product_id)) {
                return $wid;
            }
        }

        return null;
    }

    /**
     * Create an invoice directly from a validated delivery note.
     * [FIX-CRITIQUE] Guard against double-invoicing the same BL.
     */
    public function createInvoice(DeliveryNote $dn): Invoice
    {
        if ($dn->status !== 'valide') {
            throw new \RuntimeException('Le bon de livraison doit être validé avant de générer une facture.');
        }

        // Prevent double-invoicing
        if (Invoice::where('delivery_note_id', $dn->id)->exists()) {
            throw new \RuntimeException('Une facture a déjà été générée pour ce bon de livraison ('.$dn->number.').');
        }

        return app(InvoiceService::class)->createFromDeliveryNote($dn);
    }

    /** brouillon → annule */
    public function cancel(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être annulés.');
        }
        $dn->update(['status' => 'annule']);

        return $dn->fresh();
    }

    /**
     * [FIX-CRITIQUE] Annuler un BL déjà validé : reverse stock movements,
     * decrement delivered_quantity on order items, and re-sync order status.
     */
    public function cancelValidated(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'valide') {
            throw new \RuntimeException('Seuls les bons de livraison validés peuvent être annulés via cette procédure.');
        }

        // Cannot cancel if an invoice already exists
        if (Invoice::where('delivery_note_id', $dn->id)->whereNotIn('status', ['annulee'])->exists()) {
            throw new \RuntimeException('Impossible d\'annuler : une facture non-annulée est liée à ce bon de livraison.');
        }

        return DB::transaction(function () use ($dn) {
            $dn->load('items.product', 'order');

            $warehouseId = $dn->warehouse_id
                ?? Warehouse::where('is_default', true)->value('id')
                ?? Warehouse::value('id');

            foreach ($dn->items as $item) {
                if (! $item->product_id || ! ($item->product?->is_stockable ?? true)) {
                    continue;
                }

                $reversedQty = abs((float) $item->quantity);
                $allocations = $item->lotAllocations()->whereNull('reversed_at')->with('stockLot')->get();
                foreach ($allocations as $allocation) {
                    $this->stockService->recordMovement([
                        'product_id' => $item->product_id,
                        'warehouse_id' => (int) $allocation->warehouse_id,
                        'type' => 'entree',
                        'quantity' => (float) $allocation->quantity,
                        'unit_cost' => (float) $allocation->unit_cost_snapshot,
                        'occurred_at' => now(),
                        'reference_type' => 'delivery_note',
                        'reference_id' => $dn->id,
                        'stock_lot_id' => $allocation->stock_lot_id,
                        'lot_number' => $allocation->stockLot->lot_number,
                        'idempotency_key' => 'delivery-note-reversal:'.$dn->id.':'.$item->id.':lot:'.$allocation->stock_lot_id,
                        'notes' => 'Annulation BL '.$dn->number.' — lot '.$allocation->stockLot->lot_number,
                    ]);
                }
                app(DeliveryLotAllocationService::class)->reverse($item);
                if ($allocations->isEmpty()) {
                    $outbound = StockMovement::where('idempotency_key', 'delivery-note:'.$dn->id.':'.$item->id)->first();
                    $this->stockService->recordMovement([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'entree',
                        'quantity' => $reversedQty,
                        'unit_cost' => (float) ($outbound?->unit_cost ?? 0),
                        'occurred_at' => now(),
                        'reference_type' => 'delivery_note',
                        'reference_id' => $dn->id,
                        'idempotency_key' => 'delivery-note-reversal:'.$dn->id.':'.$item->id,
                        'notes' => 'Annulation BL '.$dn->number,
                    ]);
                }
                // [FIX-VENTES-07] Re-establish the reservation only when the parent order
                // is still active (confirmed / in-preparation / partially delivered).
                // If the order is cancelled or already fully invoiced there is no one
                // waiting for the stock, so adding a phantom reservation would block sales.
                $orderStatus = $dn->order?->status;
                if ($orderStatus && in_array($orderStatus, ['confirme', 'en_preparation', 'partiellement_livre'])) {
                    ProductStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->increment('reserved_quantity', $reversedQty);
                }

                // Decrement delivered_quantity (and invoiced_quantity if applicable) on the linked order item
                if ($item->order_item_id) {
                    $orderItem = OrderItem::find($item->order_item_id);
                    if ($orderItem) {
                        $orderItem->decrement('delivered_quantity', (float) $item->quantity);

                        // [FIX-QTE-02] Also decrement invoiced_quantity if items had been invoiced
                        if ((float) $orderItem->invoiced_quantity > 0) {
                            $decrement = min((float) $item->quantity, (float) $orderItem->invoiced_quantity);
                            $orderItem->decrement('invoiced_quantity', $decrement);
                        }
                    }
                }
            }

            $dn->update(['status' => 'annule']);

            // Re-sync order delivery status
            $this->syncOrderDeliveryStatus($dn->order);

            return $dn->fresh();
        });
    }

    /**
     * Sync the parent order status based on all its delivery notes' progress.
     */
    private function syncOrderDeliveryStatus(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->load('items', 'deliveryNotes');

        // Count validated delivery notes
        $validatedBls = $order->deliveryNotes->where('status', 'valide')->count();
        if ($validatedBls === 0) {
            return;
        }

        // Check if all order items are fully delivered
        $allDelivered = $order->items->every(function ($item) {
            return (float) $item->delivered_quantity >= (float) $item->quantity;
        });

        $newStatus = $allDelivered ? 'livre' : 'partiellement_livre';

        if (! in_array($order->status, ['facture', 'annule'])) {
            $order->update(['status' => $newStatus]);
        }
    }

    /**
     * Generate PDF view path for the delivery note.
     */
    public function generatePdfPath(DeliveryNote $dn): string
    {
        return 'ventes.pdf.delivery-note';
    }

    public function delete(DeliveryNote $dn): bool
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être supprimés.');
        }

        return $dn->delete();
    }
}
