<?php

namespace App\Services\Sales;

use App\Models\Product;

/**
 * Source UNIQUE du prix minimum applicable à un article.
 *
 * Deux définitions du plancher coexistaient, et elles ne donnaient pas le même
 * résultat :
 *
 *   `SalesPricingService`     lisait `min_sale_price` en direct ;
 *   `SalesPriceGuardService`  calculait `max(plancher configuré, plancher
 *                             économique)`, ce dernier dérivé du coût.
 *
 * Sur un article dont le coût vaut 6 000 F et dont `min_sale_price` est nul,
 * l'écran annonçait donc « 0 F, aucune validation requise » là où la soumission
 * refusait à 6 000 F — [BUG-A3-SALES-PRICE-API-028]. La règle la plus
 * permissive était celle que voyait l'utilisateur.
 *
 * Ce service porte le calcul, et lui seul. `SalesPriceGuardService` l'applique,
 * `SalesPricingService` l'annonce : deux usages, une définition.
 *
 * La hiérarchie des coûts est reprise À L'IDENTIQUE de l'implémentation
 * précédente — ce lot unifie, il ne change pas la règle métier.
 */
class SalesPriceFloorService
{
    /**
     * @return array{article_type:string, cost_base:float, cost_source:string,
     *               conversion_factor:float, margin_rate:float, economic_floor:float,
     *               configured_floor:float, minimum_price:float}
     */
    public function explain(Product $product): array
    {
        [$articleType, $candidates] = $this->costCandidates($product);

        $positive = collect($candidates)->filter(fn ($value) => (float) $value > 0);
        $costSource = $positive->sortDesc()->keys()->first() ?? 'aucun_cout_connu';
        $costPerStockUnit = (float) ($positive->max() ?? 0);

        // Coefficient unité de vente → unité de stock : un article géré au kilo
        // peut se vendre à la barre, et le plancher doit suivre cette conversion.
        $saleToStock = (float) ($product->uv_to_us_coef ?? 1);
        if ($saleToStock <= 0) {
            $saleToStock = 1;
        }

        $convertedCost = $costPerStockUnit * $saleToStock;
        $marginRate = max(0, (float) ($product->margin_rate_target ?? 0));
        $economicFloor = $convertedCost * (1 + ($marginRate / 100));
        $configuredFloor = max(0, (float) ($product->min_sale_price ?? 0));

        return [
            'article_type' => $articleType,
            'cost_base' => round($costPerStockUnit, 2),
            'cost_source' => $costSource,
            'conversion_factor' => $saleToStock,
            'margin_rate' => $marginRate,
            'economic_floor' => round($economicFloor, 2),
            'configured_floor' => $configuredFloor,
            // Le plus contraignant des deux gagne : un plancher configuré au-dessus
            // du coût est une décision commerciale, un coût au-dessus du plancher
            // configuré est une réalité économique. Aucun des deux ne s'efface.
            'minimum_price' => round(max($configuredFloor, $economicFloor), 2),
        ];
    }

    /** Raccourci : le seul chiffre dont la plupart des appelants ont besoin. */
    public function minimumPrice(Product $product): float
    {
        return $this->explain($product)['minimum_price'];
    }

    /**
     * Sources de coût retenues selon la nature de l'article.
     *
     * @return array{0:string, 1:array<string, float|int|string|null>}
     */
    private function costCandidates(Product $product): array
    {
        $manufactured = (bool) $product->is_manufacturable
            || in_array($product->production_mode, ['mto', 'mts'], true)
            || $product->type_article === 'produit_fini';

        if ($manufactured) {
            return ['fabrique', [
                'cout_standard' => $product->cout_standard,
                'cmp_produit_fini' => $product->weighted_avg_cost,
            ]];
        }

        $service = ! $product->is_stockable
            || $product->type === 'service'
            || $product->type_article === 'service';

        if ($service) {
            return ['service', ['cout_standard_service' => $product->cout_standard]];
        }

        return ['marchandise', [
            'cmp_marchandise' => $product->weighted_avg_cost,
            'dernier_prix_achat' => $product->last_purchase_price,
            'prix_achat' => $product->purchase_price,
        ]];
    }
}
