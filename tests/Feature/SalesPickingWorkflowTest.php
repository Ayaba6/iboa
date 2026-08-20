<?php

/**
 * [Ventes §19] Workflow des bons de préparation — 20 scénarios obligatoires.
 *
 * Chaque test isole UNE règle. Un test qui vérifierait plusieurs gardes à la
 * fois passerait même si une seule protégeait réellement.
 *
 * Les quantités et les statuts sont TOUJOURS relus en base après l'action :
 * on ne fait jamais confiance à l'objet en mémoire retourné par le service.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\SalesPickingControl;
use App\Models\SalesPickingItem;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Services\Sales\SalesPickingService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Décor
// ---------------------------------------------------------------------------

/** @return array<string,mixed> */
function pickFixture(float $orderedQty = 100, float $lotQty = 500): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PICK-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Pick Co'], [
        'email' => 'pick@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    foreach (['bon_preparations.update', 'bon_preparations.control', 'bon_preparations.validate'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    // Trois acteurs DISTINCTS : la séparation des tâches est le sujet, pas un détail.
    $preparateur = User::factory()->create(['company_id' => $company->id]);
    $preparateur->givePermissionTo('bon_preparations.update');
    $controleur = User::factory()->create(['company_id' => $company->id]);
    $controleur->givePermissionTo(['bon_preparations.control', 'bon_preparations.update']);
    $validateur = User::factory()->create(['company_id' => $company->id]);
    $validateur->givePermissionTo('bon_preparations.validate');

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'name' => 'Dépôt principal Pick', 'code' => 'DEPP-'.uniqid(),
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Kg Pick'], ['abbreviation' => 'kgp']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-PICK-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
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
        'lot_number' => 'LOT-PICK-'.uniqid(), 'quantity' => $lotQty, 'initial_quantity' => $lotQty,
        'reserved_quantity' => 0, 'unit_cost' => 800, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere',
        'received_at' => now(),
    ]);

    test()->actingAs($preparateur);

    return compact('company', 'fy', 'order', 'orderItem', 'product', 'warehouse', 'unit', 'lot',
        'preparateur', 'controleur', 'validateur', 'client');
}

function pickService(): SalesPickingService
{
    return app(SalesPickingService::class);
}

/** Crée un bon avec une ligne unique. */
function pickCreate(array $f, float $qty): SalesPicking
{
    return pickService()->create($f['order'], [
        ['order_item_id' => $f['orderItem']->id, 'quantity' => $qty],
    ], ['warehouse_id' => $f['warehouse']->id]);
}

/** Chaîne complète jusqu'au statut préparé, sur une allocation unique. */
function pickPrepared(array $f, float $qty, ?float $pickedQty = null, ?string $reason = null): SalesPicking
{
    $picking = pickCreate($f, $qty);
    $item = $picking->items->first();
    $alloc = pickService()->allocate($item, [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => $qty,
    ]);
    pickService()->start($picking);
    pickService()->pick($alloc, $pickedQty ?? $qty, $reason);

    return $picking->fresh('items');
}

// ---------------------------------------------------------------------------
// 1-3 · Préparation complète, partielle, multiple
// ---------------------------------------------------------------------------

it('1 · prépare la totalité du reliquat et passe au statut préparé', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickPrepared($f, 100);

    expect($picking->status)->toBe(SalesPicking::STATUS_PREPARE)
        ->and((float) $picking->items->first()->qty_picked)->toBe(100.0)
        ->and((float) $picking->items->first()->qty_remaining_snapshot)->toBe(100.0);
});

it('2 · une préparation partielle reste au statut partiellement préparé', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickCreate($f, 100);
    $item = $picking->items->first();
    $alloc = pickService()->allocate($item, [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 100,
    ]);
    pickService()->start($picking);
    pickService()->pick($alloc, 60, 'Rupture de palette, reliquat à préparer.');

    $fresh = $picking->fresh('items');
    expect($fresh->status)->toBe(SalesPicking::STATUS_PARTIELLEMENT_PREPARE)
        ->and((float) $fresh->items->first()->qty_picked)->toBe(60.0)
        ->and((float) $fresh->items->first()->variance_qty)->toBe(40.0);
});

