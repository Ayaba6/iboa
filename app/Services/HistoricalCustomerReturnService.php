<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteItemLotReturn;
use App\Models\DeliveryNoteItemLotAllocation;
use App\Models\StockLot;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

class HistoricalCustomerReturnService
{
    public function __construct(private StockService $stock) {}

    public function returnItem(CreditNote $creditNote, CreditNoteItem $item, int $defaultWarehouseId): void
    {
        if (($item->disposition ?? 'restock') === 'no_return') {
            return;
        }

        $remaining = (float) $item->quantity;
        $allocations = DeliveryNoteItemLotAllocation::query()
            ->select('delivery_note_item_lot_allocations.*')
            ->join('invoice_items', 'invoice_items.delivery_note_item_id', '=', 'delivery_note_item_lot_allocations.delivery_note_item_id')
            ->where('invoice_items.invoice_id', $creditNote->invoice_id)
            ->where('invoice_items.product_id', $item->product_id)
            ->whereNull('delivery_note_item_lot_allocations.reversed_at')
            ->orderBy('delivery_note_item_lot_allocations.allocated_at')
            ->orderBy('delivery_note_item_lot_allocations.id')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0.0001) {
                break;
            }
            $alreadyReturned = (float) CreditNoteItemLotReturn::query()
                ->join('credit_note_items', 'credit_note_items.id', '=', 'credit_note_item_lot_returns.credit_note_item_id')
                ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
                ->where('credit_note_item_lot_returns.delivery_allocation_id', $allocation->id)
                ->whereNotIn('credit_notes.status', ['annule'])
                ->sum('credit_note_item_lot_returns.quantity');
            $available = max(0, (float) $allocation->quantity - $alreadyReturned);
            if ($available <= 0.0001) {
                continue;
            }

            $quantity = min($remaining, $available);
            [$warehouseId, $returnedLot] = $this->returnDestination($creditNote, $item, $allocation, $defaultWarehouseId);
            $movement = $this->stock->recordMovement([
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'type' => 'retour_client',
                'quantity' => $quantity,
                'unit_cost' => (float) $allocation->unit_cost_snapshot,
                'stock_lot_id' => $returnedLot->id,
                'lot_number' => $returnedLot->lot_number,
                'occurred_at' => now(),
                'reference_type' => 'credit_note',
                'reference_id' => $creditNote->id,
                'idempotency_key' => "credit-note-return:{$creditNote->id}:{$item->id}:{$allocation->id}",
                'notes' => "Retour historique avoir {$creditNote->number}",
            ]);

            CreditNoteItemLotReturn::create([
                'credit_note_item_id' => $item->id,
                'delivery_allocation_id' => $allocation->id,
                'source_stock_lot_id' => $allocation->stock_lot_id,
                'returned_stock_lot_id' => $returnedLot->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'unit_cost_snapshot' => (float) $allocation->unit_cost_snapshot,
                'total_cost' => round($quantity * (float) $allocation->unit_cost_snapshot, 2),
                'stock_movement_id' => $movement->id,
            ]);
            $remaining -= $quantity;
        }

        if ($remaining > 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => "Retour supérieur à la quantité historiquement livrée pour {$item->description}.",
            ]);
        }
    }

    private function returnDestination(CreditNote $creditNote, CreditNoteItem $item, DeliveryNoteItemLotAllocation $allocation, int $defaultWarehouseId): array
    {
        $sourceLot = StockLot::lockForUpdate()->findOrFail($allocation->stock_lot_id);
        if (($item->disposition ?? 'restock') !== 'quarantaine') {
            return [(int) ($allocation->warehouse_id ?: $defaultWarehouseId), $sourceLot];
        }

        $warehouseId = (int) (Warehouse::where('company_id', $creditNote->company_id)
            ->where(fn ($query) => $query->where('type', 'quarantaine')->orWhere('code', 'like', '%QUAR%'))
            ->value('id') ?? throw new \RuntimeException('Aucun dépôt de quarantaine configuré.'));
        $lot = StockLot::firstOrCreate([
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouseId,
            'lot_number' => $sourceLot->lot_number.'-RET-Q',
        ], [
            'quantity' => 0,
            'initial_quantity' => 0,
            'unit_cost' => (float) $allocation->unit_cost_snapshot,
            'status' => 'disponible',
            'valuation_status' => 'valorisation_definitive',
            'source_type' => CreditNote::class,
            'source_id' => $creditNote->id,
        ]);

        return [$warehouseId, $lot];
    }
}
