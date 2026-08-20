<?php

/**
 * [Ventes §4.3] Le bon de livraison se construit depuis les quantités PRÉPARÉES
 * ET VALIDÉES, jamais depuis la commande.
 *
 * C'est l'exigence qui rend l'invariant « livré ≤ préparé validé » vérifiable.
 * Sans ce rattachement, le BL reprenait le reliquat de commande et rien ne
 * garantissait qu'il corresponde à ce qui était physiquement sorti.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNoteItem;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesPicking;
use App\Models\SalesPickingControl;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use App\Services\Sales\SalesPickingService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function dlvFixture(float $orderedQty = 100): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DLV-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Dlv Co'], [
        'email' => 'dlv@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    foreach (['bon_preparations.update', 'bon_preparations.control', 'bon_preparations.validate'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $preparateur = User::factory()->create(['company_id' => $company->id]);
    $preparateur->givePermissionTo('bon_preparations.update');
    $controleur = User::factory()->create(['company_id' => $company->id]);
    $controleur->givePermissionTo('bon_preparations.control');
    $validateur = User::factory()->create(['company_id' => $company->id]);
    $validateur->givePermissionTo('bon_preparations.validate');

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'name' => 'Dépôt livraison', 'code' => 'DEPL-'.uniqid(),
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Kg Dlv'], ['abbreviation' => 'kgd']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-DLV-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 1_000_000, 'total_ttc' => 1_000_000, 'invoiced_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => $product->name, 'quantity' => $orderedQty, 'delivered_quantity' => 0,
        'unit_price' => 10_000, 'line_total_ht' => $orderedQty * 10_000,
        'line_tax' => 0, 'line_total_ttc' => $orderedQty * 10_000,
    ]);

    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-DLV-'.uniqid(), 'quantity' => 1000, 'initial_quantity' => 1000,
        'reserved_quantity' => 0, 'unit_cost' => 800, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere',
        'received_at' => now(),
    ]);

    test()->actingAs($preparateur);

    return compact('company', 'fy', 'order', 'orderItem', 'product', 'warehouse',
        'lot', 'preparateur', 'controleur', 'validateur');
}

/** Bon préparé, contrôlé et validé pour `$qty`, prélevé pour `$picked`. */
function dlvValidatedPicking(array $f, float $qty, ?float $picked = null, ?string $reason = null): SalesPicking
{
    $svc = app(SalesPickingService::class);
    test()->actingAs($f['preparateur']);
    $picking = $svc->create($f['order'], [
        ['order_item_id' => $f['orderItem']->id, 'quantity' => $qty],
    ], ['warehouse_id' => $f['warehouse']->id]);
    $alloc = $svc->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => $qty,
    ]);
    $svc->start($picking);
    $svc->pick($alloc, $picked ?? $qty, $reason);

    test()->actingAs($f['controleur']);
    $svc->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    test()->actingAs($f['validateur']);
    $svc->validate($picking);

    return $picking->fresh('items');
}

// ---------------------------------------------------------------------------

it('construit le BL depuis le PRÉPARÉ VALIDÉ, pas depuis la commande', function () {
    // Commande de 100, mais seulement 60 préparés et validés.
    $f = dlvFixture(orderedQty: 100);
    $picking = dlvValidatedPicking($f, 60);

    $dn = app(DeliveryNoteService::class)->createFromPicking($picking);

    expect($dn->items)->toHaveCount(1)
        // 60 et non 100 : c'est toute la différence.
        ->and((float) $dn->items->first()->quantity)->toBe(60.0)
        ->and((float) $dn->total_quantity)->toBe(60.0)
        ->and($dn->items->first()->sales_picking_item_id)->toBe($picking->items->first()->id);
});

it('reprend la quantité réellement prélevée, pas celle allouée', function () {
    // Alloué 100, prélevé 80 avec motif : c'est 80 qui doit partir.
    $f = dlvFixture(orderedQty: 100);
    $picking = dlvValidatedPicking($f, 100, picked: 80, reason: 'Deux paquets endommagés.');

    $dn = app(DeliveryNoteService::class)->createFromPicking($picking);

    expect((float) $dn->items->first()->quantity)->toBe(80.0)
        ->and((float) $picking->items->first()->qty_validated)->toBe(80.0);
});

