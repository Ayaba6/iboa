<?php

/**
 * [CDC — Critère d'acceptation Stock / Inventaire]
 *
 * « Le module Stock sera accepté lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 * Les mouvements (PMP entrée/sortie), lots FIFO, réservations et pertes/casses sont
 * déjà couverts (StockServiceTest, LotTraceabilityTest, StockLossTest, ReservationReleaseTest).
 * Ce test complète les dimensions restantes du critère :
 *   - INVENTAIRE : session → comptage → validation → écart appliqué + mouvement + écriture GL
 *   - TRANSFERT INTER-DÉPÔTS : création → expédition (−source) → réception (+destination)
 *   - CONTRÔLES : transfert source = destination refusé
 *   - DROITS : accès module refusé sans permission
 *   - DOCUMENTS : export PDF de l'état des stocks
 */

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use App\Services\InventoryService;
use App\Services\StockService;
use App\Services\StockTransferService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function stkSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'STK'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'STK Co'], ['email' => 'stk@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return compact('co', 'u');
}

function stkSeedStock(Company $co, Warehouse $wh, Product $product, float $qty, int $cost = 6000): void
{
    app(StockService::class)->recordMovement([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'entree',
        'quantity' => $qty, 'unit_cost' => $cost, 'occurred_at' => now(),
    ]);
}

it('valide un inventaire : écart appliqué au stock + mouvement + écriture comptable', function () {
    $ctx = stkSetup();
    $co = $ctx['co'];
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-STK'], ['name' => 'Dépôt STK', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    stkSeedStock($co, $wh, $product, 100);

    $session = app(InventoryService::class)->create(['warehouse_id' => $wh->id, 'type' => 'complet']);
    $item = $session->items->firstWhere('product_id', $product->id);
    expect((float) $item->theoretical_quantity)->toBe(100.0);

    // Comptage réel : 90 (écart -10)
    app(InventoryService::class)->saveCount($session, [['id' => $item->id, 'counted_quantity' => 90]]);
    $validated = app(InventoryService::class)->validate($session->fresh());

    expect($validated->status)->toBe('valide')
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(90.0);

    // Mouvement d'inventaire tracé
    $move = StockMovement::where('reference_type', 'inventory_session')->where('reference_id', $session->id)->where('type', 'inventaire')->first();
    expect($move)->not->toBeNull()
        ->and((float) $move->quantity)->toBe(-10.0);

    // Écriture comptable d'écart d'inventaire équilibrée
    $gl = JournalEntry::where('reference', 'like', '%'.$session->number.'%')->first();
    expect($gl)->not->toBeNull()
        ->and($gl->total_debit)->toBe($gl->total_credit);
});

it('exécute un transfert inter-dépôts : expédition puis réception (impacts stock)', function () {
    $ctx = stkSetup();
    $co = $ctx['co'];
    $whA = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-A'], ['name' => 'Dépôt A', 'is_active' => true]);
    $whB = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-B'], ['name' => 'Dépôt B', 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    stkSeedStock($co, $whA, $product, 50);

    $svc = app(StockTransferService::class);
    $transfer = $svc->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 20]],
    ]);

    $svc->ship($transfer);
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whA->id)->value('quantity'))->toBe(30.0);

    $svc->receive($transfer->fresh());
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whB->id)->value('quantity'))->toBe(20.0);
});

it('refuse un transfert avec dépôt source identique à la destination (contrôle)', function () {
    $ctx = stkSetup();
    $wh = Warehouse::firstOrCreate(['company_id' => $ctx['co']->id, 'code' => 'WH-X'], ['name' => 'Dépôt X', 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true]);

    expect(fn () => app(StockTransferService::class)->create([
        'from_warehouse_id' => $wh->id, 'to_warehouse_id' => $wh->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ]))->toThrow(RuntimeException::class);
});

it('refuse l’accès au module Stock sans la permission (droits)', function () {
    $ctx = stkSetup();
    Permission::firstOrCreate(['name' => 'stocks.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits_stock', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $ctx['co']->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/stocks/dashboard')->assertForbidden();
});

it('génère l’export PDF de l’état des stocks (documents)', function () {
    stkSetup();

    $resp = $this->get('/stocks/export-pdf');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');
});

// [Matrice annulations — transfert] Annulation en transit réintègre la source ;
// après réception ou double annulation : refus.
it('annulation de transfert : réintégration en transit, refus après réception et double annulation', function () {
    $ctx = stkSetup();
    $co = $ctx['co'];
    $whA = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-TC1'], ['name' => 'Dépôt TC1', 'is_active' => true]);
    $whB = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-TC2'], ['name' => 'Dépôt TC2', 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    stkSeedStock($co, $whA, $product, 50);
    $svc = app(StockTransferService::class);

    // 1. En transit → annulation réintègre la source, rien en destination
    $t1 = $svc->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 20]],
    ]);
    $svc->ship($t1);
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whA->id)->value('quantity'))->toBe(30.0);

    $svc->cancel($t1->fresh(), 'Camion rappelé');
    expect($t1->fresh()->status)->toBe('annule')
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whA->id)->value('quantity'))->toBe(50.0)
        ->and((float) (ProductStock::where('product_id', $product->id)->where('warehouse_id', $whB->id)->value('quantity') ?? 0))->toBe(0.0);

    // 2. Double annulation → refusée (aucune double réintégration)
    expect(fn () => $svc->cancel($t1->fresh(), 'encore'))->toThrow(RuntimeException::class);
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whA->id)->value('quantity'))->toBe(50.0);

    // 3. Transfert REÇU → annulation refusée (flux terminé)
    $t2 = $svc->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10]],
    ]);
    $svc->ship($t2);
    $svc->receive($t2->fresh());
    expect(fn () => $svc->cancel($t2->fresh(), 'trop tard'))->toThrow(RuntimeException::class);
});