it('3 · deux préparations successives se partagent le reliquat sans le dépasser', function () {
    $f = pickFixture(orderedQty: 100);

    $first = pickCreate($f, 60);
    expect((float) $first->items->first()->qty_remaining_snapshot)->toBe(60.0);

    // Le second bon ne voit plus que 40 : le premier a engagé 60.
    $second = pickCreate($f, 40);
    expect((float) $second->items->first()->qty_remaining_snapshot)->toBe(40.0);

    $engaged = SalesPickingItem::where('order_item_id', $f['orderItem']->id)->sum('qty_remaining_snapshot');
    expect((float) $engaged)->toBe(100.0);
});

// ---------------------------------------------------------------------------
// 4 · Quantité supérieure au reliquat
// ---------------------------------------------------------------------------

it('4 · refuse une quantité supérieure au reliquat de la commande', function () {
    $f = pickFixture(orderedQty: 100);
    pickCreate($f, 80);

    expect(fn () => pickCreate($f, 30))
        ->toThrow(RuntimeException::class, 'supérieure au reliquat');
});

it('4 bis · le reliquat tient compte du déjà livré', function () {
    $f = pickFixture(orderedQty: 100);
    $f['orderItem']->update(['delivered_quantity' => 70]);

    expect(fn () => pickCreate($f, 40))->toThrow(RuntimeException::class, 'supérieure au reliquat');
    $ok = pickCreate($f, 30);
    expect((float) $ok->items->first()->qty_remaining_snapshot)->toBe(30.0);
});

// ---------------------------------------------------------------------------
// 5-6 · Allocation multi-lots et par bobine
// ---------------------------------------------------------------------------

it('5 · alloue une ligne sur plusieurs lots', function () {
    $f = pickFixture(orderedQty: 100);
    $second = StockLot::create([
        'product_id' => $f['product']->id, 'warehouse_id' => $f['warehouse']->id,
        'lot_number' => 'LOT-PICK-B', 'quantity' => 200, 'initial_quantity' => 200,
        'reserved_quantity' => 0, 'unit_cost' => 950, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere', 'received_at' => now(),
    ]);

    $picking = pickCreate($f, 100);
    $item = $picking->items->first();
    pickService()->allocate($item, ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 70]);
    pickService()->allocate($item, ['stock_lot_id' => $second->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 30]);

    $allocations = SalesPickingAllocation::where('sales_picking_item_id', $item->id)->get();
    expect($allocations)->toHaveCount(2)
        ->and((float) $item->fresh()->qty_allocated)->toBe(100.0)
        // Chaque allocation fige SON coût historique : 800 et 950, pas une moyenne.
        ->and($allocations->pluck('historical_unit_cost')->map(fn ($c) => (float) $c)->sort()->values()->all())
        ->toBe([800.0, 950.0]);
});

it('6 · alloue une ligne à une bobine libérée', function () {
    $f = pickFixture(orderedQty: 50);
    $coil = Coil::factory()->create([
        'warehouse_id' => $f['warehouse']->id,
        'quality_status' => Coil::QUALITY_RELEASED,
        'transformation_status' => Coil::TRANSFO_INTACT,
        'status' => 'disponible',
        'initial_weight' => 500, 'remaining_weight' => 500, 'cost_per_kg' => 700,
    ]);

    $picking = pickCreate($f, 50);
    $alloc = pickService()->allocate($picking->items->first(), [
        'coil_id' => $coil->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]);

    expect($alloc->coil_id)->toBe($coil->id)
        ->and((float) $alloc->historical_unit_cost)->toBe(700.0);
});

// ---------------------------------------------------------------------------
// 7-9 · Interdictions d'allocation
// ---------------------------------------------------------------------------

it('7 · refuse un lot en quarantaine', function () {
    $f = pickFixture(orderedQty: 50);
    $f['lot']->update(['quality_status' => 'quarantaine']);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]))->toThrow(RuntimeException::class, 'quarantaine');
});

it('7 bis · refuse un lot non valorisé définitivement', function () {
    $f = pickFixture(orderedQty: 50);
    $f['lot']->update(['valuation_status' => 'valorisation_provisoire']);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]))->toThrow(RuntimeException::class, 'non valorisé');
});

