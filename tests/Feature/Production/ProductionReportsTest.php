<?php

/**
 * [Refonte Prod X3 §26] Rapports production ajoutés :
 * OF en retard · OF par statut · Performance par ligne · Stock MP/PF.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function repAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'REP'], ['email' => 'rep@rep.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WR'], ['name' => 'WR', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('rend le rapport « OF en retard » avec les OF concernés', function () {
    $this->actingAs(repAdmin());
    $co = Company::first();
    $pf = Product::factory()->create();
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-REP-RETARD', 'status' => 'en_cours', 'quantity_requested' => 5,
        'product_id' => $pf->id, 'date_fin_prevue' => now()->subDays(4), 'launched_at' => now()->subDays(9),
    ]);

    $this->get(route('production.reports', ['type' => 'of_retard']))
        ->assertOk()->assertSee('OF en retard')->assertSee('OF-REP-RETARD');
});

it('rend le rapport « OF par statut » avec les compteurs', function () {
    $this->actingAs(repAdmin());
    $co = Company::first();
    $pf = Product::factory()->create();
    foreach (['brouillon', 'brouillon', 'en_cours'] as $i => $s) {
        ProductionOrder::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'number' => 'OF-REP-S' . $i, 'status' => $s, 'quantity_requested' => 5, 'product_id' => $pf->id,
            'launched_at' => $s === 'en_cours' ? now() : null,
        ]);
    }

    $this->get(route('production.reports', ['type' => 'of_statut']))
        ->assertOk()->assertSee('OF par statut')->assertSee('Brouillon')->assertSee('En cours');
});

it('rend les rapports « Performance par ligne » et « Stock MP/PF » sans erreur', function () {
    $this->actingAs(repAdmin());

    $this->get(route('production.reports', ['type' => 'perf_ligne']))
        ->assertOk()->assertSee('Performance par ligne');

    $this->get(route('production.reports', ['type' => 'stock_mp_pf']))
        ->assertOk()->assertSee('Stock matière');
});
