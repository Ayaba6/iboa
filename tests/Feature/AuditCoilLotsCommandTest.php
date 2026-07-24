<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function aclCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'ACL-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'ACL Co'],
        ['email' => 'acl@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    app()->instance('current_company', $company);

    return $company;
}

function aclAdmin(): User
{
    $company = aclCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

function aclWarehouse(Company $company): Warehouse
{
    return Warehouse::firstOrCreate(
        ['company_id' => $company->id, 'code' => 'WH-ACL'],
        ['name' => 'D?p?t audit bobines', 'is_default' => true, 'is_active' => true]
    );
}

function aclCoilProduct(Company $company): Product
{
    $category = ItemCategory::create([
        'company_id' => $company->id,
        'code' => 'ACL-BOB-' . uniqid(),
        'name' => 'MP bobine audit',
        'nature' => 'matiere_premiere',
        'strategy' => 'mto',
        'is_active' => true,
        'is_stockable' => true,
        'is_purchasable' => true,
        'usable_in_bom' => true,
        'coil_managed' => true,
        'lot_managed' => true,
    ]);

    return Product::factory()->create([
        'item_category_id' => $category->id,
        'is_stockable' => true,
        'has_lot_number' => true,
        'valuation_method' => 'cmp',
    ]);
}

function aclSeedDiscrepancy(Product $product, Warehouse $warehouse, User $user, float $physical = 8.0, float $stock = 5.0): void
{
    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $stock,
        'reserved_quantity' => 0,
        'avg_cost' => 100,
    ]);

    $lot = StockLot::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-ACL-' . uniqid(),
        'quantity' => $physical,
        'initial_quantity' => $physical,
        'stock_uom' => 'KG',
        'unit_cost' => 100,
        'status' => 'disponible',
    ]);

    Coil::create([
        'company_id' => aclCompany()->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'stock_lot_id' => $lot->id,
        'reference' => 'COIL-ACL-' . uniqid(),
        'initial_weight' => $physical,
        'remaining_weight' => $physical,
        'cost_per_kg' => 100,
        'purchase_price' => (int) round($physical * 100),
        'status' => 'disponible',
        'received_at' => now(),
        'created_by' => $user->id,
    ]);

    StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'entree',
        'quantity' => $stock,
        'quantity_in_stock_uom' => $stock,
        'stock_uom' => 'KG',
        'unit_cost' => 100,
        'total_cost' => $stock * 100,
        'occurred_at' => now(),
        'reference_type' => 'seed',
        'reference_id' => 1,
        'created_by' => $user->id,
    ]);
}

it('reports discrepancies in dry run without modifying stock', function () {
    $company = aclCompany();
    $user = aclAdmin();
    $warehouse = aclWarehouse($company);
    $product = aclCoilProduct($company);
    aclSeedDiscrepancy($product, $warehouse, $user);

    $report = storage_path('framework/testing/audit-coil-dry-run.json');
    @unlink($report);

    $this->artisan('stock:audit-coil-lots', ['--report' => $report])
        ->assertExitCode(1);

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(5.0)
        ->and(StockMovement::where('idempotency_key', 'like', 'opening-reconciliation:%')->count())->toBe(0)
        ->and(file_exists($report))->toBeTrue();

    $json = json_decode(file_get_contents($report), true, flags: JSON_THROW_ON_ERROR);
    expect($json['before']['summary']['anomalies'])->toBe(1)
        ->and($json['after'])->toBeNull();
});

it('applies a reconciliation once per run id and is replay safe', function () {
    $company = aclCompany();
    $user = aclAdmin();
    $warehouse = aclWarehouse($company);
    $product = aclCoilProduct($company);
    aclSeedDiscrepancy($product, $warehouse, $user);

    $report = storage_path('framework/testing/audit-coil-fix.json');
    @unlink($report);

    $this->artisan('stock:audit-coil-lots', [
        '--fix' => true,
        '--run-id' => 'RUN-ACL-1',
        '--report' => $report,
    ])->assertSuccessful();

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(8.0)
        ->and(StockMovement::where('idempotency_key', 'opening-reconciliation:RUN-ACL-1:' . $product->id . ':' . $warehouse->id)->count())->toBe(1);

    $this->artisan('stock:audit-coil-lots', [
        '--fix' => true,
        '--run-id' => 'RUN-ACL-1',
        '--report' => $report,
    ])->assertSuccessful();

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(8.0)
        ->and(StockMovement::where('idempotency_key', 'opening-reconciliation:RUN-ACL-1:' . $product->id . ':' . $warehouse->id)->count())->toBe(1);

    $json = json_decode(file_get_contents($report), true, flags: JSON_THROW_ON_ERROR);
    expect($json['meta']['run_id'])->toBe('RUN-ACL-1')
        ->and(count($json['applied']))->toBe(0)
        ->and($json['after']['summary']['anomalies'])->toBe(0);
});

it('can revert a reconciliation run through explicit reversal movements', function () {
    $company = aclCompany();
    $user = aclAdmin();
    $warehouse = aclWarehouse($company);
    $product = aclCoilProduct($company);
    aclSeedDiscrepancy($product, $warehouse, $user);

    $this->artisan('stock:audit-coil-lots', [
        '--fix' => true,
        '--run-id' => 'RUN-ACL-REV',
    ])->assertSuccessful();

    $this->artisan('stock:audit-coil-lots', [
        '--revert-run' => 'RUN-ACL-REV',
    ])->assertSuccessful();

    $original = StockMovement::where('idempotency_key', 'opening-reconciliation:RUN-ACL-REV:' . $product->id . ':' . $warehouse->id)->firstOrFail();
    $reversal = StockMovement::where('idempotency_key', 'opening-reconciliation-reversal:RUN-ACL-REV:' . $original->id)->first();

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(5.0)
        ->and($reversal)->not->toBeNull()
        ->and((int) $reversal->reversal_of_movement_id)->toBe($original->id);
});
