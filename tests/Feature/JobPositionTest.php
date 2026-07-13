<?php

/**
 * [RH-01] Référentiel Postes / grades / emplois — CRUD + garde suppression.
 */

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\JobPosition;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function jobAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'JOB-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'JOB Co'], ['email' => 'job@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    foreach (['rh.employees.view', 'rh.employees.manage'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'drh', 'guard_name' => 'web']);
    $role->givePermissionTo(['rh.employees.view', 'rh.employees.manage']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

it('crée un poste et l’affiche dans la liste', function () {
    $this->actingAs(jobAdmin());
    $dept = Department::create(['company_id' => Company::first()->id, 'name' => 'Production', 'is_active' => true]);

    $this->post(route('rh.postes.store'), [
        'code' => 'prof-01', 'name' => 'Opérateur profilage', 'department_id' => $dept->id,
        'grade' => 'Échelon 2', 'category' => 'Ouvrier', 'salary_min' => 90000, 'salary_max' => 150000,
    ])->assertRedirect(route('rh.postes.index'));

    $pos = JobPosition::firstWhere('code', 'PROF-01'); // code forcé en majuscule
    expect($pos)->not->toBeNull()->and($pos->name)->toBe('Opérateur profilage');

    $this->get(route('rh.postes.index'))->assertOk()->assertSee('Opérateur profilage');
});

it('refuse un code dupliqué dans la même société', function () {
    $this->actingAs(jobAdmin());
    JobPosition::create(['company_id' => Company::first()->id, 'code' => 'DUP', 'name' => 'X']);

    $this->post(route('rh.postes.store'), ['code' => 'DUP', 'name' => 'Y'])
        ->assertSessionHasErrors('code');
});

it('empêche la suppression d’un poste occupé', function () {
    $this->actingAs(jobAdmin());
    $pos = JobPosition::create(['company_id' => Company::first()->id, 'code' => 'OCC', 'name' => 'Occupé']);
    Employee::factory()->create(['company_id' => Company::first()->id, 'job_position_id' => $pos->id]);

    $this->delete(route('rh.postes.destroy', $pos))->assertSessionHas('error');
    expect(JobPosition::find($pos->id))->not->toBeNull();
});
