<?php

/**
 * [Imputation automatique] Un encaissement saisi SANS imputation est imputé
 * d'office sur les factures ouvertes du client, la plus ancienne d'abord (FIFO),
 * jusqu'à épuisement du montant. Un acompte reste non imputé ; une imputation
 * manuelle saisie garde la priorité.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClientPaymentService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function afCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'AF-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'AF Co'], ['email' => 'af@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function afSetup(): array
{
    $co = afCompany();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $client = Client::factory()->create();
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);

    return [$co, $client, $cash];
}

function afInvoice(Company $co, Client $client, int $ttc, string $issuedAt): Invoice
{
    return Invoice::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'FA-AF-' . uniqid(),
        'status' => 'emise', 'issued_at' => $issuedAt,
        'total_ttc' => $ttc, 'net_to_pay' => $ttc, 'remaining_amount' => $ttc, 'paid_amount' => 0,
    ]);
}

it('impute automatiquement en FIFO sur les factures ouvertes (plus ancienne d\'abord)', function () {
    [$co, $client, $cash] = afSetup();
    $ancienne = afInvoice($co, $client, 100000, '2026-06-01');
    $recente  = afInvoice($co, $client, 200000, '2026-07-01');

    $payment = app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => 150000, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'status' => 'confirme',
        // AUCUNE imputation saisie
    ]);

    $payment->refresh();
    expect((int) $payment->allocated_amount)->toBe(150000)
        ->and((int) $payment->unallocated_amount)->toBe(0)
        ->and((int) $ancienne->fresh()->remaining_amount)->toBe(0)          // soldée en premier
        ->and($ancienne->fresh()->status)->toBe('payee')
        ->and((int) $recente->fresh()->remaining_amount)->toBe(150000)      // 200k − 50k
        ->and($recente->fresh()->status)->toBe('partiellement_payee');
});

it('laisse un acompte volontairement non imputé', function () {
    [$co, $client, $cash] = afSetup();
    afInvoice($co, $client, 100000, '2026-06-01');

    $payment = app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => 80000, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'status' => 'confirme', 'is_acompte' => true,
    ]);

    expect((int) $payment->fresh()->unallocated_amount)->toBe(80000)
        ->and($payment->allocations()->count())->toBe(0);
});

it('respecte une imputation manuelle saisie (pas d\'auto par-dessus)', function () {
    [$co, $client, $cash] = afSetup();
    $ancienne = afInvoice($co, $client, 100000, '2026-06-01');
    $ciblee   = afInvoice($co, $client, 200000, '2026-07-01');

    $payment = app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => 50000, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'status' => 'confirme',
        'allocations' => [['invoice_id' => $ciblee->id, 'allocated_amount' => 50000]],
    ]);

    expect((int) $ancienne->fresh()->remaining_amount)->toBe(100000)  // intacte
        ->and((int) $ciblee->fresh()->remaining_amount)->toBe(150000)
        ->and($payment->allocations()->count())->toBe(1);
});

it('l\'excédent au-delà des factures ouvertes reste non affecté (crédit client)', function () {
    [$co, $client, $cash] = afSetup();
    afInvoice($co, $client, 100000, '2026-06-01');

    $payment = app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => 130000, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'status' => 'confirme',
    ]);

    $payment->refresh();
    expect((int) $payment->allocated_amount)->toBe(100000)
        ->and((int) $payment->unallocated_amount)->toBe(30000);
});

// [Lot 1 annulations] Annuler un encaissement inverse TOUT : facture restaurée,
// allocations supprimées, écriture extournée, caisse débitée, statut annulé.
it('annule un encaissement : facture restaurée, GL extourné, caisse restituée', function () {
    [$co, $user, $client0] = afSetup();
    
    $client = Client::factory()->create();
    $inv = afInvoice($co, $client, 59000, '2026-01-10');
    $cash = \App\Models\CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);

    $svc = app(\App\Services\ClientPaymentService::class);
    $payment = $svc->create([
        'client_id' => $client->id, 'amount' => 59000, 'status' => 'confirme',
        'method' => 'especes', 'payment_date' => now()->toDateString(), 'cash_account_id' => $cash->id,
    ]);
    expect((int) $inv->fresh()->remaining_amount)->toBe(0)
        ->and((int) $cash->fresh()->current_balance)->toBe(59000);
    $glCountBefore = \App\Models\JournalEntry::count();

    $svc->cancel($payment->fresh(), 'Erreur de saisie caissier');

    $inv->refresh();
    expect($payment->fresh()->status)->toBe('annule')
        ->and((int) $inv->remaining_amount)->toBe(59000)
        ->and($inv->status)->toBe('emise')
        ->and(\App\Models\ClientPaymentAllocation::where('client_payment_id', $payment->id)->count())->toBe(0)
        // Caisse restituée (débit inverse) → solde revenu à 0
        ->and((int) $cash->fresh()->current_balance)->toBe(0)
        // Une écriture d'extourne a été créée (équilibrée par construction)
        ->and(\App\Models\JournalEntry::count())->toBeGreaterThan($glCountBefore);

    // Double annulation refusée
    expect(fn () => $svc->cancel($payment->fresh(), 'encore'))->toThrow(\RuntimeException::class);
});

// [Lot 2 idempotence] Double soumission et paiement annulé.
it('refuse la sur-imputation séquentielle et l\'imputation d\'un paiement annulé', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 100000, '2026-02-01');

    $svc = app(\App\Services\ClientPaymentService::class);
    $payment = $svc->create([
        'client_id' => $client->id, 'amount' => 60000, 'status' => 'confirme',
        'is_acompte' => true, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'force_duplicate' => true,
    ]);

    // 1re imputation : 60 000 → OK, plus rien de disponible
    $svc->addAllocation($payment->fresh(), $inv->id, 60000);
    expect((int) $payment->fresh()->unallocated_amount)->toBe(0);

    // 2e clic identique : refusé (dépasse le non-imputé)
    expect(fn () => $svc->addAllocation($payment->fresh(), $inv->id, 60000))
        ->toThrow(\RuntimeException::class);

    // Paiement annulé : plus imputable
    $p2 = $svc->create([
        'client_id' => $client->id, 'amount' => 10000, 'status' => 'confirme',
        'is_acompte' => true, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'force_duplicate' => true,
    ]);
    $svc->cancel($p2->fresh(), 'test annulation');
    expect(fn () => $svc->addAllocation($p2->fresh(), $inv->id, 5000))
        ->toThrow(\RuntimeException::class);
});

// [Lot 2 idempotence] Double validation de facture refusée (garde + lock).
it('refuse la double validation d\'une facture', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 50000, '2026-03-01');
    $inv->update(['status' => 'brouillon', 'subtotal_ht' => 50000, 'total_tax' => 0]);
    $p = \App\Models\Product::factory()->create();
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'Ligne', 'quantity' => 1,
        'unit_price' => 50000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 50000, 'line_tax' => 0, 'line_total_ttc' => 50000,
    ]);

    $svc = app(\App\Services\InvoiceService::class);
    $svc->validate($inv->fresh());
    expect(fn () => $svc->validate($inv->fresh()))->toThrow(\RuntimeException::class);
});

// [Preuve §1] Facture réglée par DEUX paiements : annuler l'un ne touche que
// SES allocations — l'autre paiement reste intact, la facture redevient
// partiellement payée avec le bon reste dû.
it('annulation partielle : seul le paiement annulé est défait, l\'autre reste', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 59000, '2026-04-01');

    $svc = app(\App\Services\ClientPaymentService::class);
    $p1 = $svc->create(['client_id' => $client->id, 'amount' => 30000, 'status' => 'confirme', 'method' => 'especes', 'payment_date' => now()->toDateString(), 'cash_account_id' => $cash->id]);
    $p2 = $svc->create(['client_id' => $client->id, 'amount' => 29000, 'status' => 'confirme', 'method' => 'especes', 'payment_date' => now()->toDateString(), 'cash_account_id' => $cash->id, 'force_duplicate' => true]);
    expect((int) $inv->fresh()->remaining_amount)->toBe(0)
        ->and($inv->fresh()->status)->toBe('payee');

    $svc->cancel($p1->fresh(), 'Erreur de saisie sur le premier règlement');

    $inv->refresh();
    expect((int) $inv->remaining_amount)->toBe(30000)          // seul p1 (30 000) défait
        ->and((int) $inv->paid_amount)->toBe(29000)            // p2 intact
        ->and($inv->status)->toBe('partiellement_payee')
        ->and(\App\Models\ClientPaymentAllocation::where('client_payment_id', $p2->id)->count())->toBe(1)
        ->and($p2->fresh()->status)->toBe('confirme')
        // Caisse : 59 000 entrés, 30 000 restitués
        ->and((int) $cash->fresh()->current_balance)->toBe(29000);
});

// [Preuve §1] Un encaissement qui finance un OF ACTIF est inannulable.
it('refuse d\'annuler un encaissement finançant un OF actif', function () {
    [$co, $client, $cash] = afSetup();
    $client->update(['payment_mode' => 'comptant']);
    $p = \App\Models\Product::factory()->create(['production_mode' => 'mto']);
    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-OFA-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100000,
    ]);
    \App\Modules\Production\Models\ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-OFA-' . uniqid(), 'status' => 'en_cours',
        'order_id' => $order->id, 'product_id' => $p->id, 'quantity_requested' => 10,
    ]);

    $svc = app(\App\Services\ClientPaymentService::class);
    $acompte = $svc->create([
        'client_id' => $client->id, 'amount' => 100000, 'status' => 'confirme',
        'is_acompte' => true, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'cash_account_id' => $cash->id, 'force_duplicate' => true,
    ]);

    expect(fn () => $svc->cancel($acompte->fresh(), 'tentative annulation'))
        ->toThrow(\RuntimeException::class, 'production EN COURS');
});

// [Preuve §7] Une facture SANS lignes est refusée à la validation (garde
// d'équilibre comptable) — le test précédent l'avait révélé par accident,
// celui-ci le fige en invariant.
it('refuse de valider une facture sans lignes (écriture déséquilibrée)', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 50000, '2026-05-01');
    $inv->update(['status' => 'brouillon']);

    expect(fn () => app(\App\Services\InvoiceService::class)->validate($inv->fresh()))
        ->toThrow(\RuntimeException::class);
});
