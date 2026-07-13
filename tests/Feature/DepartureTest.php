<?php

/**
 * [RH-13] Départs & solde de tout compte — calcul STC + clôture.
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDeparture;
use App\Services\DepartureService;

uses(\Tests\Concerns\RefreshDatabase::class);

function departCo(): Company
{
    return Company::firstOrCreate(['name' => 'DEP Co'], ['email' => 'dep@iboa.test']);
}

function makeDeparture(): EmployeeDeparture
{
    $co = departCo();
    $emp = Employee::factory()->create(['company_id' => $co->id, 'status' => 'actif']);

    return EmployeeDeparture::create([
        'company_id' => $co->id, 'employee_id' => $emp->id, 'type' => 'licenciement',
        'effective_date' => '2026-07-31', 'status' => 'declare',
        'severance_amount' => 500000, 'notice_amount' => 200000,
        'leave_balance_amount' => 120000, 'other_amount' => 30000,
    ]);
}

it('calcule le total du solde de tout compte', function () {
    $d = makeDeparture();
    // 500000 + 200000 + 120000 + 30000 = 850000
    expect(app(DepartureService::class)->computeTotal($d))->toBe(850000.0);
});

it('clôture le départ, fige le STC et marque le salarié sorti', function () {
    $d = makeDeparture();
    app(DepartureService::class)->finalize($d);

    $d->refresh();
    expect((float) $d->total_stc)->toBe(850000.0);
    expect($d->status)->toBe('cloture');
    expect($d->finalized_at)->not->toBeNull();

    $emp = $d->employee->fresh();
    expect($emp->status)->toBe('sorti');
    expect(optional($emp->leave_date)->format('Y-m-d'))->toBe('2026-07-31');
});
