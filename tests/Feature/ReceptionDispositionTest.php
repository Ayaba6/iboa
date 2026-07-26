<?php

/**
 * [ACHATS Réceptions #1/#7/#8] Ventilation des quantités de réception :
 * reçu = accepté + quarantaine + refusé. Routage stock : accepté → dépôt
 * utilisable, quarantaine → DÉPÔT QUAR (non disponible), refusé → aucun stock.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function recSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'REC'], ['email' => 'rec@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $wh   = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-ACH'], ['name' => 'Dépôt achats', 'is_default' => true, 'is_active' => true, 'can_purchase' => true]);
    $quar = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-QUAR'], ['name' => 'Dépôt Quarantaine', 'is_active' => true]);
    $sup  = Supplier::factory()->create();
    $p    = Product::factory()->create(['is_stockable' => true]);

    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'fiscal_year_id' => $fy->id,
        'number' => 'BC-' . uniqid(), 'status' => 'confirme', 'ordered_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 100000, 'total_tax' => 0, 'total_ttc' => 100000,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'product_id' => $p->id, 'description' => 'Matière',
        'quantity' => 100, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
        'received_quantity' => 0, 'accepted_quantity' => 0, 'invoiced_quantity' => 0,
    ]);
    $rec = Reception::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'purchase_order_id' => $po->id,
        'number' => 'RCP-' . uniqid(), 'status' => 'brouillon', 'received_at' => now(),
        'created_by' => $u->id,
    ]);
    $recItem = $rec->items()->create([
        'purchase_order_item_id' => $poItem->id, 'product_id' => $p->id, 'description' => 'Matière',
        'expected_quantity' => 100, 'received_quantity' => 0, 'rejected_quantity' => 0, 'unit_cost' => 1000,
    ]);

    return [$co, $wh, $quar, $p, $po, $poItem, $rec, $recItem];
}

it('ventile 100 reçu = 70 accepté + 20 quarantaine + 10 refusé, avec routage stock', function () {
    [$co, $wh, $quar, $p, $po, $poItem, $rec, $recItem] = recSetup();

    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $recItem->id => ['received_quantity' => 100, 'accepted_quantity' => 70, 'quarantine_quantity' => 20, 'refused_quantity' => 10],
    ]);

    // Ligne de réception ventilée
    $ri = $recItem->fresh();
    expect((float) $ri->received_quantity)->toBe(100.0)
        ->and((float) $ri->accepted_quantity)->toBe(70.0)
        ->and((float) $ri->quarantine_quantity)->toBe(20.0)
        ->and((float) $ri->rejected_quantity)->toBe(10.0)
        ->and($ri->disposition_origin)->toBe('saisie');

    // Agrégat BC : accepté 70, reçu 100
    expect((float) $poItem->fresh()->accepted_quantity)->toBe(70.0)
        ->and((float) $poItem->fresh()->received_quantity)->toBe(100.0);

    // Stock : dépôt utilisable +70 ; quarantaine +20 ; refusé → rien
    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(70.0)
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity'))->toBe(20.0);
    // Somme des mouvements = 90 (accepté + quarantaine), jamais 100.
    expect((int) \App\Models\StockMovement::where('reference_type', 'reception')->where('reference_id', $rec->id)->count())->toBe(2);
});

it('refuse une ventilation dont la somme ≠ reçu (invariant reçu = accepté + quarantaine + refusé)', function () {
    [$co, $wh, $quar, $p, $po, $poItem, $rec, $recItem] = recSetup();

    expect(fn () => app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $recItem->id => ['received_quantity' => 100, 'accepted_quantity' => 70, 'quarantine_quantity' => 20, 'refused_quantity' => 5],
    ]))->toThrow(\RuntimeException::class, 'Ventilation incohérente');

    // Rien n'a été validé (transaction annulée) : réception toujours brouillon, pas de stock.
    expect($rec->fresh()->status)->toBe('brouillon')
        ->and((float) (ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity') ?? 0))->toBe(0.0);
});

it('sans ventilation, tout le reçu est accepté (rétro-compatibilité)', function () {
    [$co, $wh, $quar, $p, $po, $poItem, $rec, $recItem] = recSetup();

    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $recItem->id => ['received_quantity' => 100],
    ]);

    expect((float) $recItem->fresh()->accepted_quantity)->toBe(100.0)
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(100.0)
        ->and((float) (ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity') ?? 0))->toBe(0.0);
});
