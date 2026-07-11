<?php

/**
 * [Refonte Prod X3 §19] Plan de charge : replanification des OF —
 * déplacement des dates prévues + réaffectation de ligne, gardes clôture/ligne indisponible.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function planAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PLAN'], ['email' => 'plan@plan.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WP'], ['name' => 'WP', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function planOf(array $attrs = []): ProductionOrder
{
    $co = Company::first();
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    return ProductionOrder::create(array_merge([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-PLAN-' . uniqid(), 'status' => 'lance',
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => $pf->id, 'launched_at' => now(),
    ], $attrs));
}

it('affiche la section de replanification sur le plan de charge', function () {
    $this->actingAs(planAdmin());
    $of = planOf();

    $this->get(route('production.planning'))
        ->assertOk()
        ->assertSee('Replanification des OF actifs')
        ->assertSee($of->number);
});

it('déplace les dates prévues et réaffecte la ligne d\'un OF', function () {
    $this->actingAs(planAdmin());
    $co = Company::first();
    $ligne = ProductionLine::factory()->create(['company_id' => $co->id, 'is_active' => true, 'status' => 'active']);
    $of = planOf();

    $this->post(route('production.planning.replan', $of), [
        'production_line_id'      => $ligne->id,
        'date_fabrication_prevue' => '2026-08-01',
        'date_fin_prevue'         => '2026-08-05',
    ])->assertRedirect()->assertSessionHas('success');

    $of->refresh();
    expect($of->production_line_id)->toBe($ligne->id);
    expect($of->date_fabrication_prevue->format('Y-m-d'))->toBe('2026-08-01');
    expect($of->date_fin_prevue->format('Y-m-d'))->toBe('2026-08-05');
});

it('refuse de replanifier un OF clôturé ou annulé', function () {
    $this->actingAs(planAdmin());

    $this->post(route('production.planning.replan', planOf(['status' => 'termine'])), [
        'date_fabrication_prevue' => '2026-08-01',
    ])->assertStatus(422);

    $this->post(route('production.planning.replan', planOf(['status' => 'annule'])), [
        'date_fabrication_prevue' => '2026-08-01',
    ])->assertStatus(422);
});

it('refuse une date de fin antérieure à la date de fabrication', function () {
    $this->actingAs(planAdmin());
    $of = planOf();

    $this->post(route('production.planning.replan', $of), [
        'date_fabrication_prevue' => '2026-08-10',
        'date_fin_prevue'         => '2026-08-01',
    ])->assertSessionHasErrors('date_fin_prevue');
});
