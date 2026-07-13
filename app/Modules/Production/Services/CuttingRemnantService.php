<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Modules\Production\Models\CuttingOptimization;
use App\Services\StockService;

/**
 * [PRO-08] Ré-entrée en stock des chutes réutilisables d'une découpe, à la clôture.
 *
 * Décisions de cadrage :
 *  - Article  : matière source (bobine) → coil.product_id, sinon product_id.
 *  - Dépôt    : dépôt dédié « Chutes réutilisables » (créé si absent).
 *  - Coût     : PMP courant de la matière (pas de profit fictif SYSCOHADA).
 */
class CuttingRemnantService
{
    public function __construct(private StockService $stock) {}

    /**
     * Génère le mouvement d'entrée de la chute réutilisable. Idempotent :
     * ne recrée pas le mouvement si la découpe en a déjà un.
     */
    public function reenter(CuttingOptimization $opt): ?StockMovement
    {
        if (! $opt->valorize_offcuts || (float) $opt->reusable_offcut_m <= 0) {
            return null;
        }

        $existing = StockMovement::where('reference_type', 'cutting_optimization')
            ->where('reference_id', $opt->id)->where('type', 'entree')->first();
        if ($existing) {
            return $existing; // déjà ré-entré
        }

        $productId = $opt->coil?->product_id ?? $opt->product_id;
        if (! $productId) {
            return null; // pas de matière identifiable → rien à ré-entrer
        }

        $product  = Product::find($productId);
        $unitCost = (float) ($product?->weighted_avg_cost ?? 0);
        $warehouse = $this->dedicatedWarehouse((int) $opt->company_id);

        return $this->stock->recordMovement([
            'product_id'     => $productId,
            'warehouse_id'   => $warehouse->id,
            'type'           => 'entree',
            'quantity'       => (float) $opt->reusable_offcut_m,
            'unit_cost'      => $unitCost,
            'occurred_at'    => now(),
            'reference_type' => 'cutting_optimization',
            'reference_id'   => $opt->id,
            'lot_number'     => 'CHUTE-'.($opt->code ?: $opt->id),
            'notes'          => 'Chute réutilisable ré-entrée à la clôture de la découpe '.($opt->code ?: '#'.$opt->id),
        ]);
    }

    /** Dépôt dédié aux chutes réutilisables (créé une fois par société). */
    public function dedicatedWarehouse(int $companyId): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'CHUTES'],
            ['name' => 'Chutes réutilisables', 'type' => 'chutes', 'is_active' => true, 'is_default' => false],
        );
    }
}
