<?php

/**
 * [FIX rapport de test MTO — 2e campagne (CLI-00008 / OF-2026-0152-0153)]
 *
 *  #2 — anti double comptage STOCK : un composant BOM déjà consommé en réel via
 *       bobine sur l'OF n'est PAS backflushé dans product_stocks (le blocage
 *       « Stock insuffisant 4,56/17,51 » venait de ce double comptage, et la
 *       déclaration disparaissait en rollback silencieux).
 *  #2b — l'annulation d'une déclaration contre-passe les mouvements RÉELS de la
 *       déclaration (pas de recalcul BOM → pas de stock fantôme si backflush sauté).
 *  #2c — quand un backflush échoue vraiment (pas de bobine), le message nomme le
 *       composant et le dépôt de sortie de la nomenclature.
 *  #4 — la quantité proposée à la création du BL est plafonnée au stock disponible
 *       pour la commande (reliquat 50 mais 40 en stock → BL proposé à 40).
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\DeliveryNoteService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtoR2Setup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MR2'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MR2 Co'], ['email' => 'mr2@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-MR2'], [
        'name' => 'WH MR2', 'is_active' => true, 'is_default' => true,
        'can_production' => true, 'can_stock' => true, 'can_sale' => true, 'can_purchase' => true,
    ]);

    return [$co, $wh];
}

function mtoR2Order(Company $co, Warehouse $wh, Product $finished, Product $bobine, float $stockBobine): ProductionOrder
{
    ProductStock::firstOrCreate(['product_id' => $bobine->id, 'warehouse_id' => $wh->id],
        ['quantity' => $stockBobine, 'reserved_quantity' => 0, 'avg_cost' => 850]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $finished->id, 'name' => 'BOM MR2 ' . uniqid(), 'is_active' => true]);
    $bom->lines()->create(['product_id' => $bobine->id, 'quantity_per_meter' => 1.7513, 'waste_rate' => 0, 'sort_order' => 1, 'depot_sortie_id' => $wh->id]);

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-MR2-' . uniqid(), 'status' => 'en_cours',
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => $finished->id, 'bill_of_material_id' => $bom->id,
    ]);
}

it('#2 — pas de backflush du composant déjà consommé en réel via bobine (déclaration acceptée)', function () {
    [$co, $wh] = mtoR2Setup();
    $finished = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $bobine   = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    // Reproduction OF-2026-0153 : stock théorique du dépôt BOM INSUFFISANT pour le
    // backflush (4,558 < 17,513) mais matière réelle déjà sortie via la bobine.
    $order = mtoR2Order($co, $wh, $finished, $bobine, stockBobine: 4.558);

    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $bobine->id, 'reference' => 'BOB-MR2-' . uniqid(),
        'initial_weight' => 100, 'remaining_weight' => 100, 'cost_per_kg' => 850,
        'purchase_price' => 85000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 36, 17.51);

    // Avant le fix : ValidationException « Stock insuffisant : 4,56 dispo, 17,51 demandée »
    // → rollback complet, déclaration perdue silencieusement.
    $output = app(ProductionStockService::class)->recordOutput($order->fresh(), [
        'quantity' => 10, 'length' => 1, 'warehouse_id' => $wh->id,
    ]);

    expect($output->exists)->toBeTrue()
        ->and((float) $order->fresh()->quantity_produced)->toBe(10.0);

    // Le stock théorique du composant n'a PAS été backflushé (bobine = source de vérité).
    $stock = ProductStock::where('product_id', $bobine->id)->where('warehouse_id', $wh->id)->first();
    expect(round((float) $stock->quantity, 3))->toBe(4.558);
});

it('#2b — annuler la déclaration ne ré-entre pas un backflush qui n’a jamais eu lieu', function () {
    [$co, $wh] = mtoR2Setup();
    $finished = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $bobine   = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $order = mtoR2Order($co, $wh, $finished, $bobine, stockBobine: 4.558);

    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $bobine->id, 'reference' => 'BOB-MR2-' . uniqid(),
        'initial_weight' => 100, 'remaining_weight' => 100, 'cost_per_kg' => 850,
        'purchase_price' => 85000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 36, 17.51);

    $output = app(ProductionStockService::class)->recordOutput($order->fresh(), [
        'quantity' => 10, 'length' => 1, 'warehouse_id' => $wh->id,
    ]);

    app(ProductionStockService::class)->reverseOutput($output->fresh());

    // Avant le fix : restore recalculé depuis la BOM → +17,513 de stock FANTÔME.
    $stock = ProductStock::where('product_id', $bobine->id)->where('warehouse_id', $wh->id)->first();
    expect(round((float) $stock->quantity, 3))->toBe(4.558)
        ->and((float) $order->fresh()->quantity_produced)->toBe(0.0);
});

it('#2c — le refus de backflush nomme le composant et le dépôt (plus de « 4,56/17,51 » cryptique)', function () {
    [$co, $wh] = mtoR2Setup();
    $finished = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $bobine   = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'allow_negative_stock' => false]);

    // Pas de consommation bobine → le backflush s'applique, et le stock est insuffisant.
    $order = mtoR2Order($co, $wh, $finished, $bobine, stockBobine: 4.558);

    try {
        app(ProductionStockService::class)->recordOutput($order->fresh(), [
            'quantity' => 10, 'length' => 1, 'warehouse_id' => $wh->id,
        ]);
        $this->fail('Une ValidationException était attendue (stock composant insuffisant).');
    } catch (ValidationException $e) {
        $msg = collect($e->errors())->flatten()->implode(' ');
        expect($msg)->toContain($bobine->name)   // le composant est nommé
            ->and($msg)->toContain($wh->name)    // le dépôt de sortie BOM est nommé
            ->and($msg)->toContain('Déclaration refusée');
    }

    // Rollback intègre : ni déclaration ni mouvement orphelin.
    expect($order->fresh()->outputs()->count())->toBe(0)
        ->and(StockMovement::where('product_id', $bobine->id)->where('type', 'sortie')->count())->toBe(0);
});

it('#4 — la quantité du BL est plafonnée au stock disponible pour la commande (50 commandées, 40 en stock → 40)', function () {
    [$co, $wh] = mtoR2Setup();
    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);

    // 40 en stock dont 0 réservé → dispo 40 pour une commande de 50.
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 40, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $order = Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-MR2-' . uniqid(),
        'status'         => 'confirme',
        'issued_at'      => now(),
        'total_ttc'      => 400000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 50, 'unit_price' => 8000, 'sort_order' => 0,
        'line_total_ht' => 400000, 'line_tax' => 0, 'line_total_ttc' => 400000,
    ]);

    $bl = app(DeliveryNoteService::class)->createFromOrder($order->fresh());

    // Avant le fix : 50 proposées (survente si validé sans correction manuelle).
    expect((float) $bl->items()->first()->quantity)->toBe(40.0)
        ->and((float) $bl->total_quantity)->toBe(40.0);
});

it('#4 — stock suffisant : la quantité du BL reste le reliquat commandé', function () {
    [$co, $wh] = mtoR2Setup();
    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $order = Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-MR2-' . uniqid(),
        'status'         => 'confirme',
        'issued_at'      => now(),
        'total_ttc'      => 400000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 50, 'unit_price' => 8000, 'sort_order' => 0,
        'line_total_ht' => 400000, 'line_tax' => 0, 'line_total_ttc' => 400000,
        'delivered_quantity' => 10, // 10 déjà livrées → reliquat 40
    ]);

    $bl = app(DeliveryNoteService::class)->createFromOrder($order->fresh());

    expect((float) $bl->items()->first()->quantity)->toBe(40.0);
});

it('#5 — le détail d’un encaissement exclut les imputations de factures supprimées du total', function () {
    [$co] = mtoR2Setup();
    $client = Client::factory()->create(['is_active' => true]);

    $mkInvoice = fn (string $n) => Invoice::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => $n, 'status' => 'emise',
        'issued_at' => now(), 'total_ht' => 320000, 'total_tax' => 57600, 'total_ttc' => 377600,
    ]);
    $keep  = $mkInvoice('FA-MR2-KEEP');
    $ghost = $mkInvoice('FA-MR2-GHOST');

    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id,
        'number' => 'ENC-MR2-001', 'amount' => 377600,
        'allocated_amount' => 377600, 'unallocated_amount' => 0,
        'payment_date' => now(), 'status' => 'confirme', 'created_by' => auth()->id(),
    ]);
    $pay->allocations()->create(['invoice_id' => $keep->id,  'amount' => 377600, 'allocated_at' => now(), 'created_by' => auth()->id()]);
    $pay->allocations()->create(['invoice_id' => $ghost->id, 'amount' => 118000, 'allocated_at' => now(), 'created_by' => auth()->id()]);

    // La facture fantôme disparaît (cas réel : facture de démo supprimée).
    $ghost->delete();

    $resp = $this->get(route('tresorerie.encaissements.show', $pay))->assertOk();

    // Total imputé = 377 600 (pas 495 600), la facture supprimée n'est plus une ligne
    // du tableau mais une note d'audit explicite.
    $resp->assertSee('377 600')
        ->assertDontSee('495 600')
        ->assertDontSee('Facture supprimée')
        ->assertSee('factures supprimées');
});
