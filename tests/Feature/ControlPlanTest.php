<?php

/**
 * [QUA-01] Plans de contrôle — CRUD + caractéristiques + tolérances.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Quality\Models\ControlPlan;
use App\Modules\Quality\Models\ControlPlanCharacteristic;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cpAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CP'], ['email' => 'cp@qa.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('rend l’index des plans de contrôle', function () {
    $this->actingAs(cpAdmin());
    $this->get(route('qualite.control-plans.index'))->assertOk()->assertSee('Plans de contrôle');
});

it('crée un plan avec ses caractéristiques', function () {
    $this->actingAs(cpAdmin());

    $this->post(route('qualite.control-plans.store'), [
        'name' => 'Contrôle tôle bac', 'reference' => 'PC-001', 'stage' => 'final', 'is_active' => '1',
        'characteristics' => [
            ['name' => 'Épaisseur', 'method' => 'Pied à coulisse', 'unit' => 'mm', 'frequency' => 'Chaque lot',
             'target_value' => '0.30', 'tolerance_min' => '0.28', 'tolerance_max' => '0.32', 'is_critical' => '1', 'responsible' => 'Opérateur'],
            ['name' => 'Aspect', 'method' => 'Visuel', 'frequency' => 'Continu'],
            ['name' => '', 'method' => 'ligne vide ignorée'],
        ],
    ])->assertRedirect();

    $plan = ControlPlan::first();
    expect($plan->name)->toBe('Contrôle tôle bac')->and($plan->stage)->toBe('final');
    expect($plan->characteristics)->toHaveCount(2); // la ligne vide est filtrée
    $ep = $plan->characteristics->firstWhere('name', 'Épaisseur');
    expect($ep->is_critical)->toBeTrue();
    expect((float) $ep->tolerance_max)->toBe(0.32);
    expect($ep->sort_order)->toBe(1);
});

it('remplace les caractéristiques à la mise à jour', function () {
    $this->actingAs(cpAdmin());
    $plan = ControlPlan::create(['company_id' => currentCompany()->id, 'name' => 'P', 'stage' => 'production', 'is_active' => true]);
    $plan->characteristics()->createMany([
        ['sort_order' => 1, 'name' => 'A'], ['sort_order' => 2, 'name' => 'B'],
    ]);

    $this->put(route('qualite.control-plans.update', $plan), [
        'name' => 'P', 'stage' => 'production', 'is_active' => '1',
        'characteristics' => [['name' => 'C', 'unit' => 'kg']],
    ])->assertRedirect(route('qualite.control-plans.show', $plan));

    expect($plan->fresh()->characteristics->pluck('name')->all())->toBe(['C']);
});

it('évalue les tolérances d’une caractéristique', function () {
    $c = new ControlPlanCharacteristic(['tolerance_min' => 0.28, 'tolerance_max' => 0.32]);
    expect($c->isWithinTolerance(0.30))->toBeTrue();
    expect($c->isWithinTolerance(0.35))->toBeFalse();
    expect($c->isWithinTolerance(0.20))->toBeFalse();

    $unbounded = new ControlPlanCharacteristic([]);
    expect($unbounded->isWithinTolerance(999))->toBeNull();
});
