<?php

use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
| Annonce du moteur, imprimée avant la première ligne du rapport.
|
| `php artisan test --env=testing.mysql` a servi une session entière à produire
| des lignes « parité MySQL ✓ » : cette commande tourne sur SQLite, `phpunit.xml`
| imposant `DB_CONNECTION=sqlite` en `force="true"`. Le rapport était vert et
| n'attestait rien. Aucun test ne pouvait le dire — une course SQLite lancée par
| erreur est parfaitement cohérente avec elle-même.
|
| Le seul remède est de rendre le moteur ILLISIBLE autrement qu'en le lisant :
| il s'affiche désormais en tête de chaque exécution, quelle que soit la commande.
| La suite MySQL se lance avec `pest -c phpunit.mysql.xml`.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // L'annonce doit être faite APRÈS le démarrage de l'application.
        // Une première version lisait l'environnement du processus au
        // chargement de ce fichier : elle affichait « MYSQL / iboa_erp » pour
        // une course qui s'exécutait sur SQLite en mémoire, les surcharges
        // `force="true"` de phpunit n'étant pas encore appliquées. Une bannière
        // fausse est pire qu'une bannière absente.
        static $annonce = false;
        if (! $annonce) {
            $annonce = true;
            $c = \Illuminate\Support\Facades\DB::connection();
            fwrite(STDOUT, sprintf(
                "\n  Moteur réel : %s / %s\n\n",
                strtoupper($c->getDriverName()),
                (string) $c->getDatabaseName()
            ));
        }

        $this->refreshDatabase();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// ─── Helpers paie BF (partagés PayrollBfComplianceTest / RegulationVersioningTest) ───

function bfSetting(): \App\Models\PayrollSetting
{
    return new \App\Models\PayrollSetting([
        'iuts_brackets'          => \App\Models\PayrollSetting::defaultIutsBrackets(),
        'iuts_family_reductions' => \App\Models\PayrollSetting::defaultFamilyReductions(),
        'iuts_max_charges'       => 4,
    ]);
}

function bfCompany(): \App\Models\Company
{
    $fy = \App\Models\FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return \App\Models\Company::firstOrCreate(
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

function bfSettings(\App\Models\Company $company): void
{
    \App\Models\PayrollSetting::firstOrCreate(['company_id' => $company->id], [
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
        'iuts_brackets' => \App\Models\PayrollSetting::defaultIutsBrackets(),
        'iuts_family_reductions' => \App\Models\PayrollSetting::defaultFamilyReductions(),
        'iuts_max_charges' => 4,
    ]);
}

function bfAccountClasses(\App\Models\Company $company): void
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

function bfEmployee(\App\Models\Company $company, int $baseSalary): \App\Models\Employee
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
