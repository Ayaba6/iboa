<?php

namespace App\Services;

use App\Models\Product;

class SalesPriceGuardService
{
    public function effectiveFloor(Product $product): float
    {
        return $this->explain($product)['minimum_price'];
    }

    /**
     * Le taux configuré est un taux de marge sur coût : prix = coût × (1 + taux/100).
     * Le coefficient UV→US convertit une unité de vente en unités de stock.
     *
     * @return array{article_type:string,cost_base:float,cost_source:string,conversion_factor:float,margin_rate:float,economic_floor:float,configured_floor:float,minimum_price:float}
     */
    public function explain(Product $product): array
    {
        [$articleType, $candidates] = $this->costCandidates($product);
        $positive = collect($candidates)->filter(fn ($value) => (float) $value > 0);
        $costSource = $positive->sortDesc()->keys()->first() ?? 'aucun_cout_connu';
        $costPerStockUnit = (float) ($positive->max() ?? 0);

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
            'minimum_price' => round(max($configuredFloor, $economicFloor), 2),
        ];
    }

    /** @return array{0:string,1:array<string, float|int|string|null>} */
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

        $service = ! $product->is_stockable || $product->type === 'service' || $product->type_article === 'service';
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
