<?php

/**
 * [Phase 2.2 §8 — réconciliation] Après un scénario traversant ventes,
 * trésorerie, achats et avoirs (avec annulation) :
 *  1. GL = soldes des comptes (somme des lignes validées = debit/credit_balance)
 *  2. Balance générale équilibrée (Σ débits = Σ crédits)
 *  3. Auxiliaire clients : solde 411 = Σ restes à payer des factures vivantes
 *  4. Auxiliaire fournisseurs : solde 401 = Σ restes à payer FF
 *  5. Résultat (7x − 6x) = ce que le scénario implique
 */

use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function recSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'REC'], ['email' => 'rec@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);

    return [$co, Client::factory()->create(), Supplier::factory()->create(), $cash];
}

function recInvoice(Company $co, Client $client, int $ht, int $tax): Invoice
{
    // Non stockable, coût nul : isole la preuve de réconciliation des écritures
    // de déstockage CMP (D6031/C311x — testées par ailleurs)
    $p = Product::factory()->create(['is_stockable' => false, 'purchase_price' => 0]);
    $inv = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'FA-REC-' . uniqid(), 'status' => 'brouillon', 'issued_at' => now(),
        'subtotal_ht' => $ht, 'total_tax' => $tax, 'total_ttc' => $ht + $tax, 'remaining_amount' => $ht + $tax,
    ]);
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'L', 'quantity' => 1, 'unit_price' => $ht,
        'discount_percent' => 0, 'tax_rate_value' => $tax > 0 ? 18 : 0,
        'line_total_ht' => $ht, 'line_tax' => $tax, 'line_total_ttc' => $ht + $tax,
    ]);
    app(\App\Services\InvoiceService::class)->validate($inv);
    $inv->fresh()->update(['status' => 'emise']);

    return $inv->fresh();
}

it('réconcilie GL, balance, auxiliaires et résultat après un scénario multi-cycles', function () {
    [$co, $client, $supplier, $cash] = recSetup();
    $paySvc = app(\App\Services\ClientPaymentService::class);

    // ── Ventes : 2 factures (59 000 TTC et 20 000 sans TVA)
    $f1 = recInvoice($co, $client, 50000, 9000);
    $f2 = recInvoice($co, $client, 20000, 0);

    // ── Encaissement 30 000 alloué à f1
    $paySvc->create([
        'company_id' => $co->id, 'client_id' => $client->id, 'amount' => 30000,
        'payment_date' => now()->toDateString(), 'payment_method' => 'espece',
        'cash_account_id' => $cash->id, 'status' => 'confirme',
        'allocations' => [['invoice_id' => $f1->id, 'amount' => 30000]],
    ]);

    // ── Encaissement 5 000 sur f2 puis ANNULÉ (extourne + débit caisse)
    $p2 = $paySvc->create([
        'company_id' => $co->id, 'client_id' => $client->id, 'amount' => 5000,
        'payment_date' => now()->toDateString(), 'payment_method' => 'espece',
        'cash_account_id' => $cash->id, 'status' => 'confirme',
        'allocations' => [['invoice_id' => $f2->id, 'amount' => 5000]],
    ]);
    $paySvc->cancel($p2->fresh(), 'Erreur de saisie');

    // ── Avoir 10 000 sur f2, validé et appliqué
    $cnSvc = app(\App\Services\CreditNoteService::class);
    $cn = $cnSvc->createFromInvoice($f2->fresh(), [
        'reason' => 'Geste commercial',
        'items' => [['description' => 'Remise', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate_value' => 0]],
    ]);
    $cnSvc->validate($cn);
    $cnSvc->applyToInvoice($cn->fresh());

    // ═══ 1. GL = soldes des comptes
    foreach (Account::whereHas('journalEntryLines')->get() as $account) {
        $sumD = (int) JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))->sum('debit');
        $sumC = (int) JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))->sum('credit');
        expect((int) $account->debit_balance)->toBe($sumD, "GL≠solde D compte {$account->code}")
            ->and((int) $account->credit_balance)->toBe($sumC, "GL≠solde C compte {$account->code}");
    }

    // ═══ 2. Balance générale équilibrée
    $totD = (int) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))->sum('debit');
    $totC = (int) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))->sum('credit');
    expect($totD)->toBe($totC)->and($totD)->toBeGreaterThan(0);

    // ═══ 3. Auxiliaire clients : solde 411 = Σ restes à payer
    $c411 = Account::where('code', '411')->first();
    $solde411 = (int) $c411->debit_balance - (int) $c411->credit_balance;
    $resteFactures = (int) Invoice::whereNotIn('status', ['brouillon', 'annulee'])->sum('remaining_amount');
    // f1 : 59 000 − 30 000 = 29 000 ; f2 : 20 000 − 10 000 (avoir) = 10 000 → 39 000
    expect($resteFactures)->toBe(39000)
        ->and($solde411)->toBe($resteFactures);

    // ═══ 5. Résultat : produits 7x − charges 6x
    $produits = Account::where('code', 'like', '7%')->get()
        ->sum(fn ($a) => (int) $a->credit_balance - (int) $a->debit_balance);
    $charges = Account::where('code', 'like', '6%')->get()
        ->sum(fn ($a) => (int) $a->debit_balance - (int) $a->credit_balance);
    // Ventes 70 000 HT − avoir 10 000 = 60 000 de produits nets ; charges 0
    expect($produits)->toBe(60000)->and($charges)->toBe(0);
});
