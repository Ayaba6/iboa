<?php

/**
 * Test end-to-end du cycle d'achat complet, conforme CDC §7 :
 *
 *   Besoin → Demande d'achat → Validation → Consultation → Commande
 *   → Réception → Facture
 *
 * Chemin A (achat direct, sans consultation) : DA → submit → approve
 *   → convertToPurchaseOrder (PO brouillon) → confirm → réception → facture
 *
 * Chemin B (achat avec consultation) : DA → submit → approve
 *   → RFQ (consultation) → markSent → recordQuote → awardToQuote (PO brouillon)
 *   → confirm → réception → facture
 *
 * Les deux chemins convergent sur le même garde-fou : un PO en brouillon ne
 * peut produire ni réception ni facture (PurchaseOrderService::RECEIVABLE_STATUSES).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use App\Services\RfqService;
use App\Services\SupplierInvoiceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Fixtures ────────────────────────────────────────────────────────────────

function purchAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = purchCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function purchCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'PF-2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'PurchaseFlow Co'],
        ['email' => 'pf@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function purchSupplier(): Supplier
{
    return Supplier::create([
        'code'      => 'FOUR-' . random_int(1000, 9999),
        'type'      => 'entreprise',
        'name'      => 'Aciers du Sahel',
        'is_active' => true,
        'country'   => 'Burkina Faso',
        'avg_delivery_days' => 7,
        'balance'   => 0,
    ]);
}

function purchProduct(): Product
{
    return Product::factory()->create([
        'is_stockable'     => true,
        'valuation_method' => 'cmp',
        'allow_negative_stock' => false,
    ]);
}

// ─── Chemin A : achat direct (sans consultation) ──────────────────────────────

describe('Chemin A — Besoin → DA → Validation → Commande → Réception → Facture', function () {

    it('parcourt DA brouillon → submit → approve → convertToPurchaseOrder → confirm → réception → facture', function () {

        $user     = purchAdmin();
        $supplier = purchSupplier();
        $product  = purchProduct();
        $unit     = Unit::firstOrCreate(['name' => 'Kg PF'], ['abbreviation' => 'kgpf']);

        $this->actingAs($user);

        // ── Étape 1+2 : Besoin → Demande d'achat ─────────────────────────────
        /** @var PurchaseRequestService $prSvc */
        $prSvc = app(PurchaseRequestService::class);

        $pr = $prSvc->create([
            'department'    => 'Production',
            'justification' => 'Réassort matière première',
            'needed_at'     => now()->addDays(15)->toDateString(),
            'items'         => [[
                'product_id'      => $product->id,
                'description'     => 'Bobine acier galvanisé',
                'quantity'        => 100,
                'estimated_price' => 5_000,
                'unit_id'         => $unit->id,
            ]],
        ]);

        expect($pr->status)->toBe('brouillon');

        // ── Étape 3 : Validation (submit + approve) ──────────────────────────
        $prSvc->submit($pr);
        $pr->refresh();
        expect($pr->status)->toBe('soumis')
            ->and($pr->submitted_at)->not->toBeNull();

        $prSvc->approve($pr);
        $pr->refresh();
        expect($pr->status)->toBe('approuve')
            ->and($pr->approved_by)->toBe($user->id);

        // ── Étape 4 : Commande (conversion directe, pas de consultation) ─────
        $po = $prSvc->convertToPurchaseOrder($pr, $supplier->id);
        $pr->refresh();

        expect($po)->toBeInstanceOf(PurchaseOrder::class)
            ->and($po->status)->toBe('brouillon')
            ->and($po->supplier_id)->toBe($supplier->id)
            ->and($po->items)->toHaveCount(1)
            ->and($pr->status)->toBe('converti')
            ->and($pr->purchase_order_id)->toBe($po->id);

        // ── Garde-fou : pas de réception/facture tant que le PO est brouillon ─
        /** @var PurchaseOrderService $poSvc */
        $poSvc = app(PurchaseOrderService::class);

        expect(fn () => $poSvc->createReception($po))->toThrow(\RuntimeException::class);
        expect(fn () => $poSvc->createSupplierInvoice($po))->toThrow(\RuntimeException::class);

        // ── Étape 5 : Confirmer la commande ──────────────────────────────────
        $poSvc->confirm($po);
        $po->refresh();
        expect($po->status)->toBe('confirme');

        // ── Étape 6 : Réception ───────────────────────────────────────────────
        $reception = $poSvc->createReception($po);
        $po->refresh();

        expect($reception)->toBeInstanceOf(Reception::class)
            ->and($reception->status)->toBe('brouillon')
            ->and($reception->purchase_order_id)->toBe($po->id)
            ->and($reception->items)->toHaveCount(1)
            ->and($po->status)->toBe('partiellement_recu');

        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['code' => 'WH-PF'],
            ['name' => 'Dépôt PF', 'company_id' => purchCompany()->id, 'is_active' => true, 'is_default' => true]
        );

        $stockBefore = (float) \App\Models\ProductStock::where('product_id', $product->id)->value('quantity');

        $this->post(route('achats.receptions.validate', $reception), [
            'warehouse_id' => $warehouse->id,
            'items' => [
                $reception->items->first()->id => ['received_quantity' => 100],
            ],
        ])->assertRedirect();

        $reception->refresh();
        $po->refresh();
        $stockAfter = (float) \App\Models\ProductStock::where('product_id', $product->id)->value('quantity');

        expect($reception->status)->toBe('valide')
            ->and($stockAfter - $stockBefore)->toBe(100.0)
            ->and($po->status)->toBe('recu');

        // ── Étape 7 : Facture fournisseur ─────────────────────────────────────
        $invoice = $poSvc->createSupplierInvoice($po);

        expect($invoice)->toBeInstanceOf(SupplierInvoice::class)
            ->and($invoice->supplier_id)->toBe($supplier->id)
            ->and($invoice->status)->toBe('recue')
            ->and($invoice->items)->toHaveCount(1);

        $invoice->update(['supplier_invoice_number' => 'FF-' . uniqid()]); // [ACHATS #8] numéro fournisseur avant validation
        app(SupplierInvoiceService::class)->validate($invoice->fresh());
        $invoice->refresh();

        expect($invoice->status)->toBe('validee')
            ->and($invoice->validated_at)->not->toBeNull();
    });
});

