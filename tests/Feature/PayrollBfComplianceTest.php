<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\PayrollSetting;
use App\Services\PayrollService;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * Conformité fiscale et sociale Burkina Faso.
 *
 * IUTS : barème progressif officiel (annexe fiscale CGI BF) appliqué au revenu
 * imposable TOTAL + réduction pour charges de famille sur l'IUTS brut.
 * CNSS : plafond mensuel 800 000, ventilation patronale 8,5/1,5/6.
 */

function bfSetting(): PayrollSetting
{
    return new PayrollSetting([
        'iuts_brackets'          => PayrollSetting::defaultIutsBrackets(),
        'iuts_family_reductions' => PayrollSetting::defaultFamilyReductions(),
        'iuts_max_charges'       => 4,
    ]);
}

function bfCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'BF Compliance Co'],
        ['email' => 'bf@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function bfAdmin(): \App\Models\User
{
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create(['company_id' => bfCompany()->id]);
    $user->assignRole($role);
    return $user;
}

function bfSettings(Company $company): void
{
    PayrollSetting::firstOrCreate(['company_id' => $company->id], [
        'smig' => 45_000,
        'cnss_employee_rate' => 5.5, 'cnss_employer_rate' => 16.0,
        'cnss_employer_pension_rate' => 8.5, 'cnss_employer_rp_rate' => 1.5, 'cnss_employer_pf_rate' => 6.0,
        'cnss_ceiling' => 800_000, 'cnss_annual_ceiling' => 9_600_000,
        'effort_paix_rate' => 1.0, 'effort_paix_enabled' => true,
        'work_days_month' => 26, 'work_hours_day' => 8, 'leave_days_year' => 30,
        'hs_rate_25' => 25.0, 'hs_rate_50' => 50.0, 'hs_rate_nuit' => 75.0,
        'anc_rate_per_year' => 2.0, 'anc_rate_max_pct' => 25.0,
        'iuts_abattement_rate' => 20.0,
        'nb_parts_max' => 5, 'parts_per_child' => 0.5, 'parts_base_single' => 1.0,
        'parts_base_married' => 2.0, 'parts_base_widowed' => 1.5,
        'currency_code' => 'XOF', 'country_code' => 'BF',
        'iuts_brackets' => PayrollSetting::defaultIutsBrackets(),
        'iuts_family_reductions' => PayrollSetting::defaultFamilyReductions(),
        'iuts_max_charges' => 4,
    ]);
}

function bfAccountClasses(Company $company): void
{
    foreach ([4 => 'Comptes de tiers', 6 => 'Comptes de charges'] as $number => $label) {
        \Illuminate\Support\Facades\DB::table('account_classes')->insertOrIgnore([
            'company_id' => $company->id,
            'number'     => $number,
            'name'       => $label,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function bfEmployee(Company $company, int $baseSalary): \App\Models\Employee
{
    $dept = \App\Models\Department::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'BF Dept'],
        ['code' => 'BFC']
    );
    $employee = \App\Models\Employee::factory()->create([
        'company_id'    => $company->id,
        'department_id' => $dept->id,
        'hiring_date'   => '2020-01-01',
        'status'        => 'actif',
    ]);
    \App\Models\EmployeeContract::create([
        'employee_id'   => $employee->id,
        'company_id'    => $company->id,
        'contract_type' => 'CDI',
        'start_date'    => '2020-01-01',
        'base_salary'   => $baseSalary,
        'is_current'    => true,
        'status'        => 'actif',
    ]);
    return $employee;
}

describe('IUTS — barème progressif officiel BF', function () {

    it('calcule l\'IUTS brut attendu sur chaque borne du barème', function (int $imposable, int $attendu) {
        expect(bfSetting()->computeIutsDetail($imposable, 0)['brut'])->toBe($attendu);
    })->with([
        'zéro'              => [0,               0],
        '20 000 (T1)'       => [20_000,          0],
        '30 000 (fin T1)'   => [30_000,          0],
        '30 001 (début T2)' => [30_001,          0],       // 1 F × 12,1 % arrondi
        '50 000 (fin T2)'   => [50_000,      2_420],       // 20 000 × 12,1 %
        '50 001 (début T3)' => [50_001,      2_420],
        '80 000 (fin T3)'   => [80_000,      6_590],       // + 30 000 × 13,9 %
        '80 001 (début T4)' => [80_001,      6_590],
        '120 000 (fin T4)'  => [120_000,    12_870],       // + 40 000 × 15,7 %
        '120 001 (début T5)'=> [120_001,    12_870],
        '170 000 (fin T5)'  => [170_000,    22_070],       // + 50 000 × 18,4 %
        '170 001 (début T6)'=> [170_001,    22_070],
        '250 000 (fin T6)'  => [250_000,    39_430],       // + 80 000 × 21,7 %
        '250 001 (début T7)'=> [250_001,    39_430],
        '500 000'           => [500_000,   101_930],       // + 250 000 × 25 %
        '1 000 000'         => [1_000_000, 226_930],       // + 750 000 × 25 %
    ]);

    it('détaille l\'impôt par tranche et la somme égale l\'IUTS brut', function () {
        $d = bfSetting()->computeIutsDetail(500_000, 0);

        expect($d['tranches'])->toHaveCount(7)
            ->and($d['tranches'][0]['taux'])->toBe(0.0)
            ->and($d['tranches'][1]['taux'])->toBe(12.1)
            ->and($d['tranches'][6]['taux'])->toBe(25.0)
            ->and($d['tranches'][6]['a'])->toBeNull()
            ->and((int) round(array_sum(array_column($d['tranches'], 'impot'))))->toBe($d['brut']);
    });

    it('applique la réduction pour charges de famille sur l\'IUTS brut', function (int $charges, float $tauxAttendu) {
        $d = bfSetting()->computeIutsDetail(500_000, $charges);

        expect($d['reduction_rate'])->toBe($tauxAttendu)
            ->and($d['reduction'])->toBe((int) round(101_930 * $tauxAttendu / 100))
            ->and($d['net'])->toBe(101_930 - (int) round(101_930 * $tauxAttendu / 100));
    })->with([
        '0 charge'   => [0,  0.0],
        '1 charge'   => [1,  8.0],
        '2 charges'  => [2, 10.0],
        '3 charges'  => [3, 12.0],
        '4 charges'  => [4, 14.0],
    ]);

    it('plafonne les charges de famille à iuts_max_charges', function () {
        $s = bfSetting();

        expect($s->computeCharges(9))->toBe(4)
            ->and($s->computeIutsDetail(500_000, 9)['reduction_rate'])->toBe(14.0)
            ->and($s->computeIuts(500_000, 9))->toBe($s->computeIuts(500_000, 4));
    });

    it('retourne 0 pour un imposable négatif ou nul quel que soit le nombre de charges', function () {
        expect(bfSetting()->computeIuts(0, 3))->toBe(0)
            ->and(bfSetting()->computeIuts(-5_000, 2))->toBe(0);
    });
});

describe('CNSS — plafond et ventilation patronale BF', function () {

    it('plafonne la base CNSS à 800 000 et ventile le patronal 8,5/1,5/6', function (int $salaire, int $baseAttendue) {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, $salaire);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => (int) date('n'), 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);

        $item = $run->items()->first();

        expect($item->cnss_base)->toBeLessThanOrEqual(800_000)
            ->and($item->cnss_employee)->toBe((int) round($item->cnss_base * 5.5 / 100))
            ->and($item->cnss_employer_pension)->toBe((int) round($item->cnss_base * 8.5 / 100))
            ->and($item->cnss_employer_rp)->toBe((int) round($item->cnss_base * 1.5 / 100))
            ->and($item->cnss_employer_pf)->toBe((int) round($item->cnss_base * 6.0 / 100))
            ->and($item->cnss_employer)->toBe(
                $item->cnss_employer_pension + $item->cnss_employer_rp + $item->cnss_employer_pf
            );

        // Le salaire de base seul ne dépasse le plafond que pour les gros salaires ;
        // l'assertion clé est le plafonnement, pas la base exacte (l'ancienneté
        // s'ajoute au brut cotisable).
        if ($baseAttendue === 800_000) {
            expect($item->cnss_base)->toBe(800_000);
        }
    })->with([
        'SMIG 45 000'      => [45_000,        0],
        '500 000'          => [500_000,       0],
        '800 001 → plafond'=> [800_001, 800_000],
        '1 500 000 → plafond' => [1_500_000, 800_000],
    ]);
});

describe('Snapshot des paramètres de calcul', function () {

    it('capture les paramètres réglementaires au calcul du run', function () {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, 200_000);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => 2, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $run->refresh();

        $snap = $run->calculation_parameters_snapshot;

        expect($snap)->not->toBeNull()
            ->and($snap['iuts_brackets'])->toBe(PayrollSetting::defaultIutsBrackets())
            ->and($snap['iuts_family_reductions'])->toBe(PayrollSetting::defaultFamilyReductions())
            ->and($snap['cnss_ceiling'])->toBe(800_000)
            ->and($snap['cnss_employer_pension_rate'])->toEqual(8.5)
            ->and($snap['cnss_employer_rp_rate'])->toEqual(1.5)
            ->and($snap['cnss_employer_pf_rate'])->toEqual(6)   // JSON: 6.0 → 6
            ->and($snap['smig'])->toBe(45_000)
            ->and($snap['regulation_version'])->toStartWith('BF-');
    });

    it('un changement de barème après calcul ne modifie pas le snapshot du run', function () {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, 200_000);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => 3, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $iutsAvant = $run->fresh()->total_iuts;

        // Sabotage du barème APRÈS calcul
        PayrollSetting::where('company_id', $company->id)
            ->update(['iuts_brackets' => json_encode([[9_999_999_999, 50]])]);
        PayrollSetting::clearCache($company->id);

        $run->refresh();
        expect($run->calculation_parameters_snapshot['iuts_brackets'])
            ->toBe(PayrollSetting::defaultIutsBrackets())
            ->and($run->total_iuts)->toBe($iutsAvant);
    });
});

