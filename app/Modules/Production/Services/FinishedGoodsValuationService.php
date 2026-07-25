<?php

namespace App\Modules\Production\Services;

use App\Models\AccountingPeriodLock;
use App\Models\FiscalYear;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockValuationAdjustment;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinishedGoodsValuationService
{
    public function revalue(ProductionOrder $order): void
    {
        $order->loadMissing(['cost', 'outputs.stockMovement', 'product']);
        $totalCost = (float) ($order->cost?->total_cost ?? 0);
        $totalQuantity = (float) $order->outputs->sum('quantity');

        if ($totalCost <= 0 || $totalQuantity <= 0) {
            return;
        }
        if (FiscalYear::find($order->fiscal_year_id)?->status !== 'ouvert'
            || AccountingPeriodLock::findForDate((int) $order->company_id, now())) {
            throw ValidationException::withMessages([
                'valuation' => 'Régularisation impossible : la période comptable est fermée ou verrouillée.',
            ]);
        }

        DB::transaction(function () use ($order, $totalCost, $totalQuantity) {
            foreach ($order->outputs as $output) {
                $quantity = (float) $output->quantity;
                $original = $output->stockMovement;
                if ($quantity <= 0 || ! $original) {
                    continue;
                }
                if (StockValuationAdjustment::where('production_order_id', $order->id)
                    ->where('original_movement_id', $original->id)->exists()) {
                    continue;
                }

                $allocatedCost = round($totalCost * ($quantity / $totalQuantity), 2);
                $newUnitCost = round($allocatedCost / $quantity, 2);
                $delta = round($allocatedCost - (float) $original->total_cost, 2);
                if (abs($delta) <= 0.001) {
                    continue;
                }

                $warehouseId = $output->quality_released_at && $output->release_warehouse_id
                    ? (int) $output->release_warehouse_id
                    : (int) $output->warehouse_id;
                $stock = ProductStock::where('product_id', $output->product_id)
                    ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

                if (! $stock || (float) $stock->quantity + 0.001 < $quantity) {
                    throw ValidationException::withMessages([
                        'valuation' => 'Régularisation impossible : le produit fini a déjà été partiellement livré ou consommé.',
                    ]);
                }

                $stockValue = (float) $stock->quantity * (float) $stock->avg_cost;
                $newAverage = round(($stockValue + $delta) / (float) $stock->quantity, 2);
                $stock->update(['avg_cost' => $newAverage]);

                $adjustmentMovement = StockMovement::create([
                    'product_id' => $output->product_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'valuation_adjustment',
                    'quantity' => 0,
                    'unit_cost' => round($delta / $quantity, 2),
                    'total_cost' => $delta,
                    'valuation_method' => $order->product?->valuation_method ?? 'cmp',
                    'avg_cost_after' => $newAverage,
                    'occurred_at' => now(),
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $order->id,
                    'idempotency_key' => 'production-valuation-adjustment:'.$order->id.':'.$output->id,
                    'notes' => 'Régularisation du coût provisoire vers le coût complet de l’OF '.$order->number,
                    'created_by' => Auth::id(),
                ]);

                StockValuationAdjustment::create([
                    'company_id' => $order->company_id,
                    'production_order_id' => $order->id,
                    'original_movement_id' => $original->id,
                    'adjustment_movement_id' => $adjustmentMovement->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'old_unit_cost' => (float) $original->unit_cost,
                    'new_unit_cost' => $newUnitCost,
                    'value_delta' => $delta,
                    'reason' => 'Passage du coût provisoire au coût complet à la clôture de l’OF',
                    'created_by' => Auth::id(),
                ]);
            }

            $weighted = ProductStock::where('product_id', $order->product_id)
                ->where('quantity', '>', 0)
                ->selectRaw('SUM(quantity * avg_cost) / NULLIF(SUM(quantity), 0) AS value')
                ->value('value');
            if ($weighted !== null) {
                $order->product?->updateQuietly(['weighted_avg_cost' => round((float) $weighted, 2)]);
            }
        });
    }
}