// ─── Chemin B : achat avec consultation (RFQ) ─────────────────────────────────

describe('Chemin B — Besoin → DA → Validation → Consultation → Commande → Réception → Facture', function () {

    it('parcourt DA approuvée → RFQ → markSent → recordQuote → awardToQuote → confirm → réception → facture', function () {

        $user      = purchAdmin();
        $supplier  = purchSupplier();
        $product   = purchProduct();
        $unit      = Unit::firstOrCreate(['name' => 'Kg PF2' ], ['abbreviation' => 'kgpf2']);

        $this->actingAs($user);

        // ── Besoin → DA → Validation ──────────────────────────────────────────
        $prSvc = app(PurchaseRequestService::class);
        $pr = $prSvc->create([
            'department'    => 'Production',
            'justification' => 'Nouvel arrivage tubes',
            'needed_at'     => now()->addDays(20)->toDateString(),
            'items'         => [[
                'product_id'      => $product->id,
                'description'     => 'Tubes acier',
                'quantity'        => 50,
                'estimated_price' => 8_000,
                'unit_id'         => $unit->id,
            ]],
        ]);
        $prSvc->submit($pr);
        $prSvc->approve($pr);
        $pr->refresh();
        expect($pr->status)->toBe('approuve');

        // ── Consultation : RFQ ────────────────────────────────────────────────
        /** @var RfqService $rfqSvc */
        $rfqSvc = app(RfqService::class);

        $rfq = $rfqSvc->create([
            'title'        => 'Consultation tubes acier',
            'supplier_ids' => [$supplier->id],
            'items'        => [[
                'product_id'  => $product->id,
                'description' => 'Tubes acier 50mm',
                'quantity'    => 50,
                'unit_id'     => $unit->id,
            ]],
        ]);

        expect($rfq->status)->toBe('brouillon')
            ->and($rfq->rfqSuppliers)->toHaveCount(1);

        $rfqSvc->markSent($rfq);
        $rfq->refresh();
        expect($rfq->status)->toBe('envoyee');

        $rfqSupplier = $rfq->rfqSuppliers->first();
        $quote = $rfqSvc->recordQuote($rfq, [
            'rfq_supplier_id' => $rfqSupplier->id,
            'items' => [[
                'rfq_item_id' => $rfq->items->first()->id,
                'unit_price'  => 7_500,
                'tax_rate'    => 18,
            ]],
        ]);
        $rfq->refresh();

        expect($rfq->status)->toBe('recue')
            ->and((float) $quote->total_ttc)->toBe(50 * 7_500 * 1.18);

        // ── Commande : attribution du marché ─────────────────────────────────
        $po = $rfqSvc->awardToQuote($rfq, $quote);
        $rfq->refresh();

        expect($po->status)->toBe('brouillon')
            ->and($po->supplier_id)->toBe($supplier->id)
            ->and($rfq->status)->toBe('cloturee')
            ->and($rfq->awarded_quote_id)->toBe($quote->id);

        // ── Validation finale + réception + facture ──────────────────────────
        /** @var PurchaseOrderService $poSvc */
        $poSvc = app(PurchaseOrderService::class);
        $poSvc->confirm($po);
        $po->refresh();
        expect($po->status)->toBe('confirme');

        $reception = $poSvc->createReception($po);
        expect($reception->items)->toHaveCount(1);

        $invoice = $poSvc->createSupplierInvoice($po);
        expect($invoice->status)->toBe('recue');
    });
});