describe('Validation transactionnelle et idempotence', function () {

    it('refuse une double validation du même run', function () {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, 150_000);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => 4, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $svc->validate($run);

        expect(fn() => $svc->validate($run->fresh()))
            ->toThrow(\RuntimeException::class);

        // Une seule écriture pour ce run
        $entryId = $run->fresh()->journal_entry_id;
        expect($entryId)->not->toBeNull()
            ->and(\App\Models\JournalEntry::where('reference', "PAIE-2025-4")->count())->toBe(1);
    });

    it('annule TOUTE la validation si la comptabilisation échoue (pas de run validé sans écriture)', function () {
        $company = bfCompany();
        bfSettings($company);
        // PAS de setupAccountClasses → la comptabilisation échouera
        $this->actingAs(bfAdmin());
        bfEmployee($company, 150_000);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => 5, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);

        expect(fn() => $svc->validate($run))->toThrow(\RuntimeException::class);

        $run->refresh();
        expect($run->status)->toBe('calcule')          // rollback complet
            ->and($run->journal_entry_id)->toBeNull()  // pas d'écriture orpheline
            ->and(\App\Models\JournalEntry::where('reference', 'PAIE-2025-5')->count())->toBe(0);
    });

    it('ventile la CNSS patronale en trois lignes comptables distinctes', function () {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, 300_000);

        $svc = app(PayrollService::class);
        $run = $svc->createRun(['period_month' => 6, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $svc->validate($run);

        $entry  = \App\Models\JournalEntry::with('lines')->find($run->fresh()->journal_entry_id);
        $labels = $entry->lines->pluck('label')->join(' | ');

        expect($labels)->toContain('pension')
            ->and($labels)->toContain('risques professionnels')
            ->and($labels)->toContain('prestations familiales');

        // Équilibre débit/crédit
        expect((int) $entry->lines->sum('debit'))->toBe((int) $entry->lines->sum('credit'));
    });
});
