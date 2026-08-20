<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Sales\SalesPriceFloorService;

/**
 * Application du prix plancher aux décisions commerciales.
 *
 * Le CALCUL vit désormais dans `SalesPriceFloorService`, source unique partagée
 * avec `SalesPricingService` — voir [BUG-A3-SALES-PRICE-API-028], où deux
 * définitions divergentes du plancher se contredisaient à l'écran et à la
 * validation.
 *
 * Cette classe reste le point d'entrée des appelants existants : elle n'a plus
 * de règle propre, seulement la délégation.
 */
class SalesPriceGuardService
{
    public function __construct(private SalesPriceFloorService $floor) {}

    public function effectiveFloor(Product $product): float
    {
        return $this->floor->minimumPrice($product);
    }

    /**
     * @return array{article_type:string,cost_base:float,cost_source:string,conversion_factor:float,margin_rate:float,economic_floor:float,configured_floor:float,minimum_price:float}
     */
    public function explain(Product $product): array
    {
        return $this->floor->explain($product);
    }
}
