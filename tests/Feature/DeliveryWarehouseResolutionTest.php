<?php

/**
 * [FIX livraison] Régression : un BL créé depuis une commande sans dépôt de
 * livraison explicite doit (A) hériter du dépôt qui détient réellement le stock
 * du produit fini, et (B) libérer la réservation de la commande AVANT la sortie,
 * pour que la commande ne bloque pas sa propre livraison (« Stock insuffisant »).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('livre depuis le dépôt détenant le stock et libère la réservation quand le BL n’a pas de dépôt', function () {
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'BLW-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $co = Company::firstOrCreate(['name' => 'BLW Co'], ['email' => 'blw@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    $this->actingAs($u);

    $default = Warehouse::create(['company_id' => $co->id, 'code' => 'DEF', 'name' => 'Défaut', 'is_default' => true, 'is_active' => true]);
    $pf      = Warehouse::create(['company_id' => $co->id, 'code' => 'PF', 'name' => 'Produits Finis', 'is_default' => false, 'is_active' => true]);

    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);

    // Stock uniquement au dépôt PF, entièrement réservé (état après réservation commande).
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $pf->id, 'quantity' => 3, 'reserved_quantity' => 3, 'avg_cost' => 1000]);

    $order = Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $fy->id,
        'client_id'      => Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-BLW-' . uniqid(),
        'status'         => 'confirme',
        'issued_at'      => now(),
        'total_ttc'      => 15000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 3, 'unit_price' => 5000, 'sort_order' => 0,
        'line_total_ht' => 15000, 'line_tax' => 0, 'line_total_ttc' => 15000,
    ]);

    $svc = app(DeliveryNoteService::class);

    // Fix A : le BL hérite du dépôt qui détient le stock (PF), pas null ni le défaut.
    $bl = $svc->createFromOrder($order->fresh());
    expect($bl->warehouse_id)->toBe($pf->id);

    // Fix B : la sortie libère la réservation d'abord → disponible suffisant, aucun blocage.
    $svc->applyStockOut($bl->fresh());

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $pf->id)->first();
    expect((float) $stock->quantity)->toBe(0.0);
    expect((float) $stock->reserved_quantity)->toBe(0.0);

    // La sortie est bien enregistrée au dépôt PF (pas au dépôt par défaut).
    $mv = StockMovement::where('reference_type', 'delivery_note')->where('reference_id', $bl->id)->first();
    expect($mv->warehouse_id)->toBe($pf->id);
    expect((float) $mv->quantity)->toBe(3.0);
});

it('ferme la ligne stock_reservations à la livraison (pas de réservation fantôme)', function () {
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'BLR-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $co = Company::firstOrCreate(['name' => 'BLR Co'], ['email' => 'blr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    $this->actingAs($u);

    $pf = Warehouse::create(['company_id' => $co->id, 'code' => 'PF2', 'name' => 'PF', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $pf->id, 'quantity' => 3, 'reserved_quantity' => 3, 'avg_cost' => 1000]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-BLR-' . uniqid(), 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 15000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 3, 'unit_price' => 5000, 'sort_order' => 0,
        'line_total_ht' => 15000, 'line_tax' => 0, 'line_total_ttc' => 15000,
    ]);

    // Réservation tracée en ligne (comme à la confirmation de commande).
    $res = \App\Models\StockReservation::create([
        'company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id,
        'warehouse_id' => $pf->id, 'quantity' => 3, 'status' => 'reserved', 'reserved_at' => now(),
    ]);

    $svc = app(DeliveryNoteService::class);
    $bl = $svc->createFromOrder($order->fresh());
    $svc->applyStockOut($bl->fresh());

    // La ligne réservation est FERMÉE (plus de fantôme au recompute) et le stock libéré.
    expect($res->fresh()->status)->toBe('released');
    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $pf->id)->first();
    expect((float) $stock->quantity)->toBe(0.0);
    expect((float) $stock->reserved_quantity)->toBe(0.0);
});

it('surface « Stock insuffisant » comme erreur VISIBLE au lieu d’un échec silencieux à la validation BL', function () {
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'BLE-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $co = Company::firstOrCreate(['name' => 'BLE Co'], ['email' => 'ble@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    $this->actingAs($u);

    Warehouse::create(['company_id' => $co->id, 'code' => 'BLE', 'name' => 'Défaut', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);
    // AUCUN ProductStock → 0 disponible → la sortie jette ValidationException.

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-BLE-' . uniqid(), 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 25000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 5, 'unit_price' => 5000, 'sort_order' => 0,
        'line_total_ht' => 25000, 'line_tax' => 0, 'line_total_ttc' => 25000,
    ]);

    $bl = app(DeliveryNoteService::class)->createFromOrder($order->fresh());
    $bl->update(['status' => 'en_attente_validation']);

    // Avant le fix : la ValidationException échappait au catch RuntimeException →
    // rollback silencieux, aucun message. Désormais : bannière d'erreurs visible.
    $resp = $this->post(route('ventes.bons-livraison.validate-internal', $bl));
    $resp->assertSessionHasErrors();

    // Rollback : le statut reste inchangé (rien persisté), pas de fausse réussite.
    expect($bl->fresh()->status)->toBe('en_attente_validation');
});
