<?php

/**
 * [RH-05] Mouvements & carrière — enregistrement + application à la fiche salarié.
 */

use App\Models\CareerEvent;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Services\CareerMovementService;

uses(\Tests\Concerns\RefreshDatabase::class);

function careerCo(): Company
{
    return Company::firstOrCreate(['name' => 'CAR Co'], ['email' => 'car@iboa.test']);
}

it('enregistre un mouvement et l’applique à la fiche (date atteinte)', function () {
    $co = careerCo();
    $dep = Department::create(['company_id' => $co->id, 'name' => 'Commercial']);
    $poste = JobPosition::create(['company_id' => $co->id, 'code' => 'CHEF', 'name' => 'Chef de ligne', 'is_active' => true]);
    $emp = Employee::factory()->create(['company_id' => $co->id, 'category' => 'C1', 'job_position_id' => null, 'department_id' => null]);

    $event = app(CareerMovementService::class)->record($emp, [
        'type' => 'promotion', 'effective_date' => now()->subDay()->toDateString(),
        'to_job_position_id' => $poste->id, 'to_department_id' => $dep->id, 'to_category' => 'C2',
        'grade' => 'B3', 'salary' => 350000, 'reason' => 'Promotion annuelle',
    ]);

    // historique : from capturé, applied
    expect($event->from_category)->toBe('C1');
    expect($event->applied)->toBeTrue();

    // fiche salarié mise à jour
    $emp->refresh();
    expect($emp->job_position_id)->toBe($poste->id);
    expect($emp->department_id)->toBe($dep->id);
    expect($emp->category)->toBe('C2');
});

it('ne s’applique pas si la date d’effet est future', function () {
    $co = careerCo();
    $poste = JobPosition::create(['company_id' => $co->id, 'code' => 'MUT', 'name' => 'Muté', 'is_active' => true]);
    $emp = Employee::factory()->create(['company_id' => $co->id, 'job_position_id' => null]);

    $event = app(CareerMovementService::class)->record($emp, [
        'type' => 'mutation', 'effective_date' => now()->addMonth()->toDateString(),
        'to_job_position_id' => $poste->id,
    ]);

    expect($event->applied)->toBeFalse();
    expect($emp->fresh()->job_position_id)->toBeNull(); // pas encore appliqué
});

it('n’écrase pas les champs laissés inchangés', function () {
    $co = careerCo();
    $emp = Employee::factory()->create(['company_id' => $co->id, 'category' => 'C1', 'fonction' => 'Vendeur']);

    app(CareerMovementService::class)->record($emp, [
        'type' => 'revalorisation', 'effective_date' => now()->toDateString(),
        'salary' => 400000, // aucun to_* → fiche inchangée
    ]);

    $emp->refresh();
    expect($emp->category)->toBe('C1');
    expect($emp->fonction)->toBe('Vendeur');
    expect(CareerEvent::where('employee_id', $emp->id)->count())->toBe(1);
});
