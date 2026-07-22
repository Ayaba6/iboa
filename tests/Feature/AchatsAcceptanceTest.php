<?php

/**
 * [CDC — Critère d'acceptation Achats]
 *
 * « Le module Achats sera accepté lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 *   - STATUTS + BOUT-EN-BOUT : DA → PO → Réception → Facture → Paiement → Retour
 *   - IMPACTS AUTO : entrée de stock à la réception, écritures SYSCOHADA équilibrées
 *     (facture 601/4452/401, paiement 401/521), sortie de stock au retour fournisseur
 *   - CONTRÔLES : PO brouillon → ni réception ni facture ; DA non approuvée → pas de PO
 *   - DROITS : accès au module refusé sans permission
 *   - DOCUMENTS : génération PDF bon de commande
 *   - HISTORIQUE : audit_logs alimentés sur le cycle de vie de la facture fournisseur
 */

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierReturnService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function achCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'ACH'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'ACH Co'], ['email' => 'ach@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function achAdmin(): User
{
    $co = achCompany();
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

function achSupplier(): Supplier
{
    return Supplier::create([
        'code' => 'F-'.random_int(1000, 9999), 'type' => 'entreprise', 'name' => 'Aciers Sahel',
        'is_active' => true, 'country' => 'Burkina Faso', 'avg_delivery_days' => 7, 'balance' => 0,
    ]);
}

/** DA approuvée → PO confirmé pour un article stockable. */
function achConfirmedPo(Company $co, Supplier $supplier, Product $product, int $qty = 100, int $price = 5000): PurchaseOrder
{
    $unit = Unit::firstOrCreate(['name' => 'Kg ACH'], ['abbreviation' => 'kgach']);
    $prSvc = app(PurchaseRequestService::class);
    $pr = $prSvc->create([
        'department' => 'Production',
        'items' => [['product_id' => $product->id, 'description' => 'Bobine acier', 'quantity' => $qty, 'estimated_price' => $price, 'unit_id' => $unit->id]],
    ]);
    $prSvc->submit($pr);
    $prSvc->approve($pr);
    $po = $prSvc->convertToPurchaseOrder($pr, $supplier->id);
    app(PurchaseOrderService::class)->confirm($po);

    return $po->fresh();
}

it('exécute la chaîne Achats bout-en-bout avec statuts, stock et écritures comptables', function () {
    $user = achAdmin();
    $co   = achCompany();
    $supplier = achSupplier();
    $product  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'allow_negative_stock' => false]);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-ACH'], ['name' => 'Dépôt ACH', 'is_default' => true, 'is_active' => true]);

    $po = achConfirmedPo($co, $supplier, $product, 100, 5000);
    expect($po->status)->toBe('confirme');

    $poSvc = app(PurchaseOrderService::class);

    // ── Réception : entrée de stock automatique ──────────────────────────────
    $reception = $poSvc->createReception($po);
    $stockBefore = (float) ProductStock::where('product_id', $product->id)->value('quantity') ?: 0.0;

    $this->post(route('achats.receptions.validate', $reception), [
        'warehouse_id' => $wh->id,
        'items' => [$reception->items->first()->id => ['received_quantity' => 100]],
    ])->assertRedirect();

    $reception->refresh();
    $po->refresh();
    $stockAfter = (float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity');

    expect($reception->status)->toBe('valide')
        ->and($stockAfter - $stockBefore)->toBe(100.0)
        ->and($po->status)->toBe('recu');

    // ── Facture fournisseur : statut + écriture comptable équilibrée ──────────
    $invoice = $poSvc->createSupplierInvoice($po);
    app(SupplierInvoiceService::class)->validate($invoice);
    $invoice->refresh();
    expect($invoice->status)->toBe('validee');

    $glInvoice = JournalEntry::where('reference', $invoice->number)->first();
    expect($glInvoice)->not->toBeNull()
        ->and($glInvoice->total_debit)->toBe($glInvoice->total_credit);

    // ── Paiement fournisseur : écriture comptable équilibrée ──────────────────
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'banque', 'current_balance' => 1_000_000, 'is_active' => true]);
    $payment = app(SupplierPaymentService::class)->create([
        'supplier_id' => $supplier->id, 'cash_account_id' => $cash->id,
        'amount' => (int) $invoice->total_ttc, 'method' => 'virement', 'payment_date' => now()->toDateString(),
        'allocations' => [['supplier_invoice_id' => $invoice->id, 'allocated_amount' => (int) $invoice->total_ttc]],
    ]);
    $glPayment = JournalEntry::where('reference', $payment->number)->first();
    expect($glPayment)->not->toBeNull()
        ->and($glPayment->total_debit)->toBe($glPayment->total_credit);

    // [Scénario E QA] La facture fournisseur est soldée et passe « payee ».
    $invoice->refresh();
    expect((int) $invoice->remaining_amount)->toBe(0)
        ->and($invoice->status)->toBe('payee');

    // ── Retour fournisseur : sortie de stock automatique ─────────────────────
    $return = app(SupplierReturnService::class)->create([
        'supplier_id' => $supplier->id, 'returned_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'Bobine non conforme', 'quantity' => 10, 'unit_price' => 5000, 'tax_rate_value' => 0]],
    ]);
    app(SupplierReturnService::class)->validate($return);
    $stockAfterReturn = (float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity');
    expect($stockAfter - $stockAfterReturn)->toBe(10.0);
});

