<?php

/**
 * [Ventes §20] L'audit des préparations DÉTECTE-T-IL vraiment ?
 *
 * Un audit qui rend « 0 anomalie » sur une base vide ne prouve rien. Chaque
 * détection est donc éprouvée en PLANTANT l'anomalie correspondante, puis en
 * vérifiant qu'elle est comptée et que la commande sort en échec.
 *
 * Les anomalies sont insérées en SQL direct, volontairement : elles doivent être
 * impossibles à créer par le service — c'est bien pour cela qu'un audit existe.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function auditFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'AUDPICK-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Audit Pick Co'], [
        'email' => 'auditpick@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'name' => 'Dépôt audit', 'code' => 'DEPA-'.uniqid(),
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Kg Audit'], ['abbreviation' => 'kga']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-AUD-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 100_000, 'total_ttc' => 100_000, 'invoiced_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => $product->name, 'quantity' => 10, 'delivered_quantity' => 0,
        'unit_price' => 10_000, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000,
    ]);

    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-AUD-'.uniqid(), 'quantity' => 10, 'initial_quantity' => 10,
        'reserved_quantity' => 0, 'unit_cost' => 800, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere',
        'received_at' => now(),
    ]);

    return compact('company', 'fy', 'order', 'orderItem', 'product', 'warehouse', 'lot');
}

/** Crée un bon + une ligne EN SQL DIRECT, sans passer par le service. */
function auditRawPicking(array $f, array $pickingAttrs = [], array $itemAttrs = []): array
{
    $pickingId = DB::table('sales_pickings')->insertGetId(array_merge([
        'company_id' => $f['company']->id,
        'order_id' => $f['order']->id,
        'fiscal_year_id' => $f['fy']->id,
        'number' => 'BP-AUD-'.uniqid(),
        'status' => SalesPicking::STATUS_EN_PREPARATION,
        'warehouse_id' => $f['warehouse']->id,
        'priority' => 'normale',
        'created_at' => now(), 'updated_at' => now(),
    ], $pickingAttrs));

    $itemId = DB::table('sales_picking_items')->insertGetId(array_merge([
        'sales_picking_id' => $pickingId,
        'order_item_id' => $f['orderItem']->id,
        'product_id' => $f['product']->id,
        'qty_ordered' => 10, 'qty_previously_delivered' => 0, 'qty_cancelled' => 0,
        'qty_remaining_snapshot' => 10,
        'qty_reserved' => 0, 'qty_allocated' => 0, 'qty_picked' => 0,
        'qty_controlled' => 0, 'qty_validated' => 0, 'variance_qty' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $itemAttrs));

    return ['picking_id' => $pickingId, 'item_id' => $itemId];
}

function auditRawAllocation(array $f, int $itemId, array $attrs = []): int
{
    return DB::table('sales_picking_allocations')->insertGetId(array_merge([
        'sales_picking_item_id' => $itemId,
        'stock_lot_id' => $f['lot']->id,
        'warehouse_id' => $f['warehouse']->id,
        'quantity' => 10,
        'status' => SalesPickingAllocation::STATUS_ALLOUEE,
        'created_at' => now(), 'updated_at' => now(),
    ], $attrs));
}

/** @return array{exit:int,output:string} */
function runAuditPickings(): array
{
    $exit = Artisan::call('a3:audit-pickings');

    return ['exit' => $exit, 'output' => Artisan::output()];
}

// ---------------------------------------------------------------------------

it('sort PROPRE quand rien n est planté', function () {
    auditFixture();
    $r = runAuditPickings();

    expect($r['exit'])->toBe(0)
        ->and($r['output'])->toContain('AUDIT PRÉPARATIONS PROPRE');
});

it('détecte une ligne de commande sur-engagée par plusieurs préparations', function () {
    $f = auditFixture();
    // Reliquat réel = 10. Deux bons engagent 8 chacun = 16.
    auditRawPicking($f, [], ['qty_remaining_snapshot' => 8]);
    auditRawPicking($f, [], ['qty_remaining_snapshot' => 8]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('2. Lignes de commande sur-engagées par les préparations : 1');
});

it('détecte des allocations dépassant le reliquat de la ligne', function () {
    $f = auditFixture();
    $p = auditRawPicking($f, [], ['qty_remaining_snapshot' => 5, 'qty_allocated' => 9]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 9]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('3. Lignes dont les allocations dépassent le reliquat : 1');
});

it('détecte un lot alloué au-delà de son stock', function () {
    $f = auditFixture();
    // Lot de 10 ; deux bons distincts allouent 8 chacun = 16.
    $a = auditRawPicking($f, [], ['qty_allocated' => 8]);
    $b = auditRawPicking($f, [], ['qty_allocated' => 8]);
    auditRawAllocation($f, $a['item_id'], ['quantity' => 8]);
    auditRawAllocation($f, $b['item_id'], ['quantity' => 8]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('4. Lots alloués au-delà de leur stock : 1')
        ->and($r['output'])->toContain('stock 10');
});

it('détecte une allocation sur un lot en quarantaine', function () {
    $f = auditFixture();
    $f['lot']->update(['quality_status' => 'quarantaine']);
    $p = auditRawPicking($f, [], ['qty_allocated' => 5]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 5]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('5. Allocations sur un lot non libéré : 1');
});

it('détecte une allocation sur un lot non valorisé définitivement', function () {
    $f = auditFixture();
    $f['lot']->update(['valuation_status' => 'valorisation_provisoire']);
    $p = auditRawPicking($f, [], ['qty_allocated' => 5]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 5]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('5 bis. Allocations sur un lot non valorisé définitivement : 1');
});

it('détecte un bon validé sans contrôle conforme', function () {
    $f = auditFixture();
    auditRawPicking($f, ['status' => SalesPicking::STATUS_VALIDE, 'validated_at' => now()]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('7. Bons validés sans contrôle conforme : 1');
});

it('détecte une séparation des acteurs non respectée', function () {
    $f = auditFixture();
    $user = App\Models\User::factory()->create(['company_id' => $f['company']->id]);
    // Même personne prépare ET contrôle.
    auditRawPicking($f, [
        'status' => SalesPicking::STATUS_CONTROLE,
        'started_by' => $user->id, 'controlled_by' => $user->id, 'controlled_at' => now(),
    ]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('7 bis. Séparation des acteurs non respectée : 1');
});

it('détecte une allocation encore active sur un bon annulé', function () {
    $f = auditFixture();
    $p = auditRawPicking($f, ['status' => SalesPicking::STATUS_ANNULE, 'cancelled_at' => now()]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 5]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('8. Allocations actives sur un bon annulé : 1');
});

it('détecte une réservation non libérée après annulation', function () {
    $f = auditFixture();
    $reservationId = DB::table('stock_reservations')->insertGetId([
        'company_id' => $f['company']->id, 'order_id' => $f['order']->id,
        'product_id' => $f['product']->id, 'warehouse_id' => $f['warehouse']->id,
        'quantity' => 5, 'status' => 'reserved', 'reserved_at' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $p = auditRawPicking($f, ['status' => SalesPicking::STATUS_ANNULE, 'cancelled_at' => now()]);
    auditRawAllocation($f, $p['item_id'], [
        'quantity' => 5, 'stock_reservation_id' => $reservationId,
        'status' => SalesPickingAllocation::STATUS_ANNULEE,
    ]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('8 bis. Réservations non libérées après annulation : 1');
});

it('détecte une violation de l invariant de quantités', function () {
    $f = auditFixture();
    // Validé 8 alors que seulement 5 ont été contrôlés : impossible par le service.
    auditRawPicking($f, [], [
        'qty_remaining_snapshot' => 10, 'qty_allocated' => 10,
        'qty_picked' => 10, 'qty_controlled' => 5, 'qty_validated' => 8,
    ]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain("10. Lignes violant l'invariant de quantités : 1");
});

it('détecte un agrégat qty_allocated désynchronisé de ses allocations', function () {
    $f = auditFixture();
    // L'agrégat annonce 7, les allocations réelles totalisent 3.
    $p = auditRawPicking($f, [], ['qty_allocated' => 7]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 3]);

    $r = runAuditPickings();

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('10 bis. Agrégat qty_allocated désynchronisé : 1');
});

it('compte les allocations sans réservation comme INFORMATIF, jamais comme critique', function () {
    $f = auditFixture();
    $p = auditRawPicking($f, [], ['qty_allocated' => 5]);
    auditRawAllocation($f, $p['item_id'], ['quantity' => 5, 'stock_reservation_id' => null]);

    $r = runAuditPickings();

    // La règle « une allocation doit porter une réservation » n'est pas tranchée :
    // l'audit le signale sans faire échouer, et ne prétend pas que c'est sain.
    expect($r['output'])->toContain('9. Allocations sans réservation (informatif) : 1')
        ->and($r['output'])->toContain('Anomalies informatives : 1')
        ->and($r['exit'])->toBe(0);
});

it('vérifie réellement la cohérence aval depuis que le BL est rattaché', function () {
    auditFixture();
    $r = runAuditPickings();

    // Le rattachement `delivery_note_items.sales_picking_item_id` existe : la
    // détection s'exécute pour de bon et peut donc rapporter 0 honnêtement.
    // Tant qu'elle n'existait pas, l'audit déclarait NON APPLICABLE plutôt que
    // d'annoncer un zéro qui aurait laissé croire à une vérification faite.
    //
    // La preuve que cette détection MORD est dans SalesPickingToDeliveryTest :
    // « l'audit DÉTECTE une livraison au-delà du préparé validé ».
    expect(\Illuminate\Support\Facades\Schema::hasColumn('delivery_note_items', 'sales_picking_item_id'))->toBeTrue()
        ->and($r['output'])->toContain('11. Lignes livrées au-delà du préparé validé : 0')
        ->and($r['output'])->not->toContain('NON APPLICABLE');
});
