<?php

/**
 * [MULTI-UNITÉS SAGE] La réception convertit la quantité reçue (unité d'achat)
 * vers l'unité de stock via products.ua_to_us_coef, et ajuste le coût unitaire
 * pour conserver la valeur totale. Exemple bobine : achat KG, stock MTL.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sync\Handlers\ReplayReceptionStockSync;
use App\Services\UnitConversionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ucCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'UC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    return Company::firstOrCreate(['name' => 'UC Co'], ['email' => 'uc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function ucAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => ucCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

it('convertit quantité et coût du service (achat → stock)', function () {
    $svc = app(UnitConversionService::class);
    $product = Product::factory()->make(['ua_to_us_coef' => 0.571102, 'uv_to_us_coef' => 1]);

    // 100 KG × 0,571102 = 57,1102 MTL
    expect($svc->toStockQuantity($product, 100, 'achat'))->toEqual(57.1102);
    // coût 1000/KG → 1000 / 0,571102 = 1751,00 /MTL (= 1,751 KG/MTL × 1000, valeur conservée)
    expect($svc->toStockUnitCost($product, 1000, 'achat'))->toEqual(1751.0);
    // vente coef 1 → inchangé
    expect($svc->toStockQuantity($product, 42, 'vente'))->toEqual(42.0);
});

it('coef nul / non renseigné = aucune conversion (mono-unité inchangé)', function () {
    $svc = app(UnitConversionService::class);
    $p = Product::factory()->make(['ua_to_us_coef' => null, 'uv_to_us_coef' => 0]);
    expect($svc->toStockQuantity($p, 80, 'achat'))->toEqual(80.0);
    expect($svc->toStockUnitCost($p, 500, 'achat'))->toEqual(500.0);
});

it('réception 100 KG d’une bobine (coef 0,571102) → 57,1102 MTL en stock', function () {
    $this->actingAs(ucAdmin());
    $co = ucCompany();
    $wh = Warehouse::firstOrCreate(['code' => 'UC-WH'], ['name' => 'UC WH', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $supplier = Supplier::factory()->create();

    // Bobine : achat KG, stock MTL, coef 0,571102.
    $coil = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'ua_to_us_coef' => 0.571102, 'uv_to_us_coef' => 1]);

    $reception = Reception::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id,
        'number' => 'REC-UC-001', 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'received_at' => now(),
    ]);
    ReceptionItem::create([
        'reception_id' => $reception->id, 'product_id' => $coil->id,
        'description' => 'Bobine prélaquée 25/100', 'received_quantity' => 100, 'unit_cost' => 1000,
    ]);

    [$created] = app(ReplayReceptionStockSync::class)($reception->fresh('items'));
    expect($created)->toBe(1);

    $stock = ProductStock::where('product_id', $coil->id)->where('warehouse_id', $wh->id)->first();
    // 100 KG → 57,1102 MTL, coût CMP 1751,00 /MTL.
    expect((float) $stock->quantity)->toEqual(57.1102);
    expect((float) $stock->avg_cost)->toEqual(1751.0);
});
