<?php

/**
 * [Phase 2.4 — RH/paie] Profils salariés VARIÉS sur le moteur de calcul réel
 * (pas un seul cas standard) : HS, absence, prêt, plafond CNSS, charges de
 * famille, ancienneté. Chaque profil vérifie les montants EXACTS.
 */

use App\Models\EmployeeLoan;
use App\Models\PayrollItem;
use App\Models\PayrollVariable;
use App\Services\PayrollService;

uses(\Tests\Concerns\RefreshDatabase::class);

function profRun(array $employees, ?callable $withVariables = null): \App\Models\PayrollRun
{
    $company = bfCompany();
    bfSettings($company);
    bfAccountClasses($company);
    test()->actingAs(bfAdmin());
    foreach ($employees as $cb) {
        $cb($company);
    }
    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 7, 'period_year' => 2026, 'notes' => '']);
    if ($withVariables) {
        $withVariables($run);
    }
    $svc->calculate($run->fresh());

    return $run->fresh('items');
}

it('profil heures supplémentaires : HS 25 % payées au taux horaire majoré', function () {
    $empRef = null;
    $run = profRun(
        [function ($co) use (&$empRef) { $empRef = bfEmployee($co, 150_000); }],
        function ($run) use (&$empRef) {
            PayrollVariable::create([
                'payroll_run_id' => $run->id, 'employee_id' => $empRef->id,
                'type' => 'hs_25', 'label' => 'HS jour', 'qty' => 10, 'amount' => 0, 'is_gain' => true,
            ]);
        }
    );

    $item = PayrollItem::where('employee_id', $empRef->id)->first();
    $settings = \App\Models\PayrollSetting::firstOrFail();
    // Taux horaire = 150 000 / (jours × heures) ; HS = 10 h × taux × 1,25
    $hourly = 150_000 / ($settings->work_days_month * $settings->work_hours_day);
    $attendu = (int) round($hourly * 10 * (1 + $settings->hs_rate_25 / 100));
    expect((int) $item->hs_25_amount)->toBe($attendu)
        ->and((int) $item->salaire_brut)->toBeGreaterThan(150_000);
});

it('profil absence injustifiée : base proratisée, brut réduit', function () {
    $empRef = null;
    $run = profRun(
        [function ($co) use (&$empRef) { $empRef = bfEmployee($co, 150_000); }],
        function ($run) use (&$empRef) {
            PayrollVariable::create([
                'payroll_run_id' => $run->id, 'employee_id' => $empRef->id,
                'type' => 'absence_injust', 'label' => 'Absence', 'qty' => 5, 'amount' => 0, 'is_gain' => false,
            ]);
        }
    );

    $item = PayrollItem::where('employee_id', $empRef->id)->first();
    $days = \App\Models\PayrollSetting::firstOrFail()->work_days_month;
    $attendu = (int) round(150_000 * ($days - 5) / $days);
    expect((int) $item->base_salary)->toBe($attendu);
});

it('profil prêt actif : retenue mensuelle déduite du net et solde décrémenté à la validation', function () {
    $empRef = null;
    $run = profRun([function ($co) use (&$empRef) {
        $empRef = bfEmployee($co, 300_000);
        EmployeeLoan::create([
            'company_id' => $co->id, 'employee_id' => $empRef->id, 'loan_number' => 'PRT-' . uniqid(),
            'amount' => 120_000, 'monthly_deduction' => 30_000, 'remaining_balance' => 120_000,
            'start_date' => '2026-01-01', 'status' => 'actif',
        ]);
    }]);

    $item = PayrollItem::where('employee_id', $empRef->id)->first();
    expect((int) $item->loan_deductions)->toBe(30_000);

    // Validation → solde du prêt décrémenté
    app(PayrollService::class)->validate($run->fresh());
    expect((int) EmployeeLoan::where('employee_id', $empRef->id)->value('remaining_balance'))->toBe(90_000);
});

