<?php
use App\Models\Client; use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Models\Order; use App\Models\Product; use App\Models\Warehouse; use App\Models\ProductStock; use App\Models\StockReservation;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function relAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'REL'],['email'=>'rel@rel.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'WREL'],['name'=>'WREL','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function relOrderWithReservation(): array {
    $co=Company::first(); $wh=Warehouse::where('company_id',$co->id)->first();
    $p=Product::factory()->create();
    $ps=ProductStock::create(['product_id'=>$p->id,'warehouse_id'=>$wh->id,'quantity'=>50,'reserved_quantity'=>0,'avg_cost'=>100]);
    $order=Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>Client::factory()->create()->id,'number'=>'CMD-REL'.rand(100,999),'status'=>'confirme','issued_at'=>now()]);
    $order->items()->create(['product_id'=>$p->id,'description'=>'PF','quantity'=>10,'unit_price'=>1000,'line_total_ht'=>10000,'line_tax'=>0,'line_total_ttc'=>10000]);
    app(ReservationService::class)->reserveStockForOrder($order);
    return [$order,$p,$ps];
}

it('releases reservations when order cancelled', function(){
    $this->actingAs(relAdmin());
    [$order,$p]=relOrderWithReservation();
    expect((float)ProductStock::where('product_id',$p->id)->first()->reserved_quantity)->toEqual(10.0);
    app(OrderService::class)->cancel($order);
    expect((float)ProductStock::where('product_id',$p->id)->first()->reserved_quantity)->toEqual(0.0);
    expect(StockReservation::where('order_id',$order->id)->where('status','reserved')->count())->toBe(0);
});

it('releases OF-linked reservations when OF cancelled', function(){
    $this->actingAs(relAdmin());
    $co=Company::first(); $wh=Warehouse::first(); $p=Product::factory()->create();
    $ps=ProductStock::create(['product_id'=>$p->id,'warehouse_id'=>$wh->id,'quantity'=>20,'reserved_quantity'=>0,'avg_cost'=>100]);
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-REL','status'=>'termine','product_id'=>$p->id,'quantity_requested'=>8,'quantity_produced'=>8,'finished_at'=>now()]);
    $of->outputs()->create(['company_id'=>$co->id,'product_id'=>$p->id,'length'=>6,'quantity'=>8,'total_meters'=>48,'warehouse_id'=>$wh->id,'produced_at'=>now()]);
    app(ReservationService::class)->reserveForOrder($of);
    expect((float)ProductStock::where('product_id',$p->id)->first()->reserved_quantity)->toEqual(8.0);
    $of->update(['status'=>'en_cours']); // make cancellable
    // [Règle formelle] Un OF avec déclaration vivante est inannulable : extourner d'abord.
    expect(fn()=>app(\App\Modules\Production\Services\ProductionService::class)->cancel($of->fresh(),'test'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    $of->outputs()->update(['status'=>'annulee']);
    app(\App\Modules\Production\Services\ProductionService::class)->cancel($of->fresh(),'test');
    expect((float)ProductStock::where('product_id',$p->id)->first()->reserved_quantity)->toEqual(0.0);
});
