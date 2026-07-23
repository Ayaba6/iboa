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

it('autorise l\'annulation avec la permission dédiée treasury.cancel', function () {
    secUserWith(['treasury.view', 'payments.view', 'treasury.cancel']);
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
    secUserWith(['treasury.view', 'treasury.cancel', 'payments.view']);
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

// [SEC-PHASE2 §3] treasury.write (saisie courante) n'est PAS un passe-partout :
// l'annulation exige la permission dédiée treasury.cancel.
it('refuse l\'annulation d\'un encaissement à un porteur de treasury.write seul', function () {
    secUserWith(['treasury.view', 'treasury.write', 'payments.view']);
    $co = secCompany();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 50000, 'is_active' => true]);
    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'ENC-W-' . uniqid(), 'amount' => 5000, 'payment_date' => now(),
        'payment_method' => 'espece', 'cash_account_id' => $cash->id, 'status' => 'confirme',
    ]);

    $this->post(route('tresorerie.encaissements.cancel', $pay), ['reason' => 'Tentative writer'])
        ->assertForbidden();
    expect($pay->fresh()->status)->toBe('confirme');
});

// [SEC-PHASE2 §3] Créer une réception n'autorise plus à la VALIDER (effet stock).
it('refuse la validation de réception à un porteur de receptions.create seul', function () {
    secUserWith(['receptions.view', 'receptions.create']);
    $co = secCompany();
    $reception = \App\Models\Reception::create([
        'company_id' => $co->id, 'supplier_id' => \App\Models\Supplier::factory()->create()->id,
        'number' => 'REC-SEC-' . uniqid(), 'status' => 'brouillon', 'received_at' => now(), 'type' => 'totale',
    ]);

    $this->post(route('achats.receptions.validate', $reception), ['items' => []])
        ->assertForbidden();
    expect($reception->fresh()->status)->toBe('brouillon');
});

// [SEC-PHASE2 §2] Maker-checker actif : l'auteur ne valide pas sa propre opération.
it('maker-checker : refuse l\'auto-approbation d\'un décaissement quand le contrôle est actif', function () {
    config(['security.maker_checker.enabled' => true]);
    $u = secUserWith(['treasury.view', 'treasury.validate', 'payments.view']);
    $co = secCompany();
    $pay = \App\Models\SupplierPayment::create([
        'company_id' => $co->id, 'supplier_id' => \App\Models\Supplier::factory()->create()->id,
        'number' => 'DEC-MC-' . uniqid(), 'amount' => 100000, 'payment_date' => now(),
        'payment_method' => 'espece', 'status' => 'en_attente',
        'validation_status' => 'en_attente_validation', 'created_by' => $u->id,
    ]);

    try {
        app(\App\Services\SupplierPaymentService::class)->approve($pay);
        $this->fail('L\'auto-approbation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Séparation des tâches');
    }
    // Limite connue : le log du refus est écrit dans la transaction du service,
    // que l'exception fait rollback — la journalisation fiable des refus exige
    // une connexion DB dédiée (planifiée, incrément « intégrité du journal »).
    expect($pay->fresh()->validation_status)->toBe('en_attente_validation');

    // Un AUTRE utilisateur habilité approuve
    secUserWith(['treasury.view', 'treasury.validate', 'payments.view', 'x-autre']);
    app(\App\Services\SupplierPaymentService::class)->approve($pay->fresh());
    expect($pay->fresh()->validation_status)->toBe('valide');
});

// [SEC-PHASE2 §2] Contrôle désactivé (défaut petites équipes) : l'auteur peut approuver.
it('maker-checker : inactif par défaut, l\'auteur peut approuver', function () {
    config(['security.maker_checker.enabled' => false]);
    $u = secUserWith(['treasury.view', 'treasury.validate', 'payments.view']);
    $co = secCompany();
    $pay = \App\Models\SupplierPayment::create([
        'company_id' => $co->id, 'supplier_id' => \App\Models\Supplier::factory()->create()->id,
        'number' => 'DEC-MC2-' . uniqid(), 'amount' => 100000, 'payment_date' => now(),
        'payment_method' => 'espece', 'status' => 'en_attente',
        'validation_status' => 'en_attente_validation', 'created_by' => $u->id,
    ]);

    app(\App\Services\SupplierPaymentService::class)->approve($pay);
    expect($pay->fresh()->validation_status)->toBe('valide');
});

// [SEC-PHASE2 §4] Désactiver un compte révoque ses tokens API Sanctum.
it('révoque les tokens API à la désactivation du compte', function () {
    $u = secUserWith(['treasury.view']);
    $u->createToken('mobile');
    $u->createToken('poste-2');
    expect($u->tokens()->count())->toBe(2);

    $u->update(['is_active' => false]);

    expect($u->tokens()->count())->toBe(0);
});

