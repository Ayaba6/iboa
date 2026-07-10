<?php

/**
 * [Maquette X3 Paramètres comptables] Surcharge des comptes par défaut avec
 * fallback SYSCOHADA — zéro régression sur le moteur comptable.
 */

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\AccountingSetting;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccountingService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function accSetAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'ACC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'AccSet Co'], ['email' => 'accset@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function callAccount(AccountingService $svc, Company $co, string $key): Account
{
    $m = new ReflectionMethod($svc, 'account');
    $m->setAccessible(true);

    return $m->invoke($svc, $co, $key);
}

it('utilise le compte SYSCOHADA par défaut quand aucun paramétrage n\'existe', function () {
    $this->actingAs(accSetAdmin());
    $co = currentCompany();

    $account = callAccount(app(AccountingService::class), $co, 'ventes');
    // Le plan SYSCOHADA standard pour les ventes commence par 70
    expect($account->code)->toStartWith('70');
});

it('surcharge le compte de vente depuis les paramètres comptables', function () {
    $this->actingAs(accSetAdmin());
    $co = currentCompany();

    $class7 = AccountClass::firstOrCreate(['company_id' => $co->id, 'number' => 7], ['name' => 'Produits']);
    $custom = Account::create([
        'company_id' => $co->id, 'account_class_id' => $class7->id,
        'code' => '7099', 'name' => 'Ventes personnalisées', 'type' => 'produit',
        'is_detail' => true, 'is_active' => true, 'debit_balance' => 0, 'credit_balance' => 0,
    ]);

    AccountingSetting::current()->update(['account_ventes' => $custom->id]);

    $account = callAccount(app(AccountingService::class), $co, 'ventes');
    expect($account->id)->toBe($custom->id)->and($account->code)->toBe('7099');
});

it('affiche et enregistre l\'écran des paramètres comptables', function () {
    $this->actingAs(accSetAdmin());

    $this->get(route('comptabilite.parametres.edit'))
        ->assertOk()
        ->assertSee('Paramètres comptables')
        ->assertSee('Comptes par défaut');

    $this->put(route('comptabilite.parametres.update'), [
        'referentiel'            => 'SYSCOHADA révisé',
        'lettrage_auto'          => 1,
        'validation_obligatoire' => 1,
        'analytique_obligatoire' => 0,
        'axe_analytique_1'       => 'Production',
    ])->assertRedirect();

    $s = AccountingSetting::current();
    expect($s->referentiel)->toBe('SYSCOHADA révisé')
        ->and($s->lettrage_auto)->toBeTrue()
        ->and($s->analytique_obligatoire)->toBeFalse()
        ->and($s->axe_analytique_1)->toBe('Production');
});
