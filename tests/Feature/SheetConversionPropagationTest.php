<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * [§5 Tôle bac] Le nombre de tôles et la longueur unitaire restent présents
 * de la commande jusqu'au BL puis à la facture (CDC test 3).
 */
it('propage nb tôles / longueur unitaire de la commande au BL puis à la facture', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'SC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SC Co'], ['email' => 'sc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($u);

    $pf = Warehouse::create(['company_id' => $co->id, 'code' => 'PF', 'name' => 'PF', 'is_default' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_active' => true, 'is_stockable' => true]);

    // 10 tôles × 5 m = 50 mètres linéaires ; stock suffisant, réservé.
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $pf->id, 'quantity' => 50, 'reserved_quantity' => 50, 'avg_cost' => 1000]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-SC-' . uniqid(), 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'description' => 'TOLE BAC 5M',
        'quantity' => 50, 'nb_toles' => 10, 'metrage_par_tole' => 5,
        'unit_price' => 2000, 'sort_order' => 0,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    // Commande → BL : nb tôles / longueur hérités.
    $bl = app(DeliveryNoteService::class)->createFromOrder($order->fresh());
    $blItem = $bl->items->first();
    expect((float) $blItem->nb_toles)->toBe(10.0)
        ->and((float) $blItem->metrage_par_tole)->toBe(5.0)
        ->and((float) $blItem->quantity)->toBe(50.0); // métrage total conservé

    // BL → facture : nb tôles / longueur hérités.
    $invoice = app(InvoiceService::class)->createFromDeliveryNote($bl->fresh());
    $invItem = $invoice->items->first();
    expect((float) $invItem->nb_toles)->toBe(10.0)
        ->and((float) $invItem->metrage_par_tole)->toBe(5.0)
        ->and((float) $invItem->quantity)->toBe(50.0);
});
