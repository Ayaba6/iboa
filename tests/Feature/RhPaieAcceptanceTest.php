<?php

/**
 * [CDC — Critère d'acceptation RH / Paie]
 *
 * « Le module RH/Paie sera accepté lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 * Le calcul de paie (brut, CNSS 5.5%, IUTS), la validation d'un run, le paiement et
 * les déclarations sont déjà couverts (PayrollServiceTest, PayrollPaymentTest,
 * PayrollDeclarationTest). Ce test complète les dimensions restantes du critère :
 *   - COMPTABILISATION : run validé → écriture de paie SYSCOHADA équilibrée
 *   - CONGÉS : demande → approbation → solde décrémenté (statuts + impact)
 *   - CONTRÔLE : approbation refusée si solde de congés insuffisant
 *   - DROITS : accès paie refusé sans rh.payroll.view
 *   - DOCUMENTS : livre de paie (PDF)
 *
 * Réutilise les fixtures globales de PayrollServiceTest (payrollCompany, payrollAdmin,
 * setupPayrollSettings, createTestEmployee).
 */

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PayrollAccountingService;
use App\Services\PayrollService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function rhpCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2025'], ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'RHP Co'], ['email' => 'rhp@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function rhpAdmin(): User
{
    $co = rhpCompany();
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function rhpSettings(Company $co): void
{
    PayrollSetting::firstOrCreate(['company_id' => $co->id], [
        'smig' => 45000, 'cnss_employee_rate' => 5.5, 'cnss_employer_rate' => 16.0,
        'cnss_employer_pension_rate' => 8.5, 'cnss_employer_rp_rate' => 1.5, 'cnss_employer_pf_rate' => 6.0,
        'cnss_ceiling' => 800000, 'cnss_annual_ceiling' => 9600000,
        'effort_paix_rate' => 1.0, 'effort_paix_enabled' => true,
        'work_days_month' => 26, 'work_hours_day' => 8, 'leave_days_year' => 30,
        'hs_rate_25' => 25.0, 'hs_rate_50' => 50.0, 'hs_rate_nuit' => 75.0,
        'anc_rate_per_year' => 2.0, 'anc_rate_max_pct' => 25.0,
        'iuts_abattement_rate' => 20.0, 'iuts_abattement_max' => 300000,
        'nb_parts_max' => 5, 'parts_per_child' => 0.5, 'parts_base_single' => 1.0,
        'parts_base_married' => 2.0, 'parts_base_widowed' => 1.5,
        'currency_code' => 'XOF', 'country_code' => 'BF',
        'iuts_brackets' => PayrollSetting::defaultIutsBrackets(),
        'iuts_family_reductions' => PayrollSetting::defaultFamilyReductions(),
        'iuts_max_charges' => 4,
    ]);
}

function rhpEmployee(Company $co, int $baseSalary = 200000): Employee
{
    $dept = Department::firstOrCreate(['company_id' => $co->id, 'name' => 'RHP Dept'], ['code' => 'RHP']);
    $employee = Employee::factory()->create([
        'company_id' => $co->id, 'department_id' => $dept->id,
        'first_name' => 'Moussa', 'last_name' => 'Traore', 'hiring_date' => '2020-01-01', 'status' => 'actif',
    ]);
    EmployeeContract::create([
        'employee_id' => $employee->id, 'company_id' => $co->id, 'contract_type' => 'CDI',
        'start_date' => '2020-01-01', 'base_salary' => $baseSalary, 'is_current' => true, 'status' => 'actif',
    ]);

    return $employee;
}

it('comptabilise un run de paie validé par une écriture SYSCOHADA équilibrée', function () {
    $user    = rhpAdmin();
    $company = rhpCompany();
    rhpSettings($company);
    rhpEmployee($company, 200000);
    // Plan comptable minimal : classes SYSCOHADA requises par la comptabilisation de paie
    foreach (range(1, 8) as $n) {
        \App\Models\AccountClass::firstOrCreate(['company_id' => $company->id, 'number' => $n], ['name' => 'Classe '.$n]);
    }
    $this->actingAs($user);

    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 3, 'period_year' => 2025, 'notes' => '']);
    $svc->calculate($run);
    $svc->validate($run);

    // Comptabilisation : écriture de paie SYSCOHADA. Journalisée à la validation
    // (run.journal_entry_id) ou à la demande via generateForRun.
    $run = $run->fresh();
    $entry = $run->journal_entry_id
        ? JournalEntry::find($run->journal_entry_id)
        : app(PayrollAccountingService::class)->generateForRun($run);

    expect($entry)->not->toBeNull()
        ->and($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->total_debit)->toBe($entry->total_credit)
        ->and($entry->total_debit)->toBeGreaterThan(0);
});

it('approuve un congé et décrémente le solde (statuts + impact)', function () {
    $user    = rhpAdmin();
    $company = rhpCompany();
    $employee = rhpEmployee($company, 150000);
    $this->actingAs($user);

    $type = LeaveType::create(['company_id' => $company->id, 'name' => 'Congé annuel', 'code' => 'CA', 'days_per_year' => 30]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => '2025-06-02', 'end_date' => '2025-06-06', 'days' => 5, 'status' => 'en_attente', 'created_by' => $user->id,
    ]);

    $this->post(route('rh.conges.approve', $leave))->assertRedirect();

    expect($leave->fresh()->status)->toBe('approuve');
    $balance = LeaveBalance::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->first();
    expect((float) $balance->taken_days)->toBe(5.0);
});

it('refuse l’approbation d’un congé si le solde est insuffisant (contrôle)', function () {
    $user    = rhpAdmin();
    $company = rhpCompany();
    $employee = rhpEmployee($company, 150000);
    $this->actingAs($user);

    $type = LeaveType::create(['company_id' => $company->id, 'name' => 'Congé exceptionnel', 'code' => 'CE', 'days_per_year' => 2]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => '2025-06-02', 'end_date' => '2025-06-06', 'days' => 5, 'status' => 'en_attente', 'created_by' => $user->id,
    ]);

    $this->post(route('rh.conges.approve', $leave))->assertRedirect();

    // Solde insuffisant (2 acquis < 5 demandés) → reste en attente
    expect($leave->fresh()->status)->toBe('en_attente');
});

it('refuse l’accès à la paie sans la permission rh.payroll.view (droits)', function () {
    $company = rhpCompany();
    Permission::firstOrCreate(['name' => 'rh.payroll.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits_paie', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/rh/paie')->assertForbidden();
});

it('génère le livre de paie en PDF (documents)', function () {
    $user    = rhpAdmin();
    $company = rhpCompany();
    rhpSettings($company);
    rhpEmployee($company, 180000);
    $this->actingAs($user);

    $svc = app(PayrollService::class);
    $run = $svc->createRun(['period_month' => 4, 'period_year' => 2025, 'notes' => '']);
    $svc->calculate($run);
    $svc->validate($run);

    $resp = $this->get(route('rh.paie.livre-paie', ['mois' => 4, 'annee' => 2025]));
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');
});
