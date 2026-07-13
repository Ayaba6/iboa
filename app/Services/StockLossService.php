<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLoss;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * [STO-12] Valorisation et validation des pertes/casses de stock.
 * La validation génère une sortie de stock (ajustement négatif au PMP).
 */
class StockLossService
{
    public function __construct(private StockService $stock) {}

    /** Valeur estimée = quantité × PMP courant de l'article. */
    public function estimateValue(StockLoss $loss): float
    {
        $pmp = (float) (Product::find($loss->product_id)?->weighted_avg_cost ?? 0);

        return round((float) $loss->quantity * $pmp, 2);
    }

    /**
     * Valide la perte : sort le stock (ajustement négatif au PMP) et fige la valorisation.
     * Idempotent : ne génère qu'un seul mouvement par perte.
     */
    public function validateLoss(StockLoss $loss, ?int $validatorId): void
    {
        if ($loss->status === 'validee') {
            return;
        }

        DB::transaction(function () use ($loss, $validatorId) {
            $pmp = (float) (Product::find($loss->product_id)?->weighted_avg_cost ?? 0);
            $qty = abs((float) $loss->quantity);

            $exists = StockMovement::where('reference_type', 'stock_loss')
                ->where('reference_id', $loss->id)->exists();

            if (! $exists) {
                // Ajustement signé négatif → sortie de stock valorisée au PMP.
                $this->stock->recordMovement([
                    'product_id'     => $loss->product_id,
                    'warehouse_id'   => $loss->warehouse_id,
                    'type'           => 'ajustement',
                    'quantity'       => -$qty,
                    'unit_cost'      => $pmp,
                    'occurred_at'    => now(),
                    'reference_type' => 'stock_loss',
                    'reference_id'   => $loss->id,
                    'lot_number'     => $loss->lot_number,
                    'notes'          => 'Perte/casse '.$loss->typeLabel().' — '.($loss->reference ?: '#'.$loss->id),
                ]);
            }

            $loss->update([
                'unit_cost'       => $pmp,
                'estimated_value' => round($qty * $pmp, 2),
                'status'          => 'validee',
                'validated_by'    => $validatorId,
                'validated_at'    => now()->toDateString(),
            ]);
        });
    }

    public function reject(StockLoss $loss, ?string $reason): void
    {
        $loss->update(['status' => 'rejetee', 'reject_reason' => $reason]);
    }
}
