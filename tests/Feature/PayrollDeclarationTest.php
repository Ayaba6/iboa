<?php

/**
 * [PAI-08] Déclarations CNSS/IUTS — figeage depuis un run + suivi dépôt/paiement.
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollDeclaration;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\PayrollDeclarationService;

uses(\Tests\Concerns\RefreshDatabase::class);

function declRun(): PayrollRun
{
    $co = Company::firstOrCreate(['name' => 'DECL Co'], ['email' => 'decl@iboa.test']);
    $run = PayrollRun::create([
        'company_id' => $co->id, 'period_month' => 6, 'period_year' => 2026,
        'status' => 'valide', 'total_net' => 0, 'employee_count' => 0,
    ]);
    foreach ([[300000, 18000, 48000, 280000, 25000], [200000, 12000, 32000, 190000, 15000]] as [$base, $ce, $cer, $imp, $iuts]) {
        $emp = Employee::factory()->create(['company_id' => $co->id]);
        PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $emp->id,
            'employee_name' => $emp->first_name, 'employee_matricule' => $emp->matricule,
            'salaire_brut' => $base, 'salaire_net' => $base,
            'cnss_base' => $base, 'cnss_employee' => $ce, 'cnss_employer' => $cer,
            'salaire_imposable' => $imp, 'iuts_amount' => $iuts,
        ]);
    }

    return $run->fresh();
}

it('fige les déclarations CNSS et IUTS agrégées du run', function () {
    $decls = app(PayrollDeclarationService::class)->generateForRun(declRun());

    expect($decls)->toHaveCount(2);
    $cnss = PayrollDeclaration::where('type', 'cnss')->first();
    expect((float) $cnss->base_amount)->toBe(500000.0);
    expect((float) $cnss->salarial_amount)->toBe(30000.0);
    expect((float) $cnss->patronal_amount)->toBe(80000.0);
    expect((float) $cnss->total_amount)->toBe(110000.0);
    expect($cnss->headcount)->toBe(2);

    $iuts = PayrollDeclaration::where('type', 'iuts')->first();
    expect((float) $iuts->base_amount)->toBe(470000.0);
    expect((float) $iuts->total_amount)->toBe(40000.0);
    expect($cnss->status)->toBe('a_deposer');
});

it('suit le dépôt puis le paiement d’une déclaration', function () {
    $svc = app(PayrollDeclarationService::class);
    $svc->generateForRun(declRun());
    $cnss = PayrollDeclaration::where('type', 'cnss')->first();

    $svc->markDeposited($cnss, 'ACC-2026-06-001');
    expect($cnss->fresh()->status)->toBe('depose');
    expect($cnss->fresh()->receipt_number)->toBe('ACC-2026-06-001');

    $svc->markPaid($cnss->fresh());
    expect($cnss->fresh()->status)->toBe('paye');
    expect($cnss->fresh()->paid_at)->not->toBeNull();
});

it('ne réécrit pas une déclaration déjà déposée (idempotent)', function () {
    $run = declRun();
    $svc = app(PayrollDeclarationService::class);
    $svc->generateForRun($run);
    $cnss = PayrollDeclaration::where('type', 'cnss')->first();
    $svc->markDeposited($cnss, 'ACC-X');

    // regénérer ne doit pas repasser la déclaration en "a_deposer"
    $svc->generateForRun($run->fresh());

    expect($cnss->fresh()->status)->toBe('depose');
    expect(PayrollDeclaration::where('payroll_run_id', $run->id)->count())->toBe(2);
});