it('profil haut salaire : CNSS salarié plafonnée à 800 000', function () {
    $empRef = null;
    profRun([function ($co) use (&$empRef) { $empRef = bfEmployee($co, 1_500_000); }]);

    $item = PayrollItem::where('employee_id', $empRef->id)->first();
    $s = \App\Models\PayrollSetting::firstOrFail();
    // Brut = base + ancienneté (embauche 2020 → 6 ans × 2 % = 12 %) > plafond
    $attenduCnss = (int) round(800_000 * $s->cnss_employee_rate / 100);
    expect((int) $item->cnss_employee)->toBe($attenduCnss);
});

it('profil bas salaire sans charge vs 3 charges : IUTS réduit de 12 %', function () {
    $e1 = $e2 = null;
    profRun([
        function ($co) use (&$e1) { $e1 = bfEmployee($co, 200_000); },
        function ($co) use (&$e2) {
            $e2 = bfEmployee($co, 200_000);
            $e2->update(['nb_children' => 3]);
        },
    ]);

    $i1 = PayrollItem::where('employee_id', $e1->id)->first();
    $i2 = PayrollItem::where('employee_id', $e2->id)->first();
    // Même brut, même base — l'IUTS du salarié à 3 charges est réduit de 12 %
    expect((int) $i2->iuts)->toBe((int) $i1->iuts - (int) round($i1->iuts * 0.12))
        ->and((int) $i2->salaire_net)->toBeGreaterThan((int) $i1->salaire_net);
});

it('profil avance sur salaire : la variable avance_deduction réduit le net', function () {
    $avecAvance = $sansAvance = null;
    profRun(
        [
            function ($co) use (&$avecAvance) { $avecAvance = bfEmployee($co, 250_000); },
            function ($co) use (&$sansAvance) { $sansAvance = bfEmployee($co, 250_000); },
        ],
        function ($run) use (&$avecAvance) {
            PayrollVariable::create([
                'payroll_run_id' => $run->id, 'employee_id' => $avecAvance->id,
                'type' => 'avance_deduction', 'label' => 'Avance juillet', 'qty' => 1,
                'amount' => 40_000, 'is_gain' => false,
            ]);
        }
    );

    // Deux salariés identiques dans le MÊME run : seul l'écart d'avance sépare les nets
    $item = PayrollItem::where('employee_id', $avecAvance->id)->first();
    $ref  = PayrollItem::where('employee_id', $sansAvance->id)->first();
    expect((int) $item->salaire_net)->toBe((int) $ref->salaire_net - 40_000);
});

// [Phase 2.4] Immuabilité : un run validé ne se recalcule plus, ses items sont figés.
it('refuse le recalcul d\'un run validé et fige ses items', function () {
    $empRef = null;
    $run = profRun([function ($co) use (&$empRef) { $empRef = bfEmployee($co, 200_000); }]);
    $svc = app(PayrollService::class);
    $svc->validate($run->fresh());

    $netAvant = (int) PayrollItem::where('employee_id', $empRef->id)->value('salaire_net');

    try {
        $svc->calculate($run->fresh());
        $this->fail('Le recalcul aurait dû être refusé.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('validé');
    }
    expect((int) PayrollItem::where('employee_id', $empRef->id)->value('salaire_net'))->toBe($netAvant);
});

// [Phase 2.4] Confidentialité : sans rh.payroll.view, la paie est invisible (403).
it('refuse l\'accès à la paie sans la permission rh.payroll.view', function () {
    $company = bfCompany();
    bfSettings($company);
    $intrus = \App\Models\User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'intrus-stock', 'guard_name' => 'web']);
    $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'stocks.view', 'guard_name' => 'web']));
    $intrus->assignRole($role);
    $this->actingAs($intrus);

    $this->get('/rh/paie')->assertForbidden();
    $this->get('/rh/paie/livre-paie')->assertForbidden();
});