// [DÉCISION 23/07] Transfert partiellement reçu : l'écart est une PERTE EN
// TRANSIT comptabilisée (D 6097 / C 3111) — plus d'évaporation silencieuse.
it('comptabilise la perte en transit d\'un transfert partiellement reçu', function () {
    $ctx = stkSetup();
    $co = $ctx['co'];
    $whA = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-PL1'], ['name' => 'Dépôt PL1', 'is_active' => true]);
    $whB = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-PL2'], ['name' => 'Dépôt PL2', 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    stkSeedStock($co, $whA, $product, 50, 6000);
    $svc = app(StockTransferService::class);

    $t = $svc->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 20]],
    ]);
    $svc->ship($t);

    // Réception partielle : 15 reçues sur 20 → écart 5 × 6 000 = 30 000
    $item = $t->fresh('items')->items->first();
    $svc->receive($t->fresh(), [$item->id => 15]);

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whB->id)->value('quantity'))->toBe(15.0)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whA->id)->value('quantity'))->toBe(30.0);

    // Écriture de perte : D 6097 / C 3111 = 30 000, équilibrée, idempotente
    $entry = JournalEntry::where('reference', 'PERTE-TRANSIT-'.$t->number)->first();
    expect($entry)->not->toBeNull()
        ->and((int) $entry->total_debit)->toBe(30000)
        ->and((int) $entry->total_debit)->toBe((int) $entry->total_credit);
    $c6097 = Account::where('code', '6097')->first();
    expect((int) $c6097->debit_balance)->toBe(30000);

    // Journal d'audit alimenté
    expect(AuditLog::where('action', 'transfert.perte_transit')->where('model_id', $t->id)->exists())->toBeTrue();

    // Réception complète d'un autre transfert → AUCUNE écriture de perte
    stkSeedStock($co, $whA, $product, 10, 6000);
    $t2 = $svc->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id, 'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10]],
    ]);
    $svc->ship($t2);
    $svc->receive($t2->fresh());
    expect(JournalEntry::where('reference', 'PERTE-TRANSIT-'.$t2->number)->exists())->toBeFalse();
});

// [DÉCISION 23/07 — BL par lot] Lien formel : la validation du BL décrémente le
// lot rattaché, l'annulation le réintègre ; gardes quantité et cohérence produit.
it('BL lié à un lot : décrément à la validation, gardes, réintégration à l\'annulation', function () {
    $ctx = stkSetup();
    $co = $ctx['co'];
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-LOT'], ['name' => 'Dépôt LOT', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    stkSeedStock($co, $wh, $product, 30, 5000);
    $lot = StockLot::create([
        'company_id' => $co->id, 'product_id' => $product->id, 'warehouse_id' => $wh->id,
        'lot_number' => 'LOT-TB-001', 'quantity' => 12, 'unit_cost' => 5000, 'status' => 'disponible',
    ]);

    $dn = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'BL-LOT-'.uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create([
        'product_id' => $product->id, 'description' => 'Tôle bac', 'quantity' => 8,
        'stock_lot_id' => $lot->id,
    ]);

    $svc = app(DeliveryNoteService::class);
    $svc->validate($dn);

    expect((float) $lot->fresh()->quantity)->toBe(4.0)                       // 12 − 8
        ->and($dn->fresh()->items->first()->lot_number)->toBe('LOT-TB-001')  // n° aligné pour le PDF
        ->and((float) ProductStock::where('product_id', $product->id)->value('quantity'))->toBe(22.0);

    // Annulation : lot ET stock réintégrés
    $svc->cancelValidated($dn->fresh());
    expect((float) $lot->fresh()->quantity)->toBe(12.0)
        ->and((float) ProductStock::where('product_id', $product->id)->value('quantity'))->toBe(30.0);

    // Garde : quantité de lot insuffisante → refus AVANT toute sortie
    $dn2 = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'BL-LOT2-'.uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn2->items()->create([
        'product_id' => $product->id, 'description' => 'Tôle bac', 'quantity' => 25,
        'stock_lot_id' => $lot->id,
    ]);
    try {
        $svc->validate($dn2);
        $this->fail('La validation aurait dû être refusée (lot insuffisant).');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('insuffisante');
    }
    expect((float) $lot->fresh()->quantity)->toBe(12.0);

    // Garde : lot d'un AUTRE produit → refus
    $autre = Product::factory()->create(['is_stockable' => true]);
    stkSeedStock($co, $wh, $autre, 5, 1000);
    $dn3 = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'BL-LOT3-'.uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn3->items()->create([
        'product_id' => $autre->id, 'description' => 'Autre', 'quantity' => 2,
        'stock_lot_id' => $lot->id, // lot du produit tôle, ligne d'un autre article
    ]);
    try {
        $svc->validate($dn3);
        $this->fail('La validation aurait dû être refusée (lot étranger).');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('ne correspond pas');
    }
});
