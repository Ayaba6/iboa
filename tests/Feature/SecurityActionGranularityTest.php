<?php

/**
 * [Phase 2.1 — sécurité] Granularité par action : une permission de LECTURE ne
 * suffit pas pour annuler/approuver des opérations de trésorerie, ni pour
 * approuver une commande fournisseur sans règle de seuil.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function secCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SEC'], ['email' => 'sec@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function secUserWith(array $permissions): User
{
    $co = secCompany();
    $role = Role::firstOrCreate(['name' => 'sec-role-' . md5(implode(',', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('refuse l\'annulation d\'un encaissement à un simple lecteur trésorerie', function () {
    secUserWith(['treasury.view', 'payments.view']);
    $co = secCompany();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 100000, 'is_active' => true]);
    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'ENC-SEC-' . uniqid(), 'amount' => 10000, 'payment_date' => now(),
        'payment_method' => 'espece', 'cash_account_id' => $cash->id, 'status' => 'confirme',
    ]);

    $this->post(route('tresorerie.encaissements.cancel', $pay), ['reason' => 'Tentative non autorisée'])
        ->assertForbidden();
    expect($pay->fresh()->status)->toBe('confirme'); // rien n'a bougé
});

it('autorise l\'annulation avec treasury.write', function () {
    secUserWith(['treasury.view', 'payments.view', 'treasury.write']);
    $co = secCompany();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 100000, 'is_active' => true]);
    $pay = app(\App\Services\ClientPaymentService::class)->create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'amount' => 10000, 'payment_date' => now()->toDateString(),
        'payment_method' => 'espece', 'cash_account_id' => $cash->id, 'status' => 'confirme',
        'force_duplicate' => true, // acompte sans facture : garde anti-doublon contournée sciemment
    ]);

    $this->post(route('tresorerie.encaissements.cancel', $pay), ['reason' => 'Erreur de caisse constatée'])
        ->assertRedirect();
    expect($pay->fresh()->status)->toBe('annule');
});

it('refuse l\'approbation d\'un décaissement à un lecteur (middleware treasury.validate)', function () {
    secUserWith(['treasury.view', 'payments.view']);
    $co = secCompany();
    $supplier = \App\Models\Supplier::factory()->create();
    $pay = \App\Models\SupplierPayment::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id,
        'number' => 'DEC-SEC-' . uniqid(), 'amount' => 500000, 'payment_date' => now(),
        'payment_method' => 'espece', 'status' => 'en_attente', 'validation_status' => 'en_attente_validation',
    ]);

    $this->post(route('tresorerie.decaissements.approve', $pay))->assertForbidden();
    expect($pay->fresh()->validation_status)->toBe('en_attente_validation');
});

it('refuse l\'approbation d\'une CF sans règle de seuil à un utilisateur sans droit d\'approbation', function () {
    $user = secUserWith(['purchase_orders.view']);
    $co = secCompany();
    $po = \App\Models\PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => \App\Models\Supplier::factory()->create()->id,
        'number' => 'CA-SEC-' . uniqid(), 'status' => 'brouillon',
        'approval_status' => 'en_attente', 'total_ttc' => 1000000, 'ordered_at' => now(),
    ]);

    try {
        app(\App\Services\PoApprovalService::class)->approve($po, $user);
        $this->fail('L\'approbation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('approbation');
    }
    expect($po->fresh()->approval_status)->toBe('en_attente');
});

// [SEC-PHASE2] Un compte désactivé en cours de session est déconnecté au
// premier aller-retour — le contrôle au login seul ne suffit pas.
it('coupe la session d\'un utilisateur désactivé en cours de route', function () {
    $u = secUserWith(['treasury.view']);
    // Session vivante : la requête n'aboutit PAS sur la page de login
    $first = $this->get(route('profile.edit'));
    expect($first->headers->get('Location'))->not->toBe(route('login'));

    $u->update(['is_active' => false]);

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    expect(auth()->check())->toBeFalse();
});

// [SEC-PHASE2] Les annulations financières alimentent le journal d'audit.
it('journalise l\'annulation d\'un encaissement dans audit_logs', function () {
    secUserWith(['treasury.view', 'treasury.write', 'payments.view']);
    $co = secCompany();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 50000, 'is_active' => true]);
    $pay = app(\App\Services\ClientPaymentService::class)->create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'amount' => 7000, 'payment_date' => now()->toDateString(),
        'payment_method' => 'espece', 'cash_account_id' => $cash->id, 'status' => 'confirme',
        'force_duplicate' => true,
    ]);

    app(\App\Services\ClientPaymentService::class)->cancel($pay->fresh(), 'Erreur de saisie caissier');

    $log = \App\Models\AuditLog::where('action', 'encaissement.annulation')
        ->where('model_id', $pay->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->new_values['motif'] ?? null)->toBe('Erreur de saisie caissier')
        ->and($log->user_id)->not->toBeNull();
});