it('interdit réception et facture tant que le PO est en brouillon (contrôle)', function () {
    $co = achCompany();
    achAdmin();
    $supplier = achSupplier();
    $product  = Product::factory()->create(['is_stockable' => true]);
    $unit = Unit::firstOrCreate(['name' => 'Kg ACH'], ['abbreviation' => 'kgach']);

    $prSvc = app(PurchaseRequestService::class);
    $pr = $prSvc->create(['department' => 'Production', 'items' => [['product_id' => $product->id, 'description' => 'X', 'quantity' => 5, 'estimated_price' => 1000, 'unit_id' => $unit->id]]]);
    $prSvc->submit($pr);
    $prSvc->approve($pr);
    $po = $prSvc->convertToPurchaseOrder($pr, $supplier->id); // reste brouillon

    $poSvc = app(PurchaseOrderService::class);
    expect(fn () => $poSvc->createReception($po))->toThrow(\RuntimeException::class);
    expect(fn () => $poSvc->createSupplierInvoice($po))->toThrow(\RuntimeException::class);
});

it('interdit la conversion d’une DA non approuvée (contrôle)', function () {
    achAdmin();
    $supplier = achSupplier();
    $product  = Product::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'Kg ACH'], ['abbreviation' => 'kgach']);

    $pr = app(PurchaseRequestService::class)->create([
        'department' => 'Achats',
        'items' => [['product_id' => $product->id, 'description' => 'Y', 'quantity' => 1, 'estimated_price' => 1000, 'unit_id' => $unit->id]],
    ]); // jamais soumise/approuvée

    expect(fn () => app(PurchaseRequestService::class)->convertToPurchaseOrder($pr, $supplier->id))
        ->toThrow(\RuntimeException::class);
});

it('refuse l’accès au module Achats sans la permission (droits)', function () {
    $co = achCompany();
    Permission::firstOrCreate(['name' => 'purchase_orders.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits_achats', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/achats')->assertForbidden();
});

it('génère le document PDF du bon de commande (documents)', function () {
    achAdmin();
    $co = achCompany();
    $supplier = achSupplier();
    $product  = Product::factory()->create(['is_stockable' => true]);
    $po = achConfirmedPo($co, $supplier, $product, 10, 3000);

    $resp = $this->get(route('achats.commandes.pdf', $po));
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');
});

it('journalise l’historique du cycle de vie de la facture fournisseur (historique)', function () {
    achAdmin();
    $co = achCompany();
    $supplier = achSupplier();
    $product  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-ACH'], ['name' => 'Dépôt ACH', 'is_default' => true, 'is_active' => true]);

    $po = achConfirmedPo($co, $supplier, $product, 20, 4000);
    $poSvc = app(PurchaseOrderService::class);
    $reception = $poSvc->createReception($po);
    $this->post(route('achats.receptions.validate', $reception), [
        'warehouse_id' => $wh->id,
        'items' => [$reception->items->first()->id => ['received_quantity' => 20]],
    ]);
    $invoice = $poSvc->createSupplierInvoice($po->fresh());
    app(SupplierInvoiceService::class)->validate($invoice);

    $logs = AuditLog::where('model_type', SupplierInvoice::class)->where('model_id', $invoice->id)->count();
    expect($logs)->toBeGreaterThan(0);
});

// [FIX-TRESO recette] Le bouton « Payer » de la fiche FF doit débiter la caisse.
it('recordPayment débite le compte de trésorerie et journalise la transaction', function () {
    $co = achCompany();
    achAdmin();
    $supplier = achSupplier();

    $inv = \App\Models\SupplierInvoice::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id,
        'number' => 'FF-TRESO-' . uniqid(), 'status' => 'validee',
        'received_at' => now()->toDateString(),
        'subtotal_ht' => 10000, 'total_tax' => 1800, 'total_ttc' => 11800,
        'paid_amount' => 0, 'remaining_amount' => 11800,
    ]);
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'banque', 'current_balance' => 50000, 'is_active' => true]);

    app(\App\Services\SupplierInvoiceService::class)->recordPayment($inv, [
        'amount' => 11800, 'payment_date' => now()->toDateString(),
        'cash_account_id' => $cash->id,
    ]);

    expect((int) $cash->fresh()->current_balance)->toBe(50000 - 11800)
        ->and(\App\Models\CashTransaction::where('reference_type', 'SupplierPayment')->where('cash_account_id', $cash->id)->where('type', 'debit')->count())->toBe(1);
});
