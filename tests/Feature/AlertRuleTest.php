<?php

/**
 * [PIL-04] Alertes par seuil — opérateurs, évaluation, déclenchement.
 */

use App\Models\AlertRule;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseReport;
use App\Models\User;
use App\Services\AlertRuleService;

uses(\Tests\Concerns\RefreshDatabase::class);

function alertUser(): User
{
    $co = Company::firstOrCreate(['name' => 'ALERT Co'], ['email' => 'alert@iboa.test']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);

    return $u;
}

it('évalue les opérateurs de seuil', function () {
    $svc = app(AlertRuleService::class);
    expect($svc->isTriggered(5, 'gt', 3))->toBeTrue();
    expect($svc->isTriggered(3, 'gt', 3))->toBeFalse();
    expect($svc->isTriggered(3, 'gte', 3))->toBeTrue();
    expect($svc->isTriggered(2, 'lt', 3))->toBeTrue();
    expect($svc->isTriggered(3, 'lte', 3))->toBeTrue();
    expect($svc->isTriggered(3, 'eq', 3))->toBeTrue();
    expect($svc->isTriggered(4, 'eq', 3))->toBeFalse();
});

it('déclenche une règle quand l’indicateur dépasse le seuil', function () {
    $this->actingAs(alertUser());
    $cid = currentCompany()->id;
    $emp = Employee::factory()->create(['company_id' => $cid]);
    // 3 notes de frais soumises
    foreach (range(1, 3) as $i) {
        ExpenseReport::create(['company_id' => $cid, 'employee_id' => $emp->id, 'title' => "N$i", 'status' => 'soumise']);
    }

    $rule = AlertRule::create([
        'company_id' => $cid, 'name' => 'Trop de frais en attente', 'metric' => 'notes_frais_a_approuver',
        'operator' => 'gt', 'threshold' => 2, 'is_active' => true, 'target_roles' => [],
    ]);

    $res = app(AlertRuleService::class)->evaluate($rule);
    expect($res['value'])->toBe(3.0);
    expect($res['triggered'])->toBeTrue();
});

it('run() compte les règles déclenchées et fige la dernière valeur', function () {
    $this->actingAs(alertUser());
    $cid = currentCompany()->id;
    $emp = Employee::factory()->create(['company_id' => $cid]);
    ExpenseReport::create(['company_id' => $cid, 'employee_id' => $emp->id, 'title' => 'X', 'status' => 'soumise']);

    // Règle déclenchée (1 > 0) + règle non déclenchée (1 > 5)
    AlertRule::create(['company_id' => $cid, 'name' => 'A', 'metric' => 'notes_frais_a_approuver', 'operator' => 'gt', 'threshold' => 0, 'is_active' => true, 'target_roles' => []]);
    $calm = AlertRule::create(['company_id' => $cid, 'name' => 'B', 'metric' => 'notes_frais_a_approuver', 'operator' => 'gt', 'threshold' => 5, 'is_active' => true, 'target_roles' => []]);

    $n = app(AlertRuleService::class)->run();

    expect($n)->toBe(1);
    expect((float) $calm->fresh()->last_value)->toBe(1.0);
    expect($calm->fresh()->last_triggered_at)->toBeNull(); // non déclenchée
});