// ─── Garde-fous ────────────────────────────────────────────────────────────────

describe('Règles métier achats', function () {

    it('bloque la confirmation d\'un PO déjà confirmé', function () {
        $user     = purchAdmin();
        $supplier = purchSupplier();
        $product  = purchProduct();
        $unit     = Unit::firstOrCreate(['name' => 'Kg PF3'], ['abbreviation' => 'kgpf3']);

        $this->actingAs($user);

        $prSvc = app(PurchaseRequestService::class);
        $pr = $prSvc->create([
            'department' => 'Maintenance',
            'items'      => [[
                'product_id' => $product->id, 'description' => 'Pièce', 'quantity' => 2,
                'estimated_price' => 10_000, 'unit_id' => $unit->id,
            ]],
        ]);
        $prSvc->submit($pr);
        $prSvc->approve($pr);
        $po = $prSvc->convertToPurchaseOrder($pr, $supplier->id);

        $poSvc = app(PurchaseOrderService::class);
        $poSvc->confirm($po);

        expect(fn () => $poSvc->confirm($po->fresh()))->toThrow(\RuntimeException::class);
    });

    it('ne peut pas convertir une DA non approuvée', function () {
        $user     = purchAdmin();
        $supplier = purchSupplier();
        $product  = purchProduct();
        $unit     = Unit::firstOrCreate(['name' => 'Kg PF4'], ['abbreviation' => 'kgpf4']);

        $this->actingAs($user);

        $prSvc = app(PurchaseRequestService::class);
        $pr = $prSvc->create([
            'department' => 'Achats',
            'items'      => [[
                'product_id' => $product->id, 'description' => 'Article', 'quantity' => 1,
                'estimated_price' => 1_000, 'unit_id' => $unit->id,
            ]],
        ]);
        // Toujours en brouillon — jamais soumise ni approuvée.

        expect(fn () => $prSvc->convertToPurchaseOrder($pr, $supplier->id))
            ->toThrow(\RuntimeException::class);
    });

    it('bloque la facture si une facture existe déjà pour ce PO', function () {
        $user     = purchAdmin();
        $supplier = purchSupplier();
        $product  = purchProduct();
        $unit     = Unit::firstOrCreate(['name' => 'Kg PF5'], ['abbreviation' => 'kgpf5']);

        $this->actingAs($user);

        $prSvc = app(PurchaseRequestService::class);
        $pr = $prSvc->create([
            'department' => 'Production',
            'items'      => [[
                'product_id' => $product->id, 'description' => 'Article', 'quantity' => 3,
                'estimated_price' => 2_000, 'unit_id' => $unit->id,
            ]],
        ]);
        $prSvc->submit($pr);
        $prSvc->approve($pr);
        $po = $prSvc->convertToPurchaseOrder($pr, $supplier->id);

        $poSvc = app(PurchaseOrderService::class);
        $poSvc->confirm($po);
        $poSvc->createSupplierInvoice($po->fresh());

        expect(fn () => $poSvc->createSupplierInvoice($po->fresh()))
            ->toThrow(\RuntimeException::class);
    });
});