it('reprend le lot d origine quand l allocation est unique', function () {
    $f = dlvFixture(orderedQty: 50);
    $picking = dlvValidatedPicking($f, 50);

    $dn = app(DeliveryNoteService::class)->createFromPicking($picking);

    expect($dn->items->first()->stock_lot_id)->toBe($f['lot']->id);
});

it('n affirme aucun lot quand la ligne est préparée sur plusieurs lots', function () {
    $f = dlvFixture(orderedQty: 100);
    $second = StockLot::create([
        'product_id' => $f['product']->id, 'warehouse_id' => $f['warehouse']->id,
        'lot_number' => 'LOT-DLV-B', 'quantity' => 500, 'initial_quantity' => 500,
        'reserved_quantity' => 0, 'unit_cost' => 950, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere', 'received_at' => now(),
    ]);

    $svc = app(SalesPickingService::class);
    test()->actingAs($f['preparateur']);
    $picking = $svc->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 100]], ['warehouse_id' => $f['warehouse']->id]);
    $item = $picking->items->first();
    $a1 = $svc->allocate($item, ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 70]);
    $a2 = $svc->allocate($item, ['stock_lot_id' => $second->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 30]);
    $svc->start($picking);
    $svc->pick($a1, 70);
    $svc->pick($a2, 30);
    test()->actingAs($f['controleur']);
    $svc->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    test()->actingAs($f['validateur']);
    $svc->validate($picking);

    $dn = app(DeliveryNoteService::class)->createFromPicking($picking->fresh('items'));

    // Deux lots : en désigner un seul serait une affirmation fausse.
    expect((float) $dn->items->first()->quantity)->toBe(100.0)
        ->and($dn->items->first()->stock_lot_id)->toBeNull();
});

it('refuse de livrer depuis un bon non validé', function () {
    $f = dlvFixture(orderedQty: 50);
    $svc = app(SalesPickingService::class);
    $picking = $svc->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]);

    expect(fn () => app(DeliveryNoteService::class)->createFromPicking($picking))
        ->toThrow(RuntimeException::class, 'seul un bon VALIDÉ');
});

it('ne reproduit pas un BL déjà émis pour la totalité du validé', function () {
    $f = dlvFixture(orderedQty: 50);
    $picking = dlvValidatedPicking($f, 50);
    app(DeliveryNoteService::class)->createFromPicking($picking);

    expect(fn () => app(DeliveryNoteService::class)->createFromPicking($picking->fresh('items')))
        ->toThrow(RuntimeException::class, 'déjà livré');
});

it('refuse une ligne de BL qui dépasserait le préparé validé', function () {
    $f = dlvFixture(orderedQty: 100);
    $picking = dlvValidatedPicking($f, 60);
    $item = $picking->items->first();

    expect(fn () => app(SalesPickingService::class)->assertDeliverable($item, 61))
        ->toThrow(RuntimeException::class, 'Livraison');

    // 60 exactement passe.
    app(SalesPickingService::class)->assertDeliverable($item, 60);
    expect(true)->toBeTrue();
});

it('l audit DÉTECTE une livraison au-delà du préparé validé', function () {
    $f = dlvFixture(orderedQty: 100);
    $picking = dlvValidatedPicking($f, 60);
    $dn = app(DeliveryNoteService::class)->createFromPicking($picking);

    // Falsification directe en base : impossible par le service, c'est
    // précisément le rôle de l'audit de la constater.
    DeliveryNoteItem::where('delivery_note_id', $dn->id)
        ->update(['quantity' => 90]);

    $exit = Illuminate\Support\Facades\Artisan::call('a3:audit-pickings');
    $output = Illuminate\Support\Facades\Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('11. Lignes livrées au-delà du préparé validé : 1');
});

it('un BL annulé libère à nouveau le préparé validé', function () {
    $f = dlvFixture(orderedQty: 50);
    $picking = dlvValidatedPicking($f, 50);
    $dn = app(DeliveryNoteService::class)->createFromPicking($picking);

    // Tant que le BL vit, le validé est consommé.
    expect(fn () => app(DeliveryNoteService::class)->createFromPicking($picking->fresh('items')))
        ->toThrow(RuntimeException::class, 'déjà livré');

    $dn->update(['status' => 'annule']);

    // Une fois annulé, il ne consomme plus rien : le validé redevient livrable.
    $second = app(DeliveryNoteService::class)->createFromPicking($picking->fresh('items'));
    expect((float) $second->items->first()->quantity)->toBe(50.0);
});
