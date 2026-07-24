<?php

/**
 * [Ultimatum — Parcours F : paie] Volets NON couverts ailleurs :
 *  - maker-checker : le préparateur du run ne le valide PAS lui-même
 *    (un autre gestionnaire RH le valide) ;
 *  - confidentialité INTER-salariés : sur le portail, un salarié ne peut
 *    télécharger QUE son propre bulletin, jamais celui d'un collègue ;
 *  - immuabilité après changement de barème : un run validé garde ses
 *    montants figés (snapshot) même si le SMIG/paramètres changent ensuite.
 *
 * (HS, absence, prêt, avance, plafond CNSS, charges de famille, immuabilité
 * du recalcul et 403 global sont prouvés par PayrollProfilesTest ;
 * versionnement réglementaire par PayrollRegulationVersioningTest.)
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function paieEmployeeWithUser(Company $co, int $salary, ?User $user = null): Employee
{
    $dept = \App\Models\Department::firstOrCreate(['company_id' => $co->id, 'name' => 'F Dept'], ['code' => 'FDP']);
    $emp = Employee::factory()->create([
        'company_id' => $co->id, 'department_id' => $dept->id,
        'hiring_date' => '2020-01-01', 'status' => 'actif',
        'user_id' => $user?->id,
    ]);
    EmployeeContract::create([
        'employee_id' => $emp->id, 'company_id' => $co->id, 'contract_type' => 'CDI',
        'start_date' => '2020-01-01', 'base_salary' => $salary, 'is_current' => true, 'status' => 'actif',
    ]);

    return $emp;
}

it('F-maker-checker : le préparateur du run ne le valide pas lui-même (mode strict)', function () {
    config(['security.maker_checker.enabled' => true]);
    $co = bfCompany();
    bfSettings($co);
    bfAccountClasses($co);

    // Préparateur : RH manager (crée le run)
    $preparateur = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $rolePrep = Role::firstOrCreate(['name' => 'f-rh-prep', 'guard_name' => 'web']);
    foreach (['rh.payroll.view', 'rh.payroll.manage', 'rh.payroll.validate'] as $p) {
        $rolePrep->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $preparateur->assignRole($rolePrep);
    $this->actingAs($preparateur);
    paieEmployeeWithUser($co, 200_000);

    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 7, 'period_year' => 2026, 'notes' => '']);
    $svc->calculate($run->fresh());

    // Le préparateur (created_by) ne peut pas valider son propre run
    try {
        $svc->validate($run->fresh());
        $this->fail('L\'auto-validation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Séparation des tâches');
    }
    expect($run->fresh()->status)->toBe('calcule');

    // Un AUTRE gestionnaire RH habilité valide
    $valideur = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $valideur->assignRole($rolePrep);
    $this->actingAs($valideur);
    $svc->validate($run->fresh());
    expect($run->fresh()->status)->toBe('valide');
});

it('F-confidentialité inter-salariés : un salarié ne télécharge QUE son propre bulletin', function () {
    $co = bfCompany();
    bfSettings($co);
    bfAccountClasses($co);
    $this->actingAs(bfAdmin());

    // Deux salariés, chacun lié à un compte utilisateur au rôle « portail salarié »
    // (droit d'accéder à SON espace — la confidentialité inter-salariés est
    // assurée en plus par le contrôle item->employee_id === $employee->id)
    $rolePortail = Role::firstOrCreate(['name' => 'f-salarie', 'guard_name' => 'web']);
    $rolePortail->givePermissionTo(Permission::firstOrCreate(['name' => 'rh.portail', 'guard_name' => 'web']));
    $userA = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $userB = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $userA->assignRole($rolePortail);
    $userB->assignRole($rolePortail);
    $empA = paieEmployeeWithUser($co, 200_000, $userA);
    $empB = paieEmployeeWithUser($co, 300_000, $userB);

    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 7, 'period_year' => 2026, 'notes' => '']);
    $svc->calculate($run->fresh());
    $svc->validate($run->fresh());

    $itemA = PayrollItem::where('employee_id', $empA->id)->first();
    $itemB = PayrollItem::where('employee_id', $empB->id)->first();

    // Salarié A : accède à SON bulletin
    $this->actingAs($userA);
    $this->get(route('rh.portail.bulletin-pdf', $itemA))->assertOk();
    // Salarié A : REFUSÉ sur le bulletin de B (confidentialité salariale)
    $this->get(route('rh.portail.bulletin-pdf', $itemB))->assertForbidden();
});

it('F-immuabilité barème : un run validé garde ses montants après changement de SMIG', function () {
    $co = bfCompany();
    bfSettings($co);
    bfAccountClasses($co);
    $this->actingAs(bfAdmin());
    $emp = paieEmployeeWithUser($co, 200_000);

    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 7, 'period_year' => 2026, 'notes' => '']);
    $svc->calculate($run->fresh());
    $svc->validate($run->fresh());

    $item = PayrollItem::where('employee_id', $emp->id)->first();
    $netFige = (int) $item->salaire_net;
    $snapshot = $run->fresh()->calculation_parameters_snapshot;
    expect($netFige)->toBeGreaterThan(0)
        ->and($snapshot)->not->toBeNull(); // paramètres figés dans le run

    // Changement de paramétrage APRÈS validation (nouveau SMIG, nouveaux taux)
    \App\Models\PayrollSetting::forCompany($co->id)->update(['smig' => 60_000, 'cnss_employee_rate' => 10]);

    // Le run validé n'est pas recalculable ET ses montants restent figés
    try {
        $svc->calculate($run->fresh());
        $this->fail('Le recalcul d\'un run validé aurait dû être refusé.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('validé');
    }
    expect((int) PayrollItem::where('employee_id', $emp->id)->value('salaire_net'))->toBe($netFige);
});