// [SEC-PHASE2 §6] Contrôle bénéficiaire TOUJOURS actif : un salarié ne valide
// pas son propre prêt — indépendant de la config maker-checker.
it('refuse au bénéficiaire l\'approbation de son propre prêt', function () {
    config(['security.maker_checker.enabled' => false]); // même désactivé, le contrôle bénéficiaire tient
    $u = secUserWith(['rh.loans.manage']);
    $co = secCompany();
    $emp = \App\Models\Employee::factory()->create(['company_id' => $co->id, 'user_id' => $u->id]);
    $loan = \App\Models\EmployeeLoan::create([
        'company_id' => $co->id, 'employee_id' => $emp->id, 'loan_number' => 'PRT-SEC-' . uniqid(),
        'amount' => 200000, 'monthly_deduction' => 20000, 'remaining_balance' => 200000,
        'start_date' => now(), 'status' => 'actif',
    ]);

    $res = $this->post(route('rh.prets.approve', $loan));
    $res->assertSessionHas('error');
    expect($loan->fresh()->approved_by)->toBeNull();

    // Un AUTRE utilisateur RH approuve sans problème
    secUserWith(['rh.loans.manage', 'x2']);
    $this->post(route('rh.prets.approve', $loan));
    expect($loan->fresh()->approved_by)->not->toBeNull();
});

// [SEC-PHASE2 §8] Extension du journal : authentification + validations.
it('journalise les échecs de connexion et les validations de facture', function () {
    // Échec de connexion → journal (email tenté, jamais le mot de passe)
    $this->post(route('login'), ['email' => 'inconnu@iboa.test', 'password' => 'mauvais']);
    $failed = \App\Models\AuditLog::where('action', 'auth.failed')->first();
    expect($failed)->not->toBeNull()
        ->and($failed->new_values['email'] ?? null)->toBe('inconnu@iboa.test')
        ->and(json_encode($failed->new_values))->not->toContain('mauvais');

    // Validation de facture → journal après commit
    secUserWith(['invoices.validate']);
    $co = secCompany();
    $p = \App\Models\Product::factory()->create();
    $inv = \App\Models\Invoice::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'FA-JRN-' . uniqid(), 'status' => 'brouillon', 'issued_at' => now(),
        'subtotal_ht' => 20000, 'total_tax' => 0, 'total_ttc' => 20000, 'remaining_amount' => 20000,
    ]);
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'L', 'quantity' => 1, 'unit_price' => 20000,
        'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 20000, 'line_tax' => 0, 'line_total_ttc' => 20000,
    ]);
    app(\App\Services\InvoiceService::class)->validate($inv);

    expect(\App\Models\AuditLog::where('action', 'facture.validation')->where('model_id', $inv->id)->exists())->toBeTrue();
});

// [SEC-PHASE2 §9] Chaînage du journal : altération/suppression détectée.
it('détecte l\'altération et la suppression d\'entrées du journal d\'audit', function () {
    secUserWith(['treasury.view']);
    $svc = app(\App\Services\AuditService::class);
    $svc->log('test.a');
    $svc->log('test.b');
    $svc->log('test.c');

    // Chaîne saine
    expect($svc->verifyChain())->toBe([]);

    // Altération d'une entrée (contournement du modèle, SQL direct)
    \Illuminate\Support\Facades\DB::table('audit_logs')
        ->where('action', 'test.b')->update(['action' => 'test.b.falsifie']);
    expect($svc->verifyChain())->not->toBe([]);

    // Restaure puis SUPPRIME une entrée du milieu → rupture prev_hash
    \Illuminate\Support\Facades\DB::table('audit_logs')
        ->where('action', 'test.b.falsifie')->update(['action' => 'test.b']);
    expect($svc->verifyChain())->toBe([]);
    \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'test.b')->delete();
    expect($svc->verifyChain())->not->toBe([]);
});

// [SEC-PHASE2 §11] Retrait de permission en cours de session : effet immédiat.
it('applique immédiatement le retrait d\'une permission en cours de session', function () {
    $u = secUserWith(['treasury.view', 'treasury.cancel', 'payments.view']);
    $co = secCompany();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 10000, 'is_active' => true]);
    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'ENC-RT-' . uniqid(), 'amount' => 3000, 'payment_date' => now(),
        'payment_method' => 'espece', 'cash_account_id' => $cash->id, 'status' => 'confirme',
    ]);

    // Retrait de la permission au rôle PENDANT la session (Spatie invalide son cache)
    $u->roles->first()->revokePermissionTo('treasury.cancel');

    $this->post(route('tresorerie.encaissements.cancel', $pay), ['reason' => 'Après retrait'])
        ->assertForbidden();
    expect($pay->fresh()->status)->toBe('confirme');
});
