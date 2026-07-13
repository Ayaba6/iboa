<?php

/**
 * [CDC — Critère d'acceptation Ventes]
 *
 * « Le module Ventes sera accepté lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 * Ce test vérifie chaque dimension du critère sur un article stocké (hors production) :
 *   - STATUTS + BOUT-EN-BOUT : Devis → Commande → BL → Facture → Encaissement → Avoir
 *   - IMPACTS AUTO : sortie de stock au BL, écritures SYSCOHADA équilibrées (facture,
 *     encaissement, avoir), retour en stock à l'avoir
 *   - CONTRÔLES : client bloqué → aucun document ; stock insuffisant → livraison refusée
 *   - DROITS : accès au module refusé sans permission
 *   - DOCUMENTS : génération PDF facture
 *   - HISTORIQUE : audit_logs alimentés sur le cycle de vie facture
 */

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ClientPaymentService;
use App\Services\ClientService;
use App\Services\CommercialWorkflowService;
use App\Services\CreditNoteService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\QuoteService;
use App\Services\StockService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function vaccCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'VACC-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'VACC Co'],
        ['email' => 'vacc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function vaccAdmin(): User
{
    $co = vaccCompany();
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

/** Article stocké + stock initial disponible dans le dépôt par défaut. */
function vaccStockedProduct(Company $co, int $qty = 100): array
{
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'VACC-WH'],
        ['name' => 'Dépôt VACC', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true, 'valuation_method' => 'cmp']);

    app(StockService::class)->recordMovement([
        'product_id'   => $product->id,
        'warehouse_id' => $wh->id,
        'type'         => 'entree',
        'quantity'     => $qty,
        'unit_cost'    => 6000,
        'occurred_at'  => now(),
    ]);

    return [$product, $wh];
}

/** Commande stockée menée jusqu'à une facture validée, via le flux complet contrôlé. */
function vaccValidatedInvoice(Company $co, int $qty = 10): Invoice
{
    $client = Client::factory()->create(['is_active' => true, 'is_blocked' => false]);
    $unit   = Unit::firstOrCreate(['name' => 'PC VACC'], ['abbreviation' => 'pcv']);
    $tva    = TaxRate::firstOrCreate(['name' => 'TVA18 VACC'], ['short_name' => 'TV18V', 'rate' => 18, 'is_active' => true]);
    [$product] = vaccStockedProduct($co, max(100, $qty + 50));

    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'description' => 'Tôle', 'quantity' => $qty,
            'unit_price' => 10000, 'discount_percent' => 0, 'unit_id' => $unit->id,
            'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());

    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);

    $invoice = app(DeliveryNoteService::class)->createInvoice($dn->fresh());
    app(InvoiceService::class)->validate($invoice);

    return $invoice->fresh();
}