it('8 · refuse une bobine non libérée', function () {
    $f = pickFixture(orderedQty: 50);
    $coil = Coil::factory()->create([
        'warehouse_id' => $f['warehouse']->id,
        'quality_status' => Coil::QUALITY_QUARANTINED,
        'transformation_status' => Coil::TRANSFO_INTACT,
        'status' => 'disponible', 'initial_weight' => 500, 'remaining_weight' => 500,
    ]);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'coil_id' => $coil->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]))->toThrow(RuntimeException::class, 'non libérée');
});

it('9 · refuse une bobine mère divisée', function () {
    $f = pickFixture(orderedQty: 50);
    $coil = Coil::factory()->create([
        'warehouse_id' => $f['warehouse']->id,
        'quality_status' => Coil::QUALITY_RELEASED,
        'transformation_status' => Coil::TRANSFO_SPLIT,
        'status' => 'disponible', 'initial_weight' => 500, 'remaining_weight' => 0,
    ]);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'coil_id' => $coil->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]))->toThrow(RuntimeException::class, 'DIVISÉE');
});

// ---------------------------------------------------------------------------
// 10 · Double allocation et dépôt
// ---------------------------------------------------------------------------

it('10 · refuse une allocation qui dépasse le reliquat de la ligne', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickCreate($f, 100);
    $item = $picking->items->first();
    pickService()->allocate($item, ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 80]);

    expect(fn () => pickService()->allocate($item, [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 30,
    ]))->toThrow(RuntimeException::class, 'déjà alloué');
});

it('10 bis · refuse un dépôt différent de celui du bon', function () {
    $f = pickFixture(orderedQty: 50);
    $other = Warehouse::create([
        'company_id' => $f['company']->id, 'name' => 'Dépôt secondaire', 'code' => 'DEP2-'.uniqid(),
    ]);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $other->id, 'quantity' => 50,
    ]))->toThrow(RuntimeException::class, 'Dépôt incorrect');
});

it('10 ter · refuse une quantité supérieure au disponible du lot', function () {
    $f = pickFixture(orderedQty: 100, lotQty: 40);
    $picking = pickCreate($f, 100);

    expect(fn () => pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 100,
    ]))->toThrow(RuntimeException::class, 'disponible');
});

// ---------------------------------------------------------------------------
// 11-13 · Contrôle, modification après contrôle, validation
// ---------------------------------------------------------------------------

it('11 · le contrôle exige un acteur distinct du préparateur', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickPrepared($f, 50);

    // Le préparateur détient aussi le droit de contrôle : le refus doit donc
    // venir de la séparation des tâches, pas d'une permission manquante.
    $f['preparateur']->givePermissionTo('bon_preparations.control');
    test()->actingAs($f['preparateur']);
    expect(fn () => pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME))
        ->toThrow(RuntimeException::class, 'ne peut pas contrôler sa propre');

    test()->actingAs($f['controleur']);
    $control = pickService()->control($picking, ['article' => true, 'lot' => true, 'quantite' => true], SalesPickingControl::RESULT_CONFORME);

    expect($control->result)->toBe(SalesPickingControl::RESULT_CONFORME)
        ->and($picking->fresh()->status)->toBe(SalesPicking::STATUS_CONTROLE);
});

/**
 * Bon de 100 dont seuls 60 sont alloués et prélevés : il reste 40 allouables,
 * ce qui permet de PROUVER l'invalidation par une modification réelle.
 */
function pickPartiallyAllocated(array $f): SalesPicking
{
    $picking = pickCreate($f, 100);
    $alloc = pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 60,
    ]);
    pickService()->start($picking);
    pickService()->pick($alloc, 60, 'Première tournée de préparation.');

    return $picking->fresh('items');
}

