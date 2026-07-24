<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Reception;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\CoilReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cmrCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'CMR-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'CMR Co'],
        ['email' => 'cmr@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    app()->instance('current_company', $company);

    return $company;
}

function cmrAdmin(): User
{
    $company = cmrCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

function cmrWarehouse(Company $company): Warehouse
{
    return Warehouse::firstOrCreate(
        ['company_id' => $company->id, 'code' => 'WH-CMR'],
        ['name' => 'D?p?t CMR', 'is_default' => true, 'is_active' => true]
    );
}

it('identifies a coil managed article via the explicit category flag', function () {
    $company = cmrCompany();
    cmrAdmin();

    $category = ItemCategory::create([
        'company_id' => $company->id,
        'code' => 'CMR-BOB-' . uniqid(),
        'name' => 'Bobine explicite',
        'nature' => 'matiere_premiere',
        'strategy' => 'mto',
        'is_active' => true,
        'is_stockable' => true,
        'is_purchasable' => true,
        'coil_managed' => true,
        'lot_managed' => true,
    ]);

    $product = Product::factory()->create([
        'item_category_id' => $category->id,
        'has_lot_number' => true,
        'is_stockable' => true,
    ]);

    expect($product->isCoilManaged())->toBeTrue();
});

it('does not infer coil management from lot tracking alone', function () {
    cmrCompany();
    cmrAdmin();

    $product = Product::factory()->create([
        'has_lot_number' => true,
        'is_stockable' => true,
    ]);

    expect($product->isCoilManaged())->toBeFalse();
});

it('exposes legacy coil history separately from the explicit management flag', function () {
    $company = cmrCompany();
    $user = cmrAdmin();
    $warehouse = cmrWarehouse($company);

    $product = Product::factory()->create([
        'has_lot_number' => true,
        'is_stockable' => true,
    ]);
    $lot = StockLot::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-CMR-' . uniqid(),
        'quantity' => 100,
        'initial_quantity' => 100,
        'stock_uom' => 'KG',
        'unit_cost' => 100,
        'status' => 'disponible',
    ]);
    Coil::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'stock_lot_id' => $lot->id,
        'reference' => 'COIL-CMR-' . uniqid(),
        'initial_weight' => 100,
        'remaining_weight' => 100,
        'cost_per_kg' => 100,
        'purchase_price' => 10000,
        'status' => 'disponible',
        'received_at' => now(),
        'created_by' => $user->id,
    ]);

    expect($product->fresh()->isCoilManaged())->toBeFalse()
        ->and($product->fresh()->hasLegacyCoilHistory())->toBeTrue();
});

it('does not auto create coils on tracked receptions for a lot managed only article', function () {
    $company = cmrCompany();
    cmrAdmin();
    $warehouse = cmrWarehouse($company);

    $product = Product::factory()->create([
        'has_lot_number' => true,
        'is_stockable' => true,
    ]);

    $reception = Reception::create([
        'company_id' => $company->id,
        'number' => 'REC-CMR-001',
        'status' => 'valide',
        'warehouse_id' => $warehouse->id,
        'received_at' => now(),
        'validated_at' => now(),
    ]);
    $reception->items()->create([
        'product_id' => $product->id,
        'description' => 'Article lot uniquement',
        'expected_quantity' => 50,
        'received_quantity' => 50,
        'unit_cost' => 100,
        'lot_number' => 'LOT-CMR-REC',
    ]);

    $coils = app(CoilReceptionService::class)->createFromReception($reception->fresh(), true);

    expect($coils)->toHaveCount(0)
        ->and(Coil::where('reception_id', $reception->id)->count())->toBe(0);
});

it('blocks toggling coil managed on a category already used with physical history', function () {
    $company = cmrCompany();
    $user = cmrAdmin();
    $warehouse = cmrWarehouse($company);

    $category = ItemCategory::create([
        'company_id' => $company->id,
        'code' => 'CMR-LOCK-' . uniqid(),
        'name' => 'Cat?gorie bobine verrouill?e',
        'nature' => 'matiere_premiere',
        'strategy' => 'mto',
        'is_active' => true,
        'is_stockable' => true,
        'is_purchasable' => true,
        'coil_managed' => true,
        'lot_managed' => true,
    ]);
    $product = Product::factory()->create([
        'item_category_id' => $category->id,
        'has_lot_number' => true,
        'is_stockable' => true,
    ]);
    $lot = StockLot::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-CMR-LOCK',
        'quantity' => 20,
        'initial_quantity' => 20,
        'stock_uom' => 'KG',
        'unit_cost' => 100,
        'status' => 'disponible',
    ]);
    Coil::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'stock_lot_id' => $lot->id,
        'reference' => 'COIL-CMR-LOCK',
        'initial_weight' => 20,
        'remaining_weight' => 20,
        'cost_per_kg' => 100,
        'purchase_price' => 2000,
        'status' => 'disponible',
        'received_at' => now(),
        'created_by' => $user->id,
    ]);

    expect(fn () => $category->assertCoilManagementToggleAllowed(false))
        ->toThrow(RuntimeException::class);
});

it('allows toggling coil managed on an unused category', function () {
    $company = cmrCompany();
    cmrAdmin();

    $category = ItemCategory::create([
        'company_id' => $company->id,
        'code' => 'CMR-FREE-' . uniqid(),
        'name' => 'Cat?gorie libre',
        'nature' => 'matiere_premiere',
        'strategy' => 'mto',
        'is_active' => true,
        'is_stockable' => true,
        'is_purchasable' => true,
        'coil_managed' => false,
    ]);

    $category->assertCoilManagementToggleAllowed(true);
    $category->update(['coil_managed' => true]);

    expect($category->fresh()->coil_managed)->toBeTrue();
});
