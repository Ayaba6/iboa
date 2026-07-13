<?php

/**
 * [RH-11] Évaluations & performance — note pondérée, appréciation, finalisation.
 */

use App\Models\Appraisal;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AppraisalService;

uses(\Tests\Concerns\RefreshDatabase::class);

function apprCo(): Company
{
    return Company::firstOrCreate(['name' => 'APPR Co'], ['email' => 'appr@iboa.test']);
}

function makeAppraisal(): Appraisal
{
    $co = apprCo();
    $emp = Employee::factory()->create(['company_id' => $co->id]);
    $a = Appraisal::create([
        'company_id' => $co->id, 'employee_id' => $emp->id, 'campaign' => 'Annuelle', 'period_year' => 2026,
        'status' => 'auto_evaluation',
    ]);
    $a->criteria()->createMany([
        ['sort_order' => 1, 'label' => 'Qualité', 'weight' => 60, 'manager_rating' => 4.0],
        ['sort_order' => 2, 'label' => 'Assiduité', 'weight' => 40, 'manager_rating' => 3.0],
    ]);

    return $a->fresh();
}

it('calcule la note manager pondérée', function () {
    $a = makeAppraisal();
    $svc = app(AppraisalService::class);

    // (4×60 + 3×40) / 100 = 3,6
    expect($svc->weightedScore($a, 'manager_rating'))->toBe(3.6);
    expect($svc->ratingFor(3.6))->toBe('satisfaisant');
});

it('recalcule et fige la note à la finalisation', function () {
    $a = makeAppraisal();
    app(AppraisalService::class)->finalize($a);

    $a->refresh();
    expect((float) $a->overall_score)->toBe(3.6);
    expect($a->rating)->toBe('satisfaisant');
    expect($a->status)->toBe('finalisee');
    expect($a->finalized_at)->not->toBeNull();
});

it('mappe les seuils d’appréciation', function () {
    $svc = app(AppraisalService::class);
    expect($svc->ratingFor(1.5))->toBe('insuffisant');
    expect($svc->ratingFor(2.5))->toBe('a_ameliorer');
    expect($svc->ratingFor(3.9))->toBe('satisfaisant');
    expect($svc->ratingFor(4.2))->toBe('bon');
    expect($svc->ratingFor(4.8))->toBe('excellent');
    expect($svc->ratingFor(null))->toBeNull();
});
