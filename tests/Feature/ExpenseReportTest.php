<?php

/**
 * [RH-09] Notes de frais — total, workflow soumission/approbation/remboursement.
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseReport;
use App\Services\ExpenseReportService;

uses(\Tests\Concerns\RefreshDatabase::class);

function expenseReport(): ExpenseReport
{
    $co = Company::firstOrCreate(['name' => 'EXP Co'], ['email' => 'exp@iboa.test']);
    $emp = Employee::factory()->create(['company_id' => $co->id]);
    $r = ExpenseReport::create([
        'company_id' => $co->id, 'employee_id' => $emp->id, 'title' => 'Mission Bobo', 'status' => 'brouillon',
    ]);
    $r->lines()->createMany([
        ['sort_order' => 1, 'category' => 'transport', 'amount' => 25000],
        ['sort_order' => 2, 'category' => 'hebergement', 'amount' => 40000],
        ['sort_order' => 3, 'category' => 'repas', 'amount' => 12000],
    ]);

    return $r->fresh();
}

it('calcule le total depuis les lignes', function () {
    $r = expenseReport();
    app(ExpenseReportService::class)->refreshTotal($r);

    expect((float) $r->fresh()->total_amount)->toBe(77000.0); // 25000+40000+12000
});

it('déroule le workflow soumission → approbation → remboursement', function () {
    $r = expenseReport();
    $svc = app(ExpenseReportService::class);

    $svc->submit($r);
    expect($r->fresh()->status)->toBe('soumise');
    expect($r->fresh()->isEditable())->toBeFalse();

    $svc->approve($r->fresh(), null);
    expect($r->fresh()->status)->toBe('approuvee');
    expect($r->fresh()->approved_at)->not->toBeNull();

    $svc->markReimbursed($r->fresh(), 'virement');
    expect($r->fresh()->status)->toBe('remboursee');
    expect($r->fresh()->payment_method)->toBe('virement');
});

it('rejette avec motif et redevient modifiable', function () {
    $r = expenseReport();
    $svc = app(ExpenseReportService::class);
    $svc->submit($r);

    $svc->reject($r->fresh(), 'Justificatifs manquants');

    expect($r->fresh()->status)->toBe('rejetee');
    expect($r->fresh()->reject_reason)->toBe('Justificatifs manquants');
    expect($r->fresh()->isEditable())->toBeTrue(); // rejetée → re-modifiable
});
