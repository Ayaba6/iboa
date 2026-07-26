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

        $quarantineWarehouseId = null; // résolu à la demande

        foreach ($reception->items as $item) {
            if (empty($item->product_id)) {
                $skipped++; // ligne libre (description seule) — pas de stock

                continue;
            }

            // [Réceptions #8] Routage par disposition : l'ACCEPTÉ entre au dépôt
            // utilisable ; la QUARANTAINE entre au DÉPÔT QUAR (jamais disponible) ;
            // le REFUSÉ n'entre PAS en stock. (Rétro-compat : accepted = received
            // quand aucune ventilation n'a été saisie.)
            // [#4] Le replay NE DÉCIDE PAS de l'acceptation : il lit une disposition
            // déjà déterminée. Une ligne à disposition INCONNUE (accepted ET
            // quarantine NULL — historique non classé, jamais ventilé) ne crée
            // AUCUN stock vendable et est signalée comme non rejouable.
            if ($item->accepted_quantity === null && $item->quarantine_quantity === null) {
                $skipped++;

                continue;
            }
            $accepted   = (float) ($item->accepted_quantity ?? 0);
            $quarantine = (float) ($item->quarantine_quantity ?? 0);

            $product = Product::find($item->product_id);
            if ($product?->isCoilManaged() && (float) $item->unit_cost <= 0 && ($accepted + $quarantine) > 0) {
                throw ValidationException::withMessages([
                    'cost' => "Réception {$reception->number} : la matière bobine « {$product->name} » doit être valorisée avant entrée en stock.",
                ]);
            }

            // Entrée de l'accepté au dépôt utilisable.
            $created += $this->enter($reception, $item, $product, $accepted, $warehouseId, 'Réception '.$reception->number);

            // Entrée de la quarantaine au dépôt de quarantaine.
            if ($quarantine > 0) {
                $quarantineWarehouseId ??= app(\App\Services\QuarantineService::class)
                    ->quarantineWarehouse((int) $reception->company_id)->id;
                $created += $this->enter($reception, $item, $product, $quarantine, $quarantineWarehouseId, 'Réception '.$reception->number.' — quarantaine');
            }
        }

        return [$created, $skipped];
    }

    /**
     * Entre une quantité (unité d'achat → stock) dans un entrepôt donné, de façon
     * idempotente par (réception, produit, lot, entrepôt).
     */
    private function enter(Reception $reception, $item, ?Product $product, float $qty, int $warehouseId, string $notes): int
    {
        if ($qty <= 0) {
            return 0;
        }
        $exists = StockMovement::where('reference_type', 'reception')
            ->where('reference_id', $reception->id)
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouseId)
            ->when($item->lot_number, fn ($q) => $q->where('lot_number', $item->lot_number))
            ->exists();
        if ($exists) {
            return 0;
        }

        $stockQty  = $product ? $this->units->toStockQuantity($product, $qty, 'achat') : $qty;
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
            'notes' => $notes,
        ]);

        return 1;
    }
}
