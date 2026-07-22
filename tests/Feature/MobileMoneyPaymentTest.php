<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\ExternalTransaction;
use App\Services\Integrations\PaymentConfirmationService;

// [Chemins parallèles] Un paiement mobile money confirmé doit produire un
// encaissement COMPLET : numéro, statut confirmé, allocation, facture soldée —
// l'ancien chemin créait un ClientPayment brut invisible de la balance.
it('mobile money confirmé = encaissement central avec allocation et facture soldée', function () {
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MM'], ['email' => 'mm@mm.io', 'current_fiscal_year_id' => $fy->id]);
    $client = Client::factory()->create();

    $invoice = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id,
        'number' => 'FA-MM-' . uniqid(), 'status' => 'emise', 'issued_at' => now(),
        'subtotal_ht' => 50000, 'total_tax' => 9000, 'total_ttc' => 59000, 'remaining_amount' => 59000,
    ]);

    $tx = ExternalTransaction::create([
        'internal_reference' => 'OM-' . uniqid(), 'provider' => 'orange_money', 'type' => 'payment',
        'amount' => 59000, 'currency' => 'XOF', 'status' => 'pending',
        'invoice_id' => $invoice->id, 'client_id' => $client->id, 'direction' => 'inbound',
    ]);

    expect(app(PaymentConfirmationService::class)->confirm($tx))->toBeTrue();

    $payment = \App\Models\ClientPayment::find($tx->fresh()->client_payment_id);
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('confirme')
        ->and($payment->number)->not->toBeNull()
        ->and($payment->allocations()->count())->toBe(1);

    $invoice->refresh();
    expect((int) $invoice->remaining_amount)->toBe(0)
        ->and($invoice->status)->toBe('payee');

    // Idempotence : re-confirmer ne double rien
    app(PaymentConfirmationService::class)->confirm($tx->fresh());
    expect(\App\Models\ClientPayment::count())->toBe(1);
});
