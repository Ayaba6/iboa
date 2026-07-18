<?php

namespace App\Services\Sync\Handlers;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Services\StockService;

/**
 * [Sync ERP] Déclaration de production → entrée stock produit fini.
 * IDEMPOTENT : un output déjà lié à un mouvement (stock_movement_id) est sauté.
 */
class ReplayProductionOutputStockSync
{
    public function __construct(private StockService $stock)
    {
    }

    public function __invoke(ProductionOutput $output, array $payload = []): void
    {
        if ($output->stock_movement_id) {
            return; // entrée déjà créée
        }
        if (!$output->product_id || !$output->warehouse_id) {
            throw new \RuntimeException("Déclaration #{$output->id} : produit ou entrepôt manquant, entrée stock impossible.");
        }

        $order = $output->productionOrder ?? ProductionOrder::find($output->production_order_id);
        $uom   = $output->product?->unit?->abbreviation;

        // [COMPTA-STOCK] Coût unitaire non saisi à la déclaration → valorisation
        // au coût standard du produit (CMP puis prix d'achat). Sans cela l'entrée
        // PF vaut 0, le CMP n'est pas alimenté et l'écriture 361/736 de clôture
        // n'a pas de base cohérente avec la sortie de stock à la vente.
        $unitCost = (float) ($payload['unit_cost'] ?? 0);
        if ($unitCost <= 0) {
            $unitCost = (float) ($output->product?->weighted_avg_cost ?: 0)
                ?: (float) ($output->product?->purchase_price ?? 0);
        }

        $movement = $this->stock->recordMovement([
            'product_id'           => $output->product_id,
            'warehouse_id'         => $output->warehouse_id,
            'type'                 => 'entree',
            'quantity'             => (float) $output->quantity,
            'uom'                  => $uom,
            'quantity_in_stock_uom' => (float) $output->quantity,
            'stock_uom'            => $uom,
            'unit_cost'            => $unitCost,
            'production_order_id'  => $output->production_order_id,
            'idempotency_key'      => 'production-output:' . $output->id,
            'reference_type'       => ProductionOrder::class,
            'reference_id'         => $output->production_order_id,
            'notes'                => 'Production OF ' . ($order?->number ?? $output->production_order_id),
        ]);

        $output->update(['stock_movement_id' => $movement->id]);
    }
}