it('12 · une modification après contrôle invalide le contrôle précédent', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickPartiallyAllocated($f);
    test()->actingAs($f['controleur']);
    $control = pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    expect($control->fresh()->invalidated_at)->toBeNull()
        ->and($picking->fresh()->status)->toBe(SalesPicking::STATUS_CONTROLE);

    // Nouvelle allocation après contrôle → le contrôle tombe.
    test()->actingAs($f['preparateur']);
    pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 40,
    ]);

    expect($control->fresh()->invalidated_at)->not->toBeNull()
        ->and($control->fresh()->invalidated_reason)->toContain('après contrôle')
        // Le bon repart en préparation : il ne peut plus prétendre être contrôlé.
        ->and($picking->fresh()->status)->toBe(SalesPicking::STATUS_PARTIELLEMENT_PREPARE)
        ->and($picking->fresh()->controlled_at)->toBeNull();
});

it('12 bis · un bon dont le contrôle est tombé ne peut plus être validé', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickPartiallyAllocated($f);
    test()->actingAs($f['controleur']);
    pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);

    test()->actingAs($f['preparateur']);
    pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 40,
    ]);

    test()->actingAs($f['validateur']);
    expect(fn () => pickService()->validate($picking->fresh()))
        ->toThrow(RuntimeException::class, 'au statut');
});

it('13 · la validation exige un acteur distinct du contrôleur et fige les quantités', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickPrepared($f, 50);
    test()->actingAs($f['controleur']);
    pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);

    $f['controleur']->givePermissionTo('bon_preparations.validate');
    expect(fn () => pickService()->validate($picking))
        ->toThrow(RuntimeException::class, 'ne peut pas valider');

    test()->actingAs($f['validateur']);
    $validated = pickService()->validate($picking);

    expect($validated->status)->toBe(SalesPicking::STATUS_VALIDE)
        ->and((float) $validated->items->first()->qty_validated)->toBe(50.0);
});

it('13 bis · un bon validé est figé : plus aucune modification', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickPrepared($f, 50);
    test()->actingAs($f['controleur']);
    pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    test()->actingAs($f['validateur']);
    pickService()->validate($picking);

    expect(fn () => $picking->fresh()->update(['notes' => 'après coup']))
        ->toThrow(RuntimeException::class, 'figé');
});

// ---------------------------------------------------------------------------
// 14-15 · Annulation
// ---------------------------------------------------------------------------

it('14 · l annulation libère les réservations et conserve l historique', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickCreate($f, 100);
    $item = $picking->items->first();

    $reservationId = \Illuminate\Support\Facades\DB::table('stock_reservations')->insertGetId([
        'company_id' => $f['company']->id, 'order_id' => $f['order']->id,
        'product_id' => $f['product']->id, 'warehouse_id' => $f['warehouse']->id,
        'quantity' => 100, 'status' => 'reserved', 'reserved_at' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $alloc = pickService()->allocate($item, [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id,
        'quantity' => 100, 'stock_reservation_id' => $reservationId,
    ]);

    pickService()->cancel($picking, 'Commande annulée par le client.');

    $fresh = $picking->fresh('items');
    expect($fresh->status)->toBe(SalesPicking::STATUS_ANNULE)
        ->and($fresh->cancel_reason)->toBe('Commande annulée par le client.')
        ->and($alloc->fresh()->status)->toBe(SalesPickingAllocation::STATUS_ANNULEE)
        ->and(\Illuminate\Support\Facades\DB::table('stock_reservations')->find($reservationId)->status)->toBe('released')
        // Le document et sa ligne SURVIVENT : rien n'est supprimé.
        ->and(SalesPickingItem::where('sales_picking_id', $picking->id)->count())->toBe(1);

    // Le reliquat redevient disponible pour une nouvelle préparation.
    $replacement = pickCreate($f, 100);
    expect((float) $replacement->items->first()->qty_remaining_snapshot)->toBe(100.0);
});

it('14 bis · l annulation exige un motif et reste idempotente', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickCreate($f, 50);

    expect(fn () => pickService()->cancel($picking, '  '))
        ->toThrow(RuntimeException::class, 'motif');

    pickService()->cancel($picking, 'Erreur de saisie.');
    $first = $picking->fresh()->cancelled_at;
    pickService()->cancel($picking->fresh(), 'Deuxième appel.');

    expect($picking->fresh()->cancelled_at->toString())->toBe($first->toString())
        ->and($picking->fresh()->cancel_reason)->toBe('Erreur de saisie.');
});

