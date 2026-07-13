<?php

/**
 * [STO-12] Pertes & casses — valorisation au PMP + sortie de stock à la validation.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLoss;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockLossService;

uses(\Tests\Concerns\RefreshDatabase::class);

function lossSetup(): array
{
    $co = Company::firstOrCreate(['name' => 'LOSS Co'], ['email' => 'loss@iboa.test']);
    $product = Product::factory()->create(['weighted_avg_cost' => 2000, 'is_stockable' => true]);
    $wh = Warehouse::create(['company_id' => $co->id, 'name' => 'Central', 'code' => 'CEN', 'type' => 'depot', 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0]);

    $loss = StockLoss::create([
        'company_id' => $co->id, 'product_id' => $product->id, 'warehouse_id' => $wh->id,
        'quantity' => 10, 'type' => 'casse', 'cause' => 'Chute palette', 'status' => 'declaree',
    ]);

    return [$loss, $product, $wh];
}

it('estime la valeur de la perte au PMP', function () {
    [$loss] = lossSetup();
    expect(app(StockLossService::class)->estimateValue($loss))->toBe(20000.0); // 10 × 2000
});

it('valide la perte : sort le stock au PMP et fige la valorisation', function () {
    [$loss, $product, $wh] = lossSetup();
    app(StockLossService::class)->validateLoss($loss, null);

    $loss->refresh();
    expect($loss->status)->toBe('validee');
    expect((float) $loss->estimated_value)->toBe(20000.0);
    expect((float) $loss->unit_cost)->toBe(2000.0);

    // stock décrémenté 100 → 90
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(90.0);
    // mouvement de stock généré
    expect(StockMovement::where('reference_type', 'stock_loss')->where('reference_id', $loss->id)->count())->toBe(1);
});

it('est idempotent : pas de double sortie de stock', function () {
    [$loss, $product, $wh] = lossSetup();
    $svc = app(StockLossService::class);
    $svc->validateLoss($loss, null);
    $svc->validateLoss($loss->fresh(), null);

    expect(StockMovement::where('reference_type', 'stock_loss')->where('reference_id', $loss->id)->count())->toBe(1);
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(90.0);
});
