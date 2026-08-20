<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\ReservationService;
use App\Modules\Production\Services\SalesProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function v2Admin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'V2'], ['email' => 'v2@v2.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WV2'], ['name' => 'WV2', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function v2OrderWithStock(int $ordered, int $inStock): array
{
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $product = Product::factory()->create();
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => $inStock, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => Client::factory()->create()->id, 'number' => 'CMD-V2' . rand(100, 999), 'status' => 'confirme', 'issued_at' => now()]);
    $order->items()->create(['product_id' => $product->id, 'description' => 'PF', 'quantity' => $ordered, 'unit_price' => 1000, 'line_total_ht' => $ordered*1000, 'line_tax' => 0, 'line_total_ttc' => $ordered*1000]);

    return [$order, $product, $wh];
}

it('analyses stock: full stock available', function () {
    $this->actingAs(v2Admin());
    [$order] = v2OrderWithStock(10, 30);
    $a = app(SalesProductionService::class)->stockAnalysis($order);
    $l = $a['lines']->first();
    expect($l['available'])->toEqual(30.0);
    expect($l['reservable'])->toEqual(10.0);
    expect($l['to_produce'])->toEqual(0.0);
    expect($l['decision'])->toBe('stock');
});

it('analyses stock: mixed (partial stock, rest to produce)', function () {
    $this->actingAs(v2Admin());
    [$order] = v2OrderWithStock(50, 20);
    $l = app(SalesProductionService::class)->stockAnalysis($order)['lines']->first();
    expect($l['reservable'])->toEqual(20.0);
    expect($l['to_produce'])->toEqual(30.0);
    expect($l['decision'])->toBe('mixed');
});

it('analyses stock: fully delivered order no longer shows to-produce', function () {
    $this->actingAs(v2Admin());
    [$order] = v2OrderWithStock(3, 0);          // 3 commandés, plus rien en stock (déjà sorti)
    $order->items()->first()->update(['delivered_quantity' => 3]); // livré à 100 %
    $l = app(SalesProductionService::class)->stockAnalysis($order->fresh())['lines']->first();
    expect($l['to_produce'])->toEqual(0.0);
    expect($l['reservable'])->toEqual(0.0);
    expect($l['decision'])->toBe('livre');
});

it('reserves available finished product from stock', function () {
    $this->actingAs(v2Admin());
    [$order, $product, $wh] = v2OrderWithStock(10, 30);
    $reserved = app(ReservationService::class)->reserveStockForOrder($order);
    expect($reserved)->toEqual(10.0);
    expect((float) ProductStock::where('product_id', $product->id)->first()->reserved_quantity)->toEqual(10.0);
    expect(StockReservation::where('order_id', $order->id)->where('status', 'reserved')->sum('quantity'))->toEqual(10.0);
});

it('reserves via route and is re-runnable without over-reserving', function () {
    $this->actingAs(v2Admin());
    [$order, $product] = v2OrderWithStock(10, 30);
    $this->post(route('production.sales.reserve-stock', $order))->assertRedirect();
    // second run: nothing left to reserve for this order (already 10)
    $this->post(route('production.sales.reserve-stock', $order))->assertSessionHas('error');
    expect((float) ProductStock::where('product_id', $product->id)->first()->reserved_quantity)->toEqual(10.0);
});

it('unifie les réservations : OrderService::reserveStock crée une ligne et ne double pas avec le bouton manuel', function () {
    $this->actingAs(v2Admin());
    [$order, $product] = v2OrderWithStock(3, 10);

    // Chemin « confirmation » (listener) — écrit désormais une ligne stock_reservations.
    app(\App\Services\OrderService::class)->reserveStock($order->fresh());
    expect((float) ProductStock::where('product_id', $product->id)->first()->reserved_quantity)->toEqual(3.0);
    expect(StockReservation::where('order_id', $order->id)->where('status', 'reserved')->count())->toBe(1);

    // Bouton « Réserver » manuel — idempotent, aucune double réservation.
    app(ReservationService::class)->reserveStockForOrder($order->fresh());
    expect((float) ProductStock::where('product_id', $product->id)->first()->reserved_quantity)->toEqual(3.0);
    expect(StockReservation::where('order_id', $order->id)->where('status', 'reserved')->sum('quantity'))->toEqual(3.0);

    // Annulation — libère tout, aucune réservation fantôme.
    app(\App\Services\OrderService::class)->cancel($order->fresh(), 'Annulation de test — remise du stock réservé.');
    expect((float) ProductStock::where('product_id', $product->id)->first()->reserved_quantity)->toEqual(0.0);
    expect(StockReservation::where('order_id', $order->id)->where('status', 'reserved')->count())->toBe(0);
});