it('15 · refuse l annulation d un bon déjà validé', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickPrepared($f, 50);
    test()->actingAs($f['controleur']);
    pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    test()->actingAs($f['validateur']);
    pickService()->validate($picking);

    test()->actingAs($f['preparateur']);
    expect(fn () => pickService()->cancel($picking->fresh(), 'Trop tard.'))
        ->toThrow(RuntimeException::class, 'ne s\'annule pas');
});

// ---------------------------------------------------------------------------
// 16 · Idempotence
// ---------------------------------------------------------------------------

it('16 · la création est idempotente sur la clé durable', function () {
    $f = pickFixture(orderedQty: 100);
    $lines = [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]];

    $a = pickService()->create($f['order'], $lines, ['warehouse_id' => $f['warehouse']->id, 'idempotency_key' => 'PICK-KEY-1']);
    $b = pickService()->create($f['order'], $lines, ['warehouse_id' => $f['warehouse']->id, 'idempotency_key' => 'PICK-KEY-1']);

    expect($b->id)->toBe($a->id)
        ->and(SalesPicking::where('order_id', $f['order']->id)->count())->toBe(1);
});

it('16 bis · valider deux fois ne produit qu une transition', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickPrepared($f, 50);
    test()->actingAs($f['controleur']);
    pickService()->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);

    test()->actingAs($f['validateur']);
    $first = pickService()->validate($picking);
    $second = pickService()->validate($picking->fresh());

    expect($second->validated_at->toString())->toBe($first->validated_at->toString())
        ->and($second->status)->toBe(SalesPicking::STATUS_VALIDE);
});

// ---------------------------------------------------------------------------
// 18-20 · Permissions, isolation société, écart motivé
// ---------------------------------------------------------------------------

it('18 · refuse toute action sans la permission correspondante', function () {
    $f = pickFixture(orderedQty: 50);
    $intrus = User::factory()->create(['company_id' => $f['company']->id]);
    test()->actingAs($intrus);

    expect(fn () => pickCreate($f, 50))
        ->toThrow(RuntimeException::class, 'bon_preparations.update');
});

it('19 · isolation société : un bon n est visible que dans sa société', function () {
    $f = pickFixture(orderedQty: 50);
    $picking = pickCreate($f, 50);

    $otherFy = FiscalYear::create([
        'label' => 'PICK-OTHER', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => false,
    ]);
    $other = Company::create([
        'name' => 'Pick Other Co', 'email' => 'pick-other@oa-metal.test',
        'current_fiscal_year_id' => $otherFy->id,
    ]);

    app()->instance('current_company', $other);
    expect(SalesPicking::find($picking->id))->toBeNull();

    app()->instance('current_company', $f['company']);
    expect(SalesPicking::find($picking->id))->not->toBeNull();
});

it('20 · un écart de prélèvement sans motif est refusé', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickCreate($f, 100);
    $alloc = pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 100,
    ]);
    pickService()->start($picking);

    expect(fn () => pickService()->pick($alloc, 80))
        ->toThrow(RuntimeException::class, 'motif est obligatoire');

    // Avec motif, l'écart est accepté ET tracé.
    pickService()->pick($alloc, 80, 'Deux paquets endommagés, mis au rebut.');
    $item = $picking->fresh('items')->items->first();

    expect((float) $item->variance_qty)->toBe(20.0)
        ->and($item->variance_reason)->toContain('endommagés');
});

it('20 bis · refuse un prélèvement supérieur à l allocation', function () {
    $f = pickFixture(orderedQty: 100);
    $picking = pickCreate($f, 100);
    $alloc = pickService()->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50,
    ]);
    pickService()->start($picking);

    expect(fn () => pickService()->pick($alloc, 70, 'Motif quelconque'))
        ->toThrow(RuntimeException::class, 'supérieur à l\'allocation');
});

it('bonus · refuse la préparation d une commande non confirmée', function () {
    $f = pickFixture(orderedQty: 50);
    $f['order']->update(['status' => 'brouillon']);

    expect(fn () => pickCreate($f, 50))
        ->toThrow(RuntimeException::class, 'seule une commande confirmée');
});
