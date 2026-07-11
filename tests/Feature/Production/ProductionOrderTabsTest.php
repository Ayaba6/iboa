<?php

/**
 * [Refonte Prod X3 §5] Fiche OF : onglets lecture
 * Allocation matière / Coûts / Traçabilité — présents en édition, absents en création.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function tabsAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TABS'], ['email' => 'tabs@tabs.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WT'], ['name' => 'WT', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('la fiche OF en édition expose les onglets Allocation / Coûts / Traçabilité', function () {
    $this->actingAs(tabsAdmin());
    $co = Company::first();
    $pf = Product::factory()->create(['is_manufacturable' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-TABS-1', 'status' => 'brouillon', 'quantity_requested' => 10, 'product_id' => $pf->id,
    ]);

    $this->get(route('production.orders.edit', $of))
        ->assertOk()
        ->assertSee('Allocation matière')->assertSee('Coûts')->assertSee('Traçabilité')
        ->assertSee('sec-allocation')->assertSee('sec-couts')->assertSee('sec-tracabilite');
});

it('l\'onglet Coûts affiche le détail standard vs réel quand un coût existe', function () {
    $this->actingAs(tabsAdmin());
    $co = Company::first();
    $pf = Product::factory()->create(['is_manufacturable' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-TABS-2', 'status' => 'lance', 'quantity_requested' => 10,
        'product_id' => $pf->id, 'launched_at' => now(),
    ]);
    ProductionCost::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'material_cost' => 50000, 'labor_cost' => 12000, 'machine_cost' => 8000,
        'energy_cost' => 2000, 'maintenance_cost' => 1000, 'packaging_cost' => 500,
        'overhead_cost' => 1500, 'total_cost' => 75000, 'standard_total' => 70000,
        'variance' => 5000, 'cost_per_meter' => 1250, 'cost_per_unit' => 7500, 'margin' => 15000,
    ]);

    $this->get(route('production.orders.edit', $of))
        ->assertOk()
        ->assertSee('Coût de revient')->assertSee('Coût réel total')
        ->assertSee('Coût standard')->assertSee('Écart')->assertSee('Marge estimée');
});

it('le formulaire de création n\'expose pas les onglets lecture', function () {
    $this->actingAs(tabsAdmin());

    $this->get(route('production.orders.create'))
        ->assertOk()
        ->assertDontSee('sec-allocation')->assertDontSee('sec-tracabilite');
});
