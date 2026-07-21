<?php

/**
 * [MTS §2] Planification production pour stock :
 * besoin net = cible + sécurité − disponible − production planifiée ;
 * OF MTS créé sans commande client ; stock MTS non réservé à un client.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtsCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTS-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MTS Co'], ['email' => 'mts@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function mtsAdmin(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

it('calcule le besoin net : cible + sécurité − disponible − planifié', function () {
    $co = mtsCompany();
    $this->actingAs(mtsAdmin($co));

    // Scénario §22 : cible 2000, dispo 300, planifié 200 → besoin 1500 (sécurité 0).
    $fer = Product::factory()->create([
        'name' => 'Fer à béton HA 10', 'production_mode' => 'mts', 'is_stockable' => true,
        'is_active' => true, 'stock_min' => 1000, 'stock_max' => 2000, 'stock_securite' => 0,
    ]);
    $wh = Warehouse::firstOrCreate(['code' => 'WH-MTS'], ['name' => 'Dépôt MTS', 'company_id' => $co->id, 'is_active' => true]);
    ProductStock::create(['product_id' => $fer->id, 'warehouse_id' => $wh->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-MTS-' . uniqid(), 'status' => 'lance', 'quantity_requested' => 200, 'product_id' => $fer->id,
    ]);

    $resp = $this->get(route('production.orders.mts'));
    $resp->assertOk()
        ->assertSee('Fer à béton HA 10')
        ->assertSee('1 500'); // besoin net = 2000 + 0 − 300 − 200
});

it('crée un OF MTS sans commande client ni client', function () {
    $co = mtsCompany();
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-MTS-' . uniqid(), 'status' => 'brouillon', 'quantity_requested' => 1000,
        'product_id' => Product::factory()->create(['production_mode' => 'mts'])->id,
    ]);

    expect($of->order_id)->toBeNull()
        ->and($of->client_id)->toBeNull();
});

it('sous le minimum : état affiché et OF proposé pour le besoin', function () {
    $co = mtsCompany();
    $this->actingAs(mtsAdmin($co));

    $fer = Product::factory()->create([
        'name' => 'Fer à béton HA 8', 'production_mode' => 'mts', 'is_stockable' => true,
        'is_active' => true, 'stock_min' => 500, 'stock_max' => null, 'stock_securite' => 0,
    ]);
    $wh = Warehouse::firstOrCreate(['code' => 'WH-MTS2'], ['name' => 'Dépôt MTS2', 'company_id' => $co->id, 'is_active' => true]);
    ProductStock::create(['product_id' => $fer->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0]);

    $this->get(route('production.orders.mts'))
        ->assertOk()
        ->assertSee('Sous le minimum')   // dispo 100 < min 500
        ->assertSee('Créer OF MTS');     // besoin = 500 − 100 = 400 > 0
});
