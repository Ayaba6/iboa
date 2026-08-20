<?php

/**
 * Encaissement client (trésorerie).
 *
 * Point métier vérifié :
 *  - encaissement total → facture payée, reste à payer = 0 ;
 *  - encaissement partiel → facture partiellement payée, reste à payer mis à jour ;
 *  - mouvement de trésorerie généré sur le compte de caisse/banque ;
 *  - pas de double encaissement (allocation refusée sur une facture déjà soldée).
 */

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClientPaymentService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cpayAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CPAY'], ['email' => 'cpay@cpay.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

/** Facture validée (émise) de 11 800 TTC pour un client donné. */
function cpayValidatedInvoice(Client $client): App\Models\Invoice
{
    $product = Product::factory()->create(['is_sellable' => true]);
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 CPAY'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article',
            'quantity' => 10, 'unit_price' => 1000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // [Ventes §21.2] La facturation est limitée aux quantités LIVRÉES. Le sujet de
    // ce test est l'encaissement, pas le circuit de livraison : on marque la ligne
    // comme livrée intégralement pour obtenir la facture de 11 800 TTC attendue.
    $order->items()->update(['delivered_quantity' => 10]);

    $invoice = app(InvoiceService::class)->createFromOrder($order->fresh());

    return app(InvoiceService::class)->validate($invoice);
}

function cpayCashAccount(): CashAccount
{
    return CashAccount::factory()->create([
        'company_id' => Company::first()->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true,
    ]);
}

it('encaissement total → facture payée, reste à payer = 0, mouvement de trésorerie', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    expect((int) $invoice->remaining_amount)->toBe(11800);

    $payment = app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'virement',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('payee');
    expect((int) $invoice->remaining_amount)->toBe(0);

    // Mouvement de trésorerie (crédit) rattaché à l'encaissement.
    $tx = CashTransaction::where('reference_type', 'ClientPayment')->where('reference_id', $payment->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('credit');
    expect((int) $tx->amount)->toBe(11800);
});

it('encaissement partiel → facture partiellement payée, reste à payer mis à jour', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 5000,
        'method'          => 'especes',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 5000]],
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('partiellement_payee');
    // 11 800 − 5 000 = 6 800 restant.
    expect((int) $invoice->remaining_amount)->toBe(6800);
    expect((int) $invoice->paid_amount)->toBe(5000);
});

it('pas de double encaissement : allocation refusée sur une facture déjà soldée', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    // Premier encaissement solde la facture.
    app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'virement',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]);
    expect($invoice->fresh()->status)->toBe('payee');

    // Second encaissement sur la même facture (payée) → refusé.
    expect(fn () => app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'cheque',
        'reference'       => 'CHQ-DUP-001',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]))->toThrow(\RuntimeException::class);

    // La facture reste soldée à 0 (aucun sur-paiement).
    expect((int) $invoice->fresh()->remaining_amount)->toBe(0);
});
