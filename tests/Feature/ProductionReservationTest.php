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
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function resAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'R'], ['email' => 'r@r.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WR'], ['name' => 'WR', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}
function resOrder(): ProductionOrder
{
    $co = Company::first();
    $p = Product::factory()->create();
    $wh = Warehouse::where('company_id', $co->id)->first();
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    return ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-R'.rand(100, 999),
        'status' => 'termine', 'product_id' => $p->id, 'quantity_requested' => 30, 'quantity_produced' => 30, 'finished_at' => now()]);
}

it('reserves finished product and bumps reserved_quantity', function () {
    $this->actingAs(resAdmin());
    $o = resOrder();
    $wh = Warehouse::first();
    app(ReservationService::class)->reserveForOrder($o);
    $res = StockReservation::where('production_order_id', $o->id)->first();
    expect((float) $res->quantity)->toEqual(30.0);
    expect($res->status)->toBe('reserved');
    $stock = ProductStock::where('product_id', $o->product_id)->first();
    expect((float) $stock->reserved_quantity)->toEqual(30.0);
    expect((float) $stock->quantity - (float) $stock->reserved_quantity)->toEqual(70.0);
});

it('blocks reservation when nothing was produced (no fallback to requested)', function () {
    $this->actingAs(resAdmin());
    $o = resOrder();
    // OF terminé mais quantité produite 0 : on ne réserve pas la demande (30).
    $o->update(['quantity_produced' => 0]);
    expect(fn () => app(ReservationService::class)->reserveForOrder($o))->toThrow(ValidationException::class);
    expect(StockReservation::count())->toBe(0);
    expect((float) ProductStock::where('product_id', $o->product_id)->first()->reserved_quantity)->toEqual(0.0);
});

it('blocks reservation on non-finished OF', function () {
    $this->actingAs(resAdmin());
    $o = resOrder();
    $o->update(['status' => 'en_cours']);
    expect(fn () => app(ReservationService::class)->reserveForOrder($o))->toThrow(ValidationException::class);
    expect(StockReservation::count())->toBe(0);
});

it('releases a reservation and restores availability', function () {
    $this->actingAs(resAdmin());
    $o = resOrder();
    $res = app(ReservationService::class)->reserveForOrder($o);
    $this->post(route('production.reservations.release', $res))->assertRedirect();
    expect($res->fresh()->status)->toBe('released');
    expect((float) ProductStock::where('product_id', $o->product_id)->first()->reserved_quantity)->toEqual(0.0);
});

it('prevents double reservation', function () {
    $this->actingAs(resAdmin());
    $o = resOrder();
    $this->post(route('production.orders.reserve', $o))->assertRedirect();
    $this->post(route('production.orders.reserve', $o))->assertSessionHas('error');
    expect(StockReservation::where('production_order_id', $o->id)->where('status', 'reserved')->count())->toBe(1);
});

// [Analyse avancée 22/07] Une commande déjà livrée/facturée ne doit plus pouvoir
// re-réserver du stock (résa fantôme CMD-2026-050).
test('reserveStockForOrder refuse une commande livrée ou facturée', function () {
    resAdmin();
    $co = Company::first();
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-RESA-'.uniqid(), 'status' => 'facture',
        'issued_at' => now(), 'total_ttc' => 100000,
    ]);

    $reserved = app(ReservationService::class)
        ->reserveStockForOrder($order);

    expect($reserved)->toBe(0.0)
        ->and(DB::table('stock_reservations')->where('order_id', $order->id)->count())->toBe(0);
});

it('ne double pas une reservation client deja couverte par le stock', function () {
    $this->actingAs(resAdmin());
    $of = resOrder();
    $company = Company::first();
    $warehouse = Warehouse::where('company_id', $company->id)->first();
    $order = Order::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-RESA-COVERED',
        'status' => 'confirme',
        'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $of->product_id,
        'description' => 'PF reserve',
        'quantity' => 30,
        'delivered_quantity' => 0,
        'unit_price' => 1000,
        'line_total_ht' => 30000,
        'line_tax' => 0,
        'line_total_ttc' => 30000,
    ]);
    $of->update(['order_id' => $order->id]);
    StockReservation::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $of->product_id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 30,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);
    ProductStock::where('product_id', $of->product_id)->where('warehouse_id', $warehouse->id)
        ->update(['reserved_quantity' => 30]);

    expect(fn () => app(ReservationService::class)->reserveForOrder($of->fresh()))
        ->toThrow(ValidationException::class);
    expect(StockReservation::where('order_id', $order->id)->where('status', 'reserved')->count())->toBe(1)
        ->and((float) ProductStock::where('product_id', $of->product_id)->where('warehouse_id', $warehouse->id)->value('reserved_quantity'))->toEqual(30.0);
});

it('reserve le produit libere dans le depot de destination qualite', function () {
    $this->actingAs(resAdmin());
    $of = resOrder();
    $company = Company::first();
    $quarantine = Warehouse::create(['company_id' => $company->id, 'code' => 'WQ', 'name' => 'Quarantaine', 'is_active' => true]);
    $release = Warehouse::create(['company_id' => $company->id, 'code' => 'WPF', 'name' => 'Produits finis', 'is_active' => true]);
    ProductStock::create(['product_id' => $of->product_id, 'warehouse_id' => $quarantine->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    ProductStock::create(['product_id' => $of->product_id, 'warehouse_id' => $release->id, 'quantity' => 30, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    ProductionOutput::create([
        'company_id' => $company->id,
        'production_order_id' => $of->id,
        'product_id' => $of->product_id,
        'warehouse_id' => $quarantine->id,
        'release_warehouse_id' => $release->id,
        'quality_released_at' => now(),
        'quantity' => 30,
        'total_meters' => 30,
        'produced_at' => now(),
        'status' => 'validee',
    ]);

    $reservation = app(ReservationService::class)->reserveForOrder($of->fresh());

    expect($reservation->warehouse_id)->toBe($release->id)
        ->and((float) ProductStock::where('product_id', $of->product_id)->where('warehouse_id', $release->id)->value('reserved_quantity'))->toEqual(30.0)
        ->and((float) ProductStock::where('product_id', $of->product_id)->where('warehouse_id', $quarantine->id)->value('reserved_quantity'))->toEqual(0.0);
});
