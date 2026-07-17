<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\FiscalYear;
use App\Models\IutsBracket;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PayrollService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function payrollCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Payroll Test Co'],
        ['email' => 'payroll@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function payrollAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = payrollCompany();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    return $user;
}

/**
 * Crée les paramètres de paie complets (tous les champs requis par assertComplete()).
 * Le barème IUTS utilise le format JSON [[seuil, taux], ...] de defaultIutsBrackets().
 */
function setupPayrollSettings(Company $company): PayrollSetting
{
    return PayrollSetting::firstOrCreate(
        ['company_id' => $company->id],
        [
            'smig'                 => 34664,
            'cnss_employee_rate'   => 5.5,
            'cnss_employer_rate'   => 16.0,
            'cnss_at_rate'         => 3.5,
            'cnss_ceiling'         => 800000,
            'effort_paix_rate'     => 1.0,
            'effort_paix_enabled'  => true,
            'work_days_month'      => 26,
            'work_hours_day'       => 8,
            'leave_days_year'      => 30,
            'hs_rate_25'           => 25.0,
            'hs_rate_50'           => 50.0,
            'hs_rate_nuit'         => 75.0,
            'anc_rate_per_year'    => 2.0,
            'anc_rate_max_pct'     => 25.0,
            'iuts_abattement_rate' => 20.0,
            'iuts_abattement_max'  => 300000,
            'nb_parts_max'         => 5,
            'parts_per_child'      => 0.5,
            'parts_base_single'    => 1.0,
            'parts_base_married'   => 2.0,
            'parts_base_widowed'   => 1.5,
            'currency_code'        => 'XOF',
            'country_code'         => 'BF',
            // Barème IUTS BF 2024 : [[seuil_superieur, taux%], ...]
            'iuts_brackets'        => PayrollSetting::defaultIutsBrackets(),
        ]
    );
}

function setupIutsBrackets(): void
{
    // Les tranches IUTS sont stockées dans payroll_settings.iuts_brackets (JSON)
    // La table iuts_brackets est un référentiel séparé non utilisé dans le calcul
    // => rien à créer ici, le barème est défini dans setupPayrollSettings()
}

function createTestEmployee(Company $company, int $baseSalary = 200000): Employee
{
    $dept = Department::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'Test Dept'],
        ['code' => 'TST']
    );

    $employee = Employee::factory()->create([
        'company_id'    => $company->id,
        'department_id' => $dept->id,
        'first_name'    => 'Moussa',
        'last_name'     => 'Traore',
        'hiring_date'   => '2020-01-01',
        'status'        => 'actif',
    ]);

    EmployeeContract::create([
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

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('PayrollService creation et calcul', function () {

    it('cree un PayrollRun avec les bons champs', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();

        $this->actingAs($user);

        $run = app(PayrollService::class)->createRun([
            'period_month' => 6,
            'period_year'  => 2025,
            'notes'        => 'Test juin 2025',
        ]);

        expect($run)->toBeInstanceOf(PayrollRun::class)
            ->and($run->status)->toBe('brouillon')
            ->and($run->period_month)->toBe(6)
            ->and($run->period_year)->toBe(2025)
            ->and($run->company_id)->toBe($company->id);
    });

    it('refuse le calcul quand aucun employe actif avec contrat', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();
        // Aucun employé créé volontairement

        $this->actingAs($user);
        $svc = app(PayrollService::class);

        $run = $svc->createRun(['period_month' => 5, 'period_year' => 2025, 'notes' => '']);

        expect(fn() => $svc->calculate($run))
            ->toThrow(\RuntimeException::class, 'Aucun employé actif');

        $run->refresh();
        expect($run->status)->toBe('brouillon')
            ->and($run->items()->count())->toBe(0);
    });

    it('calcule la paie et passe en statut calculee', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();
        createTestEmployee($company, 200000);

        $this->actingAs($user);
        $svc = app(PayrollService::class);

        $run = $svc->createRun(['period_month' => 7, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $run->refresh();

        expect($run->status)->toBe('calcule')
            ->and($run->employee_count)->toBeGreaterThanOrEqual(1)
            ->and((int) $run->total_brut)->toBeGreaterThan(0);
    });

    it('calcule correctement la CNSS salarie a 5.5% du salaire brut', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();
        createTestEmployee($company, 100000);

        $this->actingAs($user);
        $svc = app(PayrollService::class);

        $run = $svc->createRun(['period_month' => 8, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);

        $item = $run->items()->first();
        expect($item)->not->toBeNull();

        // CNSS salarié = 5.5% de la base CNSS (salaire brut incluant ancienneté)
        // salaire_brut = base + ancienneté + primes soumises à CNSS
        $grossSalary = (int) ($item->salaire_brut ?? $item->cnss_base ?? $item->base_salary ?? 0);
        $expected    = (int) round($grossSalary * 5.5 / 100);
        expect((int) $item->cnss_employee)->toBe($expected);
    });

    it('valide un PayrollRun calcule', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();
        createTestEmployee($company, 150000);

        $this->actingAs($user);
        $svc = app(PayrollService::class);

        $run = $svc->createRun(['period_month' => 9, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $svc->validate($run);
        $run->refresh();

        expect($run->status)->toBe('valide')
            ->and($run->validated_at)->not->toBeNull();
    });

    it('rejette un doublon sur le meme mois/annee', function () {
        $user    = payrollAdmin();
        $company = payrollCompany();
        setupPayrollSettings($company);
        setupIutsBrackets();

        $this->actingAs($user);
        $svc = app(PayrollService::class);

        $svc->createRun(['period_month' => 10, 'period_year' => 2025, 'notes' => '']);

        expect(fn () => $svc->createRun(['period_month' => 10, 'period_year' => 2025, 'notes' => '']))
            ->toThrow(\RuntimeException::class);
    });

});
