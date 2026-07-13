<?php

/**
 * [PRO-05] Remplacement contrôlé — le substitut BOM est remonté à l'allocation
 * quand le composant principal est en rupture.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;

uses(\Tests\Concerns\RefreshDatabase::class);

it('remonte la disponibilité du substitut pour un composant en rupture', function () {
    $co = Company::firstOrCreate(['name' => 'ALLOC Co'], ['email' => 'alloc@iboa.test']);
    $wh = Warehouse::create(['company_id' => $co->id, 'name' => 'C', 'code' => 'C', 'type' => 'depot', 'is_active' => true]);

    $pf   = Product::factory()->create(['name' => 'PF']);
    $main = Product::factory()->create(['name' => 'Composant principal', 'allow_negative_stock' => false]);
    $sub  = Product::factory()->create(['name' => 'Composant substitut', 'allow_negative_stock' => false]);

    // Principal en rupture (5), substitut suffisant (100)
    ProductStock::create(['product_id' => $main->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0]);
    ProductStock::create(['product_id' => $sub->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0]);

    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'code' => 'NOM', 'name' => 'Nomenclature',
        'quantite_base' => 1, 'statut' => 'exploitation', 'is_active' => true,
    ]);
    $bom->lines()->create([
        'product_id' => $main->id, 'substitute_product_id' => $sub->id,
        'quantity_per_meter' => 1, 'sort_order' => 1,
    ]);

    $order = ProductionOrder::factory()->create([
        'company_id' => $co->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 10,
    ]);

    $shortages = app(ProductionService::class)->materialShortages($order);

    expect($shortages)->toHaveCount(1);
    $s = $shortages[0];
    expect($s['product'])->toBe('Composant principal');
    expect($s['need'])->toBe(10.0);
    expect($s['available'])->toBe(5.0);
    // substitut remonté et couvrant le besoin
    expect($s['substitute'])->toBe('Composant substitut');
    expect($s['substitute_available'])->toBe(100.0);
    expect($s['substitute_covers'])->toBeTrue();
});
