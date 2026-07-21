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