it('exécute la chaîne Ventes bout-en-bout avec statuts, impacts stock et écritures comptables', function () {
    $user = vaccAdmin();
    $co   = vaccCompany();

    $client  = Client::factory()->create(['is_active' => true, 'is_blocked' => false]);
    $unit    = Unit::firstOrCreate(['name' => 'PC VACC'], ['abbreviation' => 'pcv']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA18 VACC'], ['short_name' => 'TV18V', 'rate' => 18, 'is_active' => true]);
    [$product, $wh] = vaccStockedProduct($co, 100);

    $item = [
        'product_id' => $product->id, 'description' => 'Tôle bac 6m',
        'quantity' => 40, 'unit_price' => 10000, 'discount_percent' => 0,
        'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
    ];

    // ── Devis → validation → conversion ──────────────────────────────────────
    $quote = app(QuoteService::class)->create(['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [$item]]);
    expect($quote->status)->toBe('brouillon');

    $wf = app(CommercialWorkflowService::class);
    $wf->submit($quote);
    $wf->validateQuote($quote->fresh());
    expect($quote->fresh()->status)->toBe('valide');

    $order = app(QuoteService::class)->convertToOrder($quote->fresh());
    expect($order->quote_id)->toBe($quote->id);

    // ── Commande : validation commerciale → confirmée ────────────────────────
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    expect($order->fresh()->status)->toBe('confirme');

    // ── Livraison : impact stock automatique (sortie) ────────────────────────
    $stockBefore = (float) \App\Models\ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity');

    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);
    $dn->refresh();
    $order->refresh();

    expect($dn->status)->toBe('valide')
        ->and($order->status)->toBe('livre');

    $stockAfter = (float) \App\Models\ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity');
    expect($stockBefore - $stockAfter)->toBe(40.0);

    // ── Facture : statut + écriture comptable équilibrée ─────────────────────
    $invoice = app(DeliveryNoteService::class)->createInvoice($dn);
    app(InvoiceService::class)->validate($invoice);
    $invoice->refresh();

    expect($invoice->status)->toBeIn(['emise', 'envoyee'])
        ->and((float) $invoice->total_ttc)->toBe(40 * 10000 * 1.18);

    $glInvoice = JournalEntry::where('reference', $invoice->number)->first();
    expect($glInvoice)->not->toBeNull()
        ->and($glInvoice->total_debit)->toBe($glInvoice->total_credit);

    // ── Encaissement : facture payée + écriture comptable ────────────────────
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true]);
    app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => $invoice->total_ttc, 'method' => 'virement', 'payment_date' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => $invoice->total_ttc]],
    ]);
    $invoice->refresh();
    expect($invoice->status)->toBe('payee')
        ->and((float) $invoice->remaining_amount)->toBe(0.0);

    // ── Avoir : retour en stock automatique + écriture comptable ─────────────
    $creditNote = app(CreditNoteService::class)->createFromInvoice($invoice, [
        'reason' => 'Retour partiel', 'items' => [[
            'product_id' => $product->id, 'description' => 'Tôle bac 6m',
            'quantity' => 10, 'unit_price' => 10000, 'tax_rate_value' => 18, 'disposition' => 'restock',
        ]],
    ]);
    app(CreditNoteService::class)->validate($creditNote);
    $creditNote->refresh();
    expect($creditNote->status)->toBe('valide');

    $retour = StockMovement::where('reference_type', 'credit_note')->where('reference_id', $creditNote->id)->where('type', 'retour_client')->first();
    expect($retour)->not->toBeNull()
        ->and((float) $retour->quantity)->toBe(10.0);

    $glCredit = JournalEntry::where('reference', $creditNote->number)->first();
    expect($glCredit)->not->toBeNull()
        ->and($glCredit->total_debit)->toBe($glCredit->total_credit);
});

it('bloque toute création de document commercial pour un client bloqué (contrôle)', function () {
    vaccAdmin();
    $blocked = Client::factory()->create(['is_blocked' => true, 'blocked_reason' => 'Impayés']);

    expect(fn () => ClientService::assertSellable($blocked))->toThrow(RuntimeException::class);
});

it('refuse la livraison quand le stock est insuffisant (contrôle + impact stock)', function () {
    $user = vaccAdmin();
    $co   = vaccCompany();
    $client = Client::factory()->create(['is_active' => true]);
    $unit   = Unit::firstOrCreate(['name' => 'PC VACC'], ['abbreviation' => 'pcv']);
    $tva    = TaxRate::firstOrCreate(['name' => 'TVA18 VACC'], ['short_name' => 'TV18V', 'rate' => 18, 'is_active' => true]);
    [$product, $wh] = vaccStockedProduct($co, 5); // seulement 5 en stock

    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'description' => 'Tôle', 'quantity' => 40,
            'unit_price' => 10000, 'discount_percent' => 0, 'unit_id' => $unit->id,
            'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);
    // Force la commande confirmée (sans passer par l'auto-génération d'OF) pour isoler
    // le contrôle de stock à la livraison.
    $order->update(['status' => 'confirme']);
    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());

    expect(fn () => app(DeliveryNoteService::class)->validate($dn))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('refuse l’accès au module Ventes sans la permission (droits)', function () {
    $co = vaccCompany();
    Permission::firstOrCreate(['name' => 'invoices.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits', 'guard_name' => 'web']); // aucune permission
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/ventes')->assertForbidden();
});

it('génère le document PDF de la facture (documents)', function () {
    vaccAdmin();
    $co = vaccCompany();
    Permission::firstOrCreate(['name' => 'invoices.view', 'guard_name' => 'web']);
    $invoice = vaccValidatedInvoice($co);

    $resp = $this->get(route('ventes.factures.pdf', $invoice));
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');
});

it('journalise l’historique des opérations sur la facture (historique)', function () {
    vaccAdmin();
    $co = vaccCompany();
    $invoice = vaccValidatedInvoice($co);

    $logs = AuditLog::where('model_type', Invoice::class)->where('model_id', $invoice->id)->count();
    expect($logs)->toBeGreaterThan(0);
});
