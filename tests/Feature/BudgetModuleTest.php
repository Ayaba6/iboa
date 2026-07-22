<?php

use App\Models\AccountingBudget;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Role;

function budAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'BUD'], ['email' => 'b@b.io', 'current_fiscal_year_id' => $fy->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

// [QA 1-4] Module budgets : seul domaine sans aucun test.

it('crée un budget comptable et refuse une période inversée', function () {
    $this->actingAs(budAdmin());

    $this->post(route('comptabilite.budgets.store'), [
        'code' => 'BUD-2026', 'label' => 'Budget annuel', 'period_from' => 1, 'period_to' => 12,
    ])->assertRedirect();
    expect(AccountingBudget::where('code', 'BUD-2026')->exists())->toBeTrue();

    $this->post(route('comptabilite.budgets.store'), [
        'code' => 'BUD-KO', 'label' => 'Inversé', 'period_from' => 6, 'period_to' => 3,
    ])->assertSessionHasErrors('period_to');
    expect(AccountingBudget::where('code', 'BUD-KO')->exists())->toBeFalse();
});

it('refuse l\'accès budgets sans authentification', function () {
    $this->get('/comptabilite/budgets')->assertRedirect();
});
