<?php

namespace App\Services;

use App\Models\DeliveryNoteItem;
use App\Models\DeliveryNoteItemLotAllocation;
use App\Models\StockLot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DeliveryLotAllocationService
{
    /** @return Collection<int, DeliveryNoteItemLotAllocation> */
    public function allocate(DeliveryNoteItem $item, int $warehouseId): Collection
    {
        $required = abs((float) $item->quantity);
        if ($item->stock_lot_id) {
            $selected = StockLot::lockForUpdate()->find($item->stock_lot_id);
            if (! $selected || (int) $selected->product_id !== (int) $item->product_id
                || (int) $selected->warehouse_id !== $warehouseId) {
                throw new \RuntimeException('Le lot sélectionné n’existe pas ou ne correspond pas à l’article et au dépôt.');
            }
        }
        $existing = $item->lotAllocations()->whereNull('reversed_at')->lockForUpdate()->get();

        if ($existing->isNotEmpty()) {
            $this->assertAllocatedQuantity($item, $existing, $required);

            return $existing;
        }

        $lots = StockLot::query()
            ->with('warehouse')
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->where('status', 'disponible')
            ->when($item->stock_lot_id, fn ($query) => $query->whereKey($item->stock_lot_id))
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $required;
        $allocations = collect();
        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }
            if ($lot->warehouse?->type === 'quarantaine') {
                continue;
            }
            if ((float) $lot->unit_cost <= 0) {
                throw new \RuntimeException("Lot {$lot->lot_number} sans valorisation : livraison bloquée.");
            }

            $quantity = min($remaining, (float) $lot->quantity);
            $allocation = DeliveryNoteItemLotAllocation::create([
                'delivery_note_item_id' => $item->id,
                'stock_lot_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'unit_cost_snapshot' => (float) $lot->unit_cost,
                'total_cost' => round($quantity * (float) $lot->unit_cost, 2),
                'allocated_by' => Auth::id(),
                'allocated_at' => now(),
            ]);

            $allocations->push($allocation);
            $remaining -= $quantity;
        }

        if ($remaining > 0.0001) {
            if ($item->stock_lot_id) {
                throw new \RuntimeException('Lot sélectionné : quantité insuffisante.');
            }
            throw new \RuntimeException(sprintf(
                'Lots insuffisants pour « %s » : %s restant à allouer.',
                $item->product?->name ?? '#'.$item->product_id,
                number_format($remaining, 4, ',', ' ')
            ));
        }

        if ($allocations->count() === 1) {
            $lot = $allocations->first()->stockLot()->first();
            $item->updateQuietly(['stock_lot_id' => $lot->id, 'lot_number' => $lot->lot_number]);
        } else {
            $item->updateQuietly(['stock_lot_id' => null, 'lot_number' => 'MULTI-LOTS']);
        }

        return $allocations;
    }

    public function reverse(DeliveryNoteItem $item): void
    {
        $allocations = $item->lotAllocations()->whereNull('reversed_at')->with('stockLot')->lockForUpdate()->get();
        foreach ($allocations as $allocation) {
            $allocation->update(['reversed_at' => now(), 'reversed_by' => Auth::id()]);
        }
    }

    private function assertAllocatedQuantity(DeliveryNoteItem $item, Collection $allocations, float $required): void
    {
        $allocated = (float) $allocations->sum('quantity');
        if (abs($allocated - $required) > 0.0001) {
            throw new \RuntimeException("Allocation de lots incohérente pour la ligne {$item->id}.");
        }
    }
}
