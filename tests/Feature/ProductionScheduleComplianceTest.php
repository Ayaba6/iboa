<?php

/**
 * [PRO Respect du programme] Ponctualité des OF : fin réelle vs fin prévue.
 *
 *  - scheduleDelayDays / finishedOnTime sur le modèle ;
 *  - scope termineEntre ;
 *  - rapport « respect_programme » (taux de ponctualité) accessible et cohérent.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function pscSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PSC'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PSC Co'], ['email' => 'psc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return compact('co', 'u');
}

function pscOrder(Company $co, string $planned, ?string $finished, string $status = 'termine'): ProductionOrder
{
    return ProductionOrder::factory()->create([
        'company_id'      => $co->id,
        'product_id'      => Product::factory()->create()->id,
        'status'          => $status,
        'date_fin_prevue' => $planned,
        'finished_at'     => $finished,
    ]);
}

it('calcule l’écart de délai et la ponctualité sur le modèle', function () {
    $ctx = pscSetup();

    $onTime = pscOrder($ctx['co'], '2026-07-10', '2026-07-08'); // 2 j d'avance
    $late   = pscOrder($ctx['co'], '2026-07-10', '2026-07-13'); // 3 j de retard
    $exact  = pscOrder($ctx['co'], '2026-07-10', '2026-07-10'); // pile

    expect($onTime->scheduleDelayDays())->toBe(-2)
        ->and($onTime->finishedOnTime())->toBeTrue();
    expect($late->scheduleDelayDays())->toBe(3)
        ->and($late->finishedOnTime())->toBeFalse();
    expect($exact->scheduleDelayDays())->toBe(0)
        ->and($exact->finishedOnTime())->toBeTrue();

    // Fin prévue absente → non mesurable
    $noPlan = pscOrder($ctx['co'], '2026-07-10', '2026-07-10');
    $noPlan->update(['date_fin_prevue' => null]);
    expect($noPlan->fresh()->scheduleDelayDays())->toBeNull()
        ->and($noPlan->fresh()->finishedOnTime())->toBeNull();
});

it('sélectionne les OF terminés sur la période via le scope', function () {
    $ctx = pscSetup();
    pscOrder($ctx['co'], '2026-07-10', '2026-07-08');            // dans la période
    pscOrder($ctx['co'], '2026-01-10', '2026-01-08');            // hors période
    pscOrder($ctx['co'], '2026-07-10', null, 'en_cours');        // pas terminé

    $count = ProductionOrder::termineEntre('2026-07-01', '2026-07-31')->count();
    expect($count)->toBe(1);
});

it('expose le rapport respect du programme avec le taux de ponctualité', function () {
    pscSetup();
    Permission::firstOrCreate(['name' => 'production.report.view', 'guard_name' => 'web']);

    $co = currentCompany();
    pscOrder($co, '2026-07-10', '2026-07-08'); // à l'heure
    pscOrder($co, '2026-07-10', '2026-07-09'); // à l'heure
    pscOrder($co, '2026-07-10', '2026-07-15'); // retard

    $resp = $this->get(route('production.reports', ['type' => 'respect_programme', 'from' => '2026-07-01', 'to' => '2026-07-31']));
    $resp->assertOk();
    // 2/3 à l'heure = 66,7 %
    $resp->assertSee('66,7');
    $resp->assertSee('Respect du programme');
});
