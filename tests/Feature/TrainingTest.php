<?php

/**
 * [RH-10] Formation & compétences — sessions, participants, coûts, habilitations.
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;

uses(\Tests\Concerns\RefreshDatabase::class);

function trainCo(): Company
{
    return Company::firstOrCreate(['name' => 'TRAIN Co'], ['email' => 'train@iboa.test']);
}

it('répartit le coût de session par participant', function () {
    $co = trainCo();
    $s = TrainingSession::create(['company_id' => $co->id, 'title' => 'Sécurité', 'cost' => 300000, 'status' => 'planifiee']);
    foreach (range(1, 3) as $i) {
        $emp = Employee::factory()->create(['company_id' => $co->id]);
        $s->participants()->create(['employee_id' => $emp->id, 'status' => 'inscrit']);
    }

    expect($s->fresh()->cost_per_participant)->toBe(100000.0); // 300000 / 3
});

it('renvoie null pour le coût par participant sans inscrit', function () {
    $co = trainCo();
    $s = TrainingSession::create(['company_id' => $co->id, 'title' => 'Vide', 'cost' => 100000, 'status' => 'planifiee']);
    expect($s->cost_per_participant)->toBeNull();
});

it('détecte une habilitation proche de l’échéance', function () {
    $co = trainCo();
    $s = TrainingSession::create(['company_id' => $co->id, 'title' => 'Pont roulant', 'status' => 'terminee']);
    $emp = Employee::factory()->create(['company_id' => $co->id]);

    $soon = $s->participants()->create([
        'employee_id' => $emp->id, 'status' => 'present',
        'certificate_number' => 'HAB-1', 'certificate_expiry' => now()->addDays(30)->toDateString(),
    ]);
    $far = new TrainingParticipant(['certificate_expiry' => now()->addYear()->toDateString()]);

    expect($soon->certificateExpiringSoon())->toBeTrue();
    expect($far->certificateExpiringSoon())->toBeFalse();
});
