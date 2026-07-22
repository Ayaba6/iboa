<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Product;
use App\Models\Warehouse; use App\Models\ProductStock; use App\Models\StockReservation;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function resAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'R'],['email'=>'r@r.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'WR'],['name'=>'WR','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $role=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($role);
    return $u;
}
function resOrder(): ProductionOrder {
    $co=Company::first(); $p=Product::factory()->create();
    $wh=Warehouse::where('company_id',$co->id)->first();
    ProductStock::create(['product_id'=>$p->id,'warehouse_id'=>$wh->id,'quantity'=>100,'reserved_quantity'=>0,'avg_cost'=>1000]);
    return ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-R'.rand(100,999),
        'status'=>'termine','product_id'=>$p->id,'quantity_requested'=>30,'quantity_produced'=>30,'finished_at'=>now()]);
}

it('reserves finished product and bumps reserved_quantity', function(){
    $this->actingAs(resAdmin());
    $o=resOrder(); $wh=Warehouse::first();
    app(ReservationService::class)->reserveForOrder($o);
    $res=StockReservation::where('production_order_id',$o->id)->first();
    expect((float)$res->quantity)->toEqual(30.0);
    expect($res->status)->toBe('reserved');
    $stock=ProductStock::where('product_id',$o->product_id)->first();
    expect((float)$stock->reserved_quantity)->toEqual(30.0);
    expect((float)$stock->quantity - (float)$stock->reserved_quantity)->toEqual(70.0);
});

it('blocks reservation when nothing was produced (no fallback to requested)', function(){
    $this->actingAs(resAdmin());
    $o=resOrder();
    // OF terminé mais quantité produite 0 : on ne réserve pas la demande (30).
    $o->update(['quantity_produced'=>0]);
    expect(fn()=>app(ReservationService::class)->reserveForOrder($o))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(StockReservation::count())->toBe(0);
    expect((float)ProductStock::where('product_id',$o->product_id)->first()->reserved_quantity)->toEqual(0.0);
});

it('blocks reservation on non-finished OF', function(){
    $this->actingAs(resAdmin());
    $o=resOrder(); $o->update(['status'=>'en_cours']);
    expect(fn()=>app(ReservationService::class)->reserveForOrder($o))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(StockReservation::count())->toBe(0);
});

it('releases a reservation and restores availability', function(){
    $this->actingAs(resAdmin());
    $o=resOrder();
    $res=app(ReservationService::class)->reserveForOrder($o);
    $this->post(route('production.reservations.release',$res))->assertRedirect();
    expect($res->fresh()->status)->toBe('released');
    expect((float)ProductStock::where('product_id',$o->product_id)->first()->reserved_quantity)->toEqual(0.0);
});

it('prevents double reservation', function(){
    $this->actingAs(resAdmin());
    $o=resOrder();
    $this->post(route('production.orders.reserve',$o))->assertRedirect();
    $this->post(route('production.orders.reserve',$o))->assertSessionHas('error');
    expect(StockReservation::where('production_order_id',$o->id)->where('status','reserved')->count())->toBe(1);
});

// [Analyse avancée 22/07] Une commande déjà livrée/facturée ne doit plus pouvoir
// re-réserver du stock (résa fantôme CMD-2026-050).
test('reserveStockForOrder refuse une commande livrée ou facturée', function () {
    resAdmin();
    $co = \App\Models\Company::first();
    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => \App\Models\Client::factory()->create()->id,
        'number' => 'CMD-RESA-' . uniqid(), 'status' => 'facture',
        'issued_at' => now(), 'total_ttc' => 100000,
    ]);

    $reserved = app(\App\Modules\Production\Services\ReservationService::class)
        ->reserveStockForOrder($order);

    expect($reserved)->toBe(0.0)
        ->and(\Illuminate\Support\Facades\DB::table('stock_reservations')->where('order_id', $order->id)->count())->toBe(0);
});
