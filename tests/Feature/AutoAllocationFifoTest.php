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
    // [BUG-A3-MTO-FIN-001] Le mode était posé à 'comptant', valeur qu'aucun
    // formulaire n'accepte et qu'aucune ligne de la base ne porte. La garde
    // ci-dessous s'appuie sur `requiredBeforeProduction()`, qui retournait donc
    // null : la boucle passait au `continue` et l'annulation était autorisée.
    // Le test passait au vert sans jamais éprouver la garde qu'il nomme.
    $client->update(['payment_mode' => Client::PAYMENT_CASH]);
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

// [Lot 3 — modification post-validation] Un devis converti ou validé est figé.
it('refuse de modifier un devis converti ou validé', function () {
    [$co, $client, $cash] = afSetup();
    $p = \App\Models\Product::factory()->create(['is_sellable' => true, 'sale_price' => 1000]);

    $svc = app(\App\Services\QuoteService::class);
    $quote = $svc->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $p->id, 'description' => 'L', 'quantity' => 2, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_rate_value' => 0]],
    ]);

    // Devis validé : modification refusée
    $quote->update(['status' => \App\Models\Quote::STATUS_VALIDATED]);
    expect(fn () => $svc->update($quote->fresh(), ['notes' => 'modif silencieuse']))
        ->toThrow(\RuntimeException::class, 'validé');

    // Devis converti : figé
    $quote->update(['status' => \App\Models\Quote::STATUS_CONVERTED, 'converted_to_order_id' => null]);
    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-L3-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 2000,
    ]);
    $quote->update(['converted_to_order_id' => $order->id]);
    expect(fn () => $svc->update($quote->fresh(), ['notes' => 'modif après conversion']))
        ->toThrow(\RuntimeException::class, 'figé');

    // Brouillon : reste modifiable
    $quote->update(['status' => \App\Models\Quote::STATUS_DRAFT, 'converted_to_order_id' => null]);
    $svc->update($quote->fresh(), ['notes' => 'modif légitime']);
    expect($quote->fresh()->notes)->toBe('modif légitime');
});

// [Matrice — facture période close] L'annulation n'altère JAMAIS l'écriture
// d'origine : extourne datée du jour, lignes originales intactes.
it('annule une facture ancienne : écriture d\'origine intacte, extourne du jour', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 40000, '2026-01-15');
    $inv->update(['status' => 'brouillon', 'subtotal_ht' => 40000, 'total_tax' => 0]);
    $p = \App\Models\Product::factory()->create();
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'L', 'quantity' => 1,
        'unit_price' => 40000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 40000, 'line_tax' => 0, 'line_total_ttc' => 40000,
    ]);

    $svc = app(\App\Services\InvoiceService::class);
    $svc->validate($inv->fresh());
    $inv->fresh()->update(['status' => 'emise']);

    $origin = \App\Models\JournalEntry::where('reference', $inv->number)->first();
    // Simuler une écriture ANCIENNE (période janvier, close depuis)
    $origin->update(['entry_date' => '2026-01-15']);
    $originalLines = $origin->lines()->orderBy('id')->get(['account_id', 'debit', 'credit'])->toArray();

    $svc->cancel($inv->fresh(), 'Erreur de facturation détectée tardivement');

    $origin->refresh();
    // Écriture d'origine : lignes et date INTACTES, seulement liée à son extourne
    expect($origin->entry_date->toDateString())->toBe('2026-01-15')
        ->and($origin->lines()->orderBy('id')->get(['account_id', 'debit', 'credit'])->toArray())->toBe($originalLines)
        ->and($origin->reversed_by_entry_id)->not->toBeNull();
    // Extourne : datée du JOUR (période ouverte), équilibrée
    $reversal = \App\Models\JournalEntry::find($origin->reversed_by_entry_id);
    expect($reversal->entry_date->toDateString())->toBe(now()->toDateString())
        ->and((int) $reversal->total_debit)->toBe((int) $reversal->total_credit);
});

