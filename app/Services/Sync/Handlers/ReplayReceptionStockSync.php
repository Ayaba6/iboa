<?php

namespace App\Services\Sync\Handlers;

use App\Models\Product;
use App\Models\Reception;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Services\UnitConversionService;
use Illuminate\Validation\ValidationException;

/**
 * [Sync ERP] Réception fournisseur → entrées stock.
 *
 * IDEMPOTENT : ne crée jamais deux mouvements pour la même ligne — une entrée
 * existante (reference_type=reception + reference_id + product + lot) est sautée.
 * Utilisé en flux nominal (validation réception) ET en relance après échec.
 *
 * [MULTI-UNITÉS] La quantité reçue est exprimée en unité d'ACHAT ; elle est
 * convertie en unité de STOCK via products.ua_to_us_coef avant l'entrée (et le
 * coût unitaire est ajusté pour conserver la valeur totale). Coef 1 = inchangé.
 */
class ReplayReceptionStockSync
{
    public function __construct(
        private StockService $stockService,
        private UnitConversionService $units,
    ) {}

    /** @return array{0:int,1:int} [mouvements créés, lignes sans article sautées] */
    public function __invoke(Reception $reception, array $payload = []): array
    {
        $warehouseId = $reception->warehouse_id ?? ($payload['warehouse_id'] ?? null);
        if (! $warehouseId) {
            throw new \RuntimeException("Réception {$reception->number} : entrepôt de destination manquant.");
        }

        $created = 0;
        $skipped = 0;

        foreach ($reception->items as $item) {
            $qty = (float) $item->received_quantity;
            if ($qty <= 0) {
                continue;
            }
            if (empty($item->product_id)) {
                $skipped++; // ligne libre (description seule) — pas de stock

                continue;
            }

            // Idempotence : mouvement déjà créé pour cette ligne ?
            $exists = StockMovement::where('reference_type', 'reception')
                ->where('reference_id', $reception->id)
                ->where('product_id', $item->product_id)
                ->when($item->lot_number, fn ($q) => $q->where('lot_number', $item->lot_number))
                ->exists();
            if ($exists) {
                continue;
            }

            // [MULTI-UNITÉS] Qté reçue (unité d'achat) → unité de stock, coût ajusté.
            $product = Product::find($item->product_id);
            if ($product?->isCoilManaged() && (float) $item->unit_cost <= 0) {
                throw ValidationException::withMessages([
                    'cost' => "Réception {$reception->number} : la matière bobine « {$product->name} » doit être valorisée avant entrée en stock.",
                ]);
            }
            $stockQty = $product ? $this->units->toStockQuantity($product, $qty, 'achat') : $qty;
            $stockCost = $product
                ? $this->units->toStockUnitCost($product, (float) $item->unit_cost, 'achat')
                : (float) $item->unit_cost;

            $this->stockService->recordMovement([
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'type' => 'entree',
                'quantity' => $stockQty,
                'unit_cost' => $stockCost,
                'occurred_at' => $reception->received_at?->toDateString() ?? now()->toDateString(),
                'reference_type' => 'reception',
                'reference_id' => $reception->id,
                'lot_number' => $item->lot_number,
                'expiry_date' => $item->expiry_date,
                'notes' => 'Réception '.$reception->number,
            ]);
            $created++;
        }

        return [$created, $skipped];
    }
}
