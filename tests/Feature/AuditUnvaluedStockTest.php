<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\Warehouse;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('échoue si un lot physique non valorisé subsiste et ce lot est exclu des lots disponibles', function () {
    $product = Product::factory()->create(['is_stockable' => true]);
    $company = Company::factory()->create();
    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Audit', 'code' => 'AUD-VAL']);
    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'SANS-VALEUR', 'quantity' => 5, 'unit_cost' => 0,
        'status' => 'disponible', 'valuation_status' => 'valorisation_manquante',
    ]);

    $this->artisan('a3:audit-unvalued-stock')->assertFailed();

    expect(StockLot::disponible()->whereKey($lot->id)->exists())->toBeFalse();
});
