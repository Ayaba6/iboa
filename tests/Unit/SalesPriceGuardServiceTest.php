<?php

use App\Models\Product;
use App\Services\SalesPriceGuardService;

it('formalise le prix minimum par type article et taux de marge', function (array $attributes, float $expected, string $source) {
    $product = new Product(array_merge([
        'is_stockable' => true,
        'uv_to_us_coef' => 1,
        'min_sale_price' => 0,
        'margin_rate_target' => 20,
    ], $attributes));

    $result = app(SalesPriceGuardService::class)->explain($product);

    expect($result['minimum_price'])->toBe($expected)
        ->and($result['cost_source'])->toBe($source);
})->with([
    'fabriqué : coût standard prioritaire sur CMP inférieur' => [[
        'is_manufacturable' => true, 'cout_standard' => 100, 'weighted_avg_cost' => 90,
        'last_purchase_price' => 500,
    ], 120.0, 'cout_standard'],
    'marchandise : dernier coût pertinent le plus prudent' => [[
        'type' => 'simple', 'weighted_avg_cost' => 80, 'last_purchase_price' => 100, 'purchase_price' => 90,
    ], 120.0, 'dernier_prix_achat'],
    'service : coût standard uniquement' => [[
        'type' => 'service', 'is_stockable' => false, 'cout_standard' => 100,
        'purchase_price' => 500,
    ], 120.0, 'cout_standard_service'],
    'conversion : deux unités de stock par unité vendue' => [[
        'type' => 'simple', 'purchase_price' => 100, 'uv_to_us_coef' => 2,
    ], 240.0, 'prix_achat'],
    'plancher configuré supérieur au calcul économique' => [[
        'type' => 'simple', 'purchase_price' => 100, 'min_sale_price' => 150,
    ], 150.0, 'prix_achat'],
]);
