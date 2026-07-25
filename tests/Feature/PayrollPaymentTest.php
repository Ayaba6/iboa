<?php

/**
 * [PAI-07] Virements de paie ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â gÃƒÆ’Ã‚Â©nÃƒÆ’Ã‚Â©ration depuis un run, rapprochement, fichier bancaire.
 */

use App\Models\Company;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PayrollItem;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Services\PayrollPaymentService;

uses(\Tests\Concerns\RefreshDatabase::class);

function payRun(): PayrollRun
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PAY-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PAY Co'], ['email' => 'pay@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    $run = PayrollRun::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'period_month' => 7, 'period_year' => 2026,
        'status' => 'valide', 'total_net' => 0, 'employee_count' => 0,
    ]);

    foreach ([['KaborÃƒÆ’Ã‚Â© Moussa', 250000, 'virement'], ['TraorÃƒÆ’Ã‚Â© Awa', 180000, 'especes']] as [$name, $net, $mode]) {
        $emp = Employee::factory()->create(['company_id' => $co->id, 'payment_mode' => $mode, 'bank_name' => 'Coris', 'bank_account' => 'BF00'.$net]);
        PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $emp->id,
            'employee_name' => $name, 'employee_matricule' => $emp->matricule,
            'salaire_net' => $net, 'salaire_brut' => $net, 'cout_employeur' => $net,
        ]);
    }

    return $run->fresh();
}

it('gÃƒÆ’Ã‚Â©nÃƒÆ’Ã‚Â¨re une ligne de virement par salariÃƒÆ’Ã‚Â© du run', function () {
    $run = payRun();
    $n = app(PayrollPaymentService::class)->generateForRun($run);

    expect($n)->toBe(2);
    expect((int) PayrollPayment::where('payroll_run_id', $run->id)->sum('net_amount'))->toBe(430000);
    $virement = PayrollPayment::where('method', 'virement')->first();
    expect($virement->bank_name)->toBe('Coris')->and($virement->status)->toBe('en_attente');
});

it('marque un run entier payÃƒÆ’Ã‚Â© et le clÃƒÆ’Ã‚Â´ture', function () {
    $run = payRun();
    $svc = app(PayrollPaymentService::class);
    $svc->generateForRun($run);

    $svc->markRunPaid($run->fresh(), 'VIR-2026-07');

    expect(PayrollPayment::where('payroll_run_id', $run->id)->where('status', '!=', 'paye')->count())->toBe(0);
    expect($run->fresh()->paid_at)->not->toBeNull();
    expect($run->fresh()->status)->toBe('paye');
});

it('produit un fichier bancaire des virements', function () {
    $run = payRun();
    $svc = app(PayrollPaymentService::class);
    $svc->generateForRun($run);

    $csv = $svc->bankFileContent($run->fresh());

    expect($csv)->toContain('Matricule;Nom;Banque;Compte;Montant;Devise');
    expect($csv)->toContain('Coris');
    expect($csv)->toContain('250000');
    // seuls les virements figurent (pas les espÃƒÆ’Ã‚Â¨ces)
    expect(substr_count($csv, "\r\n"))->toBe(2); // en-tÃƒÆ’Ã‚Âªte + 1 virement
});
