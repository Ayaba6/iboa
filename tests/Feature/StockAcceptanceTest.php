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

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockService;
use App\Services\StockTransferService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

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
    $co  = $ctx['co'];
    $wh  = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-STK'], ['name' => 'Dépôt STK', 'is_default' => true, 'is_active' => true]);
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
    $co  = $ctx['co'];
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
    ]))->toThrow(\RuntimeException::class);
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
