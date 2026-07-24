<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cbcCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'CBC-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'CBC Co'],
        ['email' => 'cbc@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    app()->instance('current_company', $company);

    return $company;
}

function cbcAdmin(): User
{
    $company = cbcCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

function cbcWarehouse(Company $company): Warehouse
{
    return Warehouse::firstOrCreate(
        ['company_id' => $company->id, 'code' => 'WH-CBC'],
        ['name' => 'D?p?t CBC', 'is_default' => true, 'is_active' => true]
    );
}

function cbcSetupBom(): array
{
    $company = cbcCompany();
    cbcAdmin();
    $warehouse = cbcWarehouse($company);
    $mp = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    ProductStock::create([
        'product_id' => $mp->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3000,
        'reserved_quantity' => 0,
        'avg_cost' => 100,
    ]);

    $order = ProductionOrder::create([
        'company_id' => $company->id,
        'number' => 'OF-CBC-' . uniqid(),
        'product_id' => $pf->id,
        'quantity_requested' => 100,
        'status' => 'en_cours',
    ]);

    $bom = BillOfMaterial::create([
        'company_id' => $company->id,
        'product_id' => $pf->id,
        'code' => 'BOM-CBC-' . uniqid(),
        'name' => 'BOM CBC',
        'version' => 'V1',
        'is_active' => true,
    ]);
    $bom->lines()->create([
        'product_id' => $mp->id,
        'quantity_per_meter' => 5,
        'warehouse_id' => $warehouse->id,
    ]);
    $order->update(['bill_of_material_id' => $bom->id]);

    $lot = StockLot::create([
        'product_id' => $mp->id,
        'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-CBC-' . uniqid(),
        'quantity' => 1200,
        'initial_quantity' => 1200,
        'stock_uom' => 'KG',
        'unit_cost' => 100,
        'status' => 'disponible',
    ]);
    $coil = Coil::create([
        'company_id' => $company->id,
        'product_id' => $mp->id,
        'warehouse_id' => $warehouse->id,
        'stock_lot_id' => $lot->id,
        'reference' => 'COIL-CBC-' . uniqid(),
        'initial_weight' => 1200,
        'remaining_weight' => 1200,
        'cost_per_kg' => 100,
        'purchase_price' => 120000,
        'status' => 'disponible',
        'received_at' => now(),
        'created_by' => auth()->id(),
    ]);

    return [$warehouse, $mp, $order->fresh(), $coil];
}

it('backflushes only the remaining uncovered quantity after multiple real consumptions', function () {
    [$warehouse, $mp, $order, $coil] = cbcSetupBom();

    app(CoilConsumptionService::class)->consume($order, $coil, 120);
    app(CoilConsumptionService::class)->consume($order->fresh(), $coil->fresh(), 180);

    app(ProductionStockService::class)
        ->recordOutput($order->fresh(), ['quantity' => 100, 'warehouse_id' => $warehouse->id]);

    $backflush = StockMovement::where('product_id', $mp->id)
        ->where('type', 'sortie')
        ->whereNull('production_consumption_id')
        ->get();

    expect((float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(2500.0)
        ->and(StockMovement::where('product_id', $mp->id)->whereNotNull('production_consumption_id')->count())->toBe(2)
        ->and($backflush)->toHaveCount(1)
        ->and((float) $backflush->first()->quantity_in_stock_uom)->toBe(200.0);
});

it('keeps cumulative backflush quantities exact across multiple outputs of the same order', function () {
    [$warehouse, $mp, $order] = cbcSetupBom();

    app(ProductionStockService::class)
        ->recordOutput($order->fresh(), ['quantity' => 40, 'warehouse_id' => $warehouse->id]);
    app(ProductionStockService::class)
        ->recordOutput($order->fresh(), ['quantity' => 60, 'warehouse_id' => $warehouse->id]);

    $backflush = StockMovement::where('product_id', $mp->id)
        ->where('type', 'sortie')
        ->whereNull('production_consumption_id')
        ->orderBy('id')
        ->get();

    expect($backflush)->toHaveCount(2)
        ->and((float) $backflush[0]->quantity_in_stock_uom)->toBe(200.0)
        ->and((float) $backflush[1]->quantity_in_stock_uom)->toBe(300.0)
        ->and((float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(2500.0);
});
