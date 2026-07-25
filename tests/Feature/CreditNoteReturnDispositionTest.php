<?php

/**
 * [VEN Retour] Retours rebut / remplacement sur avoir.
 *
 *  - une ligne 'rebut' ne réintègre PAS le stock vendable (pas de mouvement retour_client) ;
 *  - une ligne 'restock' génère bien un retour_client ;
 *  - la génération d'un remplacement crée un BL brouillon lié reprenant les articles.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItemLotAllocation;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CommercialWorkflowService;
use App\Services\CreditNoteService;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function cnrAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CNR'], ['email' => 'cnr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

function cnrCreditNote(): CreditNote
{
    $co = currentCompany();
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEF'], ['name' => 'Dépôt', 'is_default' => true]);

    $pA = Product::factory()->create(['is_stockable' => true, 'purchase_price' => 500]);
    $pB = Product::factory()->create(['is_stockable' => true, 'purchase_price' => 800]);

    $invoice = Invoice::create([
        'company_id' => $co->id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'F-'.uniqid(),
        'type' => 'facture',
        'status' => 'emise',
        'issued_at' => now(),
        'currency_code' => 'XOF',
        'subtotal_ht' => 0, 'total_ttc' => 0, 'remaining_amount' => 0,
    ]);
    $delivery = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $invoice->client_id, 'number' => 'BL-CNR-'.uniqid(),
        'status' => 'valide', 'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    foreach ([[$pA, 3, 500], [$pB, 2, 800]] as [$product, $quantity, $cost]) {
        $lot = StockLot::create([
            'product_id' => $product->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-CNR-'.uniqid(),
            'quantity' => 0, 'initial_quantity' => $quantity, 'unit_cost' => $cost, 'status' => 'consomme',
        ]);
        $deliveryItem = $delivery->items()->create([
            'product_id' => $product->id, 'description' => $product->name, 'quantity' => $quantity, 'unit_price' => 1000,
        ]);
        DeliveryNoteItemLotAllocation::create([
            'delivery_note_item_id' => $deliveryItem->id, 'stock_lot_id' => $lot->id, 'warehouse_id' => $wh->id,
            'quantity' => $quantity, 'unit_cost_snapshot' => $cost, 'total_cost' => $quantity * $cost, 'allocated_at' => now(),
        ]);
        $invoice->items()->create([
            'delivery_note_item_id' => $deliveryItem->id, 'product_id' => $product->id, 'description' => $product->name,
            'quantity' => $quantity, 'unit_price' => 1000, 'unit_cost' => $cost,
            'line_total_ht' => $quantity * 1000, 'line_tax' => 0, 'line_total_ttc' => $quantity * 1000,
        ]);
    }

    return app(CreditNoteService::class)->createFromInvoice($invoice, [
        'reason' => 'Retour marchandise',
        'items' => [
            ['product_id' => $pA->id, 'description' => 'A restock', 'quantity' => 3, 'unit_price' => 1000, 'tax_rate_value' => 0, 'disposition' => 'restock'],
            ['product_id' => $pB->id, 'description' => 'B rebut',   'quantity' => 2, 'unit_price' => 1500, 'tax_rate_value' => 0, 'disposition' => 'rebut'],
        ],
    ]);
}

it('persiste la disposition des lignes', function () {
    cnrAdmin();
    $cn = cnrCreditNote();

    $dispos = $cn->items()->pluck('disposition', 'description');
    expect($dispos['A restock'])->toBe('restock');
    expect($dispos['B rebut'])->toBe('rebut');
});

it('ne réintègre pas le stock pour une ligne rebut', function () {
    cnrAdmin();
    $cn = cnrCreditNote();

    app(CreditNoteService::class)->validate($cn);

    $movements = StockMovement::where('reference_type', 'credit_note')
        ->where('reference_id', $cn->id)
        ->where('type', 'retour_client')
        ->get();

    // Seule la ligne 'restock' génère un retour_client.
    expect($movements)->toHaveCount(1);
    expect((float) $movements->first()->quantity)->toBe(3.0);
});

it('génère un BL de remplacement brouillon lié reprenant les articles', function () {
    cnrAdmin();
    $cn = cnrCreditNote();
    app(CreditNoteService::class)->validate($cn);

    $dn = app(CreditNoteService::class)->createReplacementDelivery($cn->fresh());

    expect($dn->status)->toBe('brouillon');
    expect($dn->order_id)->toBeNull();
    expect($dn->items()->count())->toBe(2);
    expect($cn->fresh()->replacement_delivery_id)->toBe($dn->id);
    expect((bool) $cn->fresh()->is_replacement)->toBeTrue();

    // Un second appel est bloqué (idempotence métier).
    expect(fn () => app(CreditNoteService::class)->createReplacementDelivery($cn->fresh()))
        ->toThrow(RuntimeException::class);
});

// [Matrice annulations] Annuler un avoir VALIDÉ inverse tout : stock ressorti,
// application facture défaite, écritures extournées — plus un simple statut.
it('annule un avoir validé : stock ressorti, facture restaurée, GL extourné', function () {
    cnrAdmin();
    $co = currentCompany();
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEF'], ['name' => 'Dépôt', 'is_default' => true]);
    $client = Client::factory()->create();
    $p = Product::factory()->create(['is_stockable' => true, 'purchase_price' => 500]);

    $invoice = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'FA-CANCN-'.uniqid(), 'status' => 'emise', 'issued_at' => now(),
        'currency_code' => 'XOF',
        'subtotal_ht' => 10000, 'total_tax' => 0, 'total_ttc' => 10000, 'remaining_amount' => 10000, 'paid_amount' => 0,
    ]);

    $lot = StockLot::create([
        'product_id' => $p->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-CANCEL-'.uniqid(),
        'quantity' => 0, 'initial_quantity' => 2, 'unit_cost' => 500, 'status' => 'consomme',
    ]);
    $delivery = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'number' => 'BL-CANCEL-'.uniqid(),
        'status' => 'valide', 'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $deliveryItem = $delivery->items()->create([
        'product_id' => $p->id, 'description' => 'Retour', 'quantity' => 2, 'unit_price' => 5000,
    ]);
    DeliveryNoteItemLotAllocation::create([
        'delivery_note_item_id' => $deliveryItem->id, 'stock_lot_id' => $lot->id, 'warehouse_id' => $wh->id,
        'quantity' => 2, 'unit_cost_snapshot' => 500, 'total_cost' => 1000, 'allocated_at' => now(),
    ]);
    $invoice->items()->create([
        'delivery_note_item_id' => $deliveryItem->id, 'product_id' => $p->id, 'description' => 'Retour',
        'quantity' => 2, 'unit_price' => 5000, 'unit_cost' => 500,
        'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000,
    ]);

    $svc = app(CreditNoteService::class);
    $cn = $svc->createFromInvoice($invoice, [
        'reason' => 'Retour marchandise', 'items' => [[
            'product_id' => $p->id, 'description' => 'Retour', 'quantity' => 2,
            'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'restock',
        ]],
    ]);
    $svc->validate($cn);
    $svc->applyToInvoice($cn->fresh());

    $stockApres = (float) ProductStock::where('product_id', $p->id)->value('quantity');
    expect($stockApres)->toBe(2.0)                                       // retour entré
        ->and((int) $invoice->fresh()->remaining_amount)->toBe(0)        // avoir appliqué
        ->and($invoice->fresh()->status)->toBe('payee');
    $glAvant = JournalEntry::count();

    app(CommercialWorkflowService::class)->cancel($cn->fresh(), 'Avoir émis par erreur');

    expect($cn->fresh()->status)->toBe('annule')
        ->and((float) ProductStock::where('product_id', $p->id)->value('quantity'))->toBe(0.0)  // stock ressorti
        ->and((int) $invoice->fresh()->remaining_amount)->toBe(10000)    // application défaite
        ->and($invoice->fresh()->status)->toBe('emise')
        ->and(JournalEntry::count())->toBeGreaterThan($glAvant); // extourne(s)

    // Idempotence
    expect(fn () => app(CommercialWorkflowService::class)->cancel($cn->fresh(), 'encore'))
        ->toThrow(RuntimeException::class);
});
