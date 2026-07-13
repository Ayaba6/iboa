<?php

/**
 * [RH-03] Recrutement & onboarding — pipeline candidat + embauche → fiche salarié.
 */

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobCandidate;
use App\Models\Recruitment;
use App\Services\RecruitmentService;

uses(\Tests\Concerns\RefreshDatabase::class);

function recruitCo(): Company
{
    return Company::firstOrCreate(['name' => 'REC Co'], ['email' => 'rec@iboa.test']);
}

it('embauche un candidat retenu et crée sa fiche salarié', function () {
    $co = recruitCo();
    $dept = Department::create(['company_id' => $co->id, 'name' => 'Production']);
    $rec = Recruitment::create([
        'company_id' => $co->id, 'department_id' => $dept->id, 'title' => 'Opérateur profileuse',
        'contract_type' => 'cdi', 'positions_count' => 1, 'status' => 'ouvert',
    ]);
    $cand = JobCandidate::create([
        'company_id' => $co->id, 'recruitment_id' => $rec->id,
        'first_name' => 'Issa', 'last_name' => 'Ouédraogo', 'email' => 'issa@x.bf', 'status' => 'retenu',
    ]);

    $emp = app(RecruitmentService::class)->hire($cand);

    expect($emp)->toBeInstanceOf(Employee::class);
    expect($emp->first_name)->toBe('Issa')->and($emp->job_title)->toBe('Opérateur profileuse');
    expect($emp->department_id)->toBe($dept->id);
    expect($cand->fresh()->status)->toBe('embauche');
    expect($cand->fresh()->hired_employee_id)->toBe($emp->id);
    // quota atteint → besoin pourvu
    expect($rec->fresh()->status)->toBe('pourvu');
});

it('reste en cours tant que le quota de postes n’est pas atteint', function () {
    $co = recruitCo();
    $rec = Recruitment::create([
        'company_id' => $co->id, 'title' => 'Soudeur', 'contract_type' => 'cdd',
        'positions_count' => 3, 'status' => 'ouvert',
    ]);
    $cand = JobCandidate::create([
        'company_id' => $co->id, 'recruitment_id' => $rec->id,
        'first_name' => 'Awa', 'last_name' => 'Sawadogo', 'status' => 'retenu',
    ]);

    app(RecruitmentService::class)->hire($cand);

    expect($rec->fresh()->status)->toBe('en_cours');
});

it('n’embauche pas deux fois le même candidat (idempotent)', function () {
    $co = recruitCo();
    $rec = Recruitment::create(['company_id' => $co->id, 'title' => 'Cariste', 'contract_type' => 'cdi', 'positions_count' => 2, 'status' => 'ouvert']);
    $cand = JobCandidate::create(['company_id' => $co->id, 'recruitment_id' => $rec->id, 'first_name' => 'Paul', 'last_name' => 'Zongo', 'status' => 'retenu']);

    $svc = app(RecruitmentService::class);
    $e1 = $svc->hire($cand);
    $e2 = $svc->hire($cand->fresh());

    expect($e1->id)->toBe($e2->id);
    expect(Employee::withoutGlobalScopes()->where('company_id', $co->id)->count())->toBe(1);
});