// [Lot 4 — agrégats] Un règlement ANNULÉ ne compte plus dans les relevés ;
// un avoir APPLIQUÉ (statut 'applique') y figure toujours.
it('relevé client : exclut règlements annulés, inclut avoirs appliqués', function () {
    [$co, $client, $cash] = afSetup();
    $inv = afInvoice($co, $client, 50000, now()->toDateString());
    $svc = app(\App\Services\ClientPaymentService::class);

    // Règlement 20 000 confirmé puis ANNULÉ (allocation défaite, extourne, caisse inversée)
    $pay = $svc->create([
        'company_id' => $co->id, 'client_id' => $client->id, 'amount' => 20000,
        'payment_date' => now()->toDateString(), 'payment_method' => 'espece',
        'cash_account_id' => $cash->id, 'status' => 'confirme',
        'allocations' => [['invoice_id' => $inv->id, 'amount' => 20000]],
    ]);
    $svc->cancel($pay->fresh(), 'Erreur de saisie');

    // Avoir 10 000 validé puis appliqué intégralement (statut → 'applique')
    $cnSvc = app(\App\Services\CreditNoteService::class);
    $cn = $cnSvc->createFromInvoice($inv->fresh(), [
        'reason' => 'Geste commercial', 'items' => [[
            'description' => 'Remise', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate_value' => 0,
        ]],
    ]);
    $cnSvc->validate($cn);
    $cnSvc->applyToInvoice($cn->fresh());
    expect($cn->fresh()->status)->toBe('applique');

    // Mêmes filtres que relevés (exports + controllers corrigés)
    $regl = \App\Models\ClientPayment::where('client_id', $client->id)
        ->whereNotIn('status', ['annule', 'rejete'])->sum('amount');
    $avoirs = \App\Models\CreditNote::where('client_id', $client->id)
        ->whereIn('status', ['valide', 'applique'])->sum('total_ttc');
    $fact = \App\Models\Invoice::where('client_id', $client->id)
        ->whereNotIn('status', ['brouillon', 'annulee'])->sum('total_ttc');

    // Solde relevé = 50 000 − 0 (règlement annulé) − 10 000 (avoir) = 40 000
    expect((int) $regl)->toBe(0)
        ->and((int) $avoirs)->toBe(10000)
        ->and((int) ($fact - $avoirs - $regl))->toBe(40000)
        // Vérité facture alignée : remaining = 40 000 (paiement défait, avoir appliqué)
        ->and((int) $inv->fresh()->remaining_amount)->toBe(40000);
});

// [POST-VALID-01] Facture annulée = numéro définitif + écritures : jamais supprimée.
it('refuse la suppression physique d\'une facture annulée', function () {
    [$co, $client] = afSetup();
    $inv = afInvoice($co, $client, 15000, now()->toDateString());
    app(\App\Services\InvoiceService::class)->cancel($inv, 'Erreur de saisie');
    expect($inv->fresh()->status)->toBe('annulee');

    try {
        app(\App\Services\InvoiceService::class)->delete($inv->fresh());
        $this->fail('La suppression aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('brouillon');
    }
    expect(\App\Models\Invoice::find($inv->id))->not->toBeNull();
});

// [Phase 2.3 — ancrage fiscal] Un document fiscalement transmis (accepté) est
// immuable : le socle est prêt AVANT tout branchement à l'administration.
it('refuse toute modification d\'une facture fiscalement transmise', function () {
    [$co, $client] = afSetup();
    $inv = afInvoice($co, $client, 25000, now()->toDateString());
    $inv->update(['status' => 'brouillon']); // seul statut modifiable, pour isoler la garde fiscale

    \App\Models\FiscalTransmission::create([
        'company_id' => $co->id, 'document_type' => \App\Models\Invoice::class, 'document_id' => $inv->id,
        'status' => 'accepte', 'external_reference' => 'DGI-2026-000123',
        'idempotency_key' => 'fiscal:' . $inv->id, 'transmitted_at' => now(), 'responded_at' => now(),
    ]);

    try {
        app(\App\Services\InvoiceService::class)->update($inv->fresh(), ['notes' => 'tentative']);
        $this->fail('La modification aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('administration fiscale');
    }
    // Une transmission REJETÉE ne verrouille pas
    \App\Models\FiscalTransmission::where('document_id', $inv->id)->update(['status' => 'rejete']);
    app(\App\Services\InvoiceService::class)->update($inv->fresh(), ['notes' => 'ok après rejet']);
    expect($inv->fresh()->notes)->toBe('ok après rejet');
});
