<?php

/**
 * [MTO §15] Garde de livraison des commandes fabriquées.
 *
 * ADAPTATION DOCUMENTÉE — durcissement du 30/07/2026. Trois attentes de ce
 * fichier ont dû changer, et aucune ne l'a été pour faire passer la suite :
 *
 *   1. `dgScenario()` créait une déclaration de production nue : ni visa du chef
 *      d'équipe, ni libération par la qualité. Elle comptait pourtant dans le
 *      disponible, parce que le garde sommait `outputs.quantity` sans regarder
 *      `status`, `validated_at` ni `quality_released_at`. C'était précisément le
 *      trou à combler ; la fixture est donc complétée, pas la règle assouplie.
 *
 *   2-3. Les deux tests de refus refusent toujours — seuls leurs MESSAGES ont
 *      changé, le garde nommant désormais l'article et la quantité conforme
 *      réellement disponible. Le comportement vérifié est identique.
 */

use App\Models\Client; use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Models\Order; use App\Models\Product; use App\Models\Warehouse; use App\Models\ProductStock;
use App\Models\DeliveryNote;
use App\Modules\Production\Models\ProductionOrder; use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Services\DeliveryNoteService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function dgAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'DG'],['email'=>'dg@dg.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'WDG'],['name'=>'WDG','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function dgScenario(int $ordered, int $produced, ?string $qc): array {
    $co=Company::first(); $wh=Warehouse::where('company_id',$co->id)->first();
    $product=Product::factory()->create(['is_stockable'=>true,'valuation_method'=>'cmp']);
    ProductStock::create(['product_id'=>$product->id,'warehouse_id'=>$wh->id,'quantity'=>1000,'reserved_quantity'=>0,'avg_cost'=>100]);
    $order=Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>Client::factory()->create()->id,'number'=>'CMD-DG'.rand(100,999),'status'=>'confirme','issued_at'=>now()]);
    $order->items()->create(['product_id'=>$product->id,'description'=>'PF','quantity'=>$ordered,'unit_price'=>1000,'line_total_ht'=>$ordered*1000,'line_tax'=>0,'line_total_ttc'=>$ordered*1000]);
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-DG'.rand(100,999),'status'=>'termine','order_id'=>$order->id,'product_id'=>$product->id,'quantity_requested'=>$ordered,'quantity_produced'=>$produced,'finished_at'=>now()]);
    // [MTO §15] Une declaration ne compte comme livrable que VISEE par le chef
    // d'equipe ET LIBEREE par la qualite. Sans ces trois champs, la fixture
    // decrirait une production que la regle refuse - et c'est voulu.
    if($produced>0) $of->outputs()->create(['company_id'=>$co->id,'product_id'=>$product->id,'length'=>6,'quantity'=>$produced,'total_meters'=>$produced*6,'produced_at'=>now(),'status'=>'validee','validated_at'=>now(),'quality_released_at'=>now()]);
    if($qc) ProductionQualityControl::create(['company_id'=>$co->id,'production_order_id'=>$of->id,'thickness_ok'=>$qc==='conforme','length_ok'=>true,'color_ok'=>$qc==='conforme','visual_ok'=>$qc==='conforme','status'=>$qc,'rejected_quantity'=>0,'controlled_at'=>now()]);
    $dn=DeliveryNote::create(['company_id'=>$co->id,'client_id'=>$order->client_id,'order_id'=>$order->id,'number'=>'BL-DG'.rand(100,999),'issued_at'=>now(),'status'=>'brouillon','warehouse_id'=>$wh->id]);
    $dn->items()->create(['product_id'=>$product->id,'description'=>'PF','quantity'=>$ordered,'unit_price'=>1000]);
    return [$order,$dn];
}

it('allows delivery when produced enough and QC conforme', function(){
    $this->actingAs(dgAdmin());
    [$o,$dn]=dgScenario(10,10,'conforme');
    app(DeliveryNoteService::class)->validate($dn);
    expect($dn->fresh()->status)->toBe('valide');
});

it('blocks delivery when QC non conforme', function(){
    $this->actingAs(dgAdmin());
    [$o,$dn]=dgScenario(10,10,'non_conforme');
    expect(fn()=>app(DeliveryNoteService::class)->validate($dn))->toThrow(\RuntimeException::class, 'contrôle qualité « non conforme »');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('blocks delivery when produced quantity insufficient', function(){
    $this->actingAs(dgAdmin());
    [$o,$dn]=dgScenario(10,4,'conforme'); // produced 4 < ordered 10
    expect(fn()=>app(DeliveryNoteService::class)->validate($dn))->toThrow(\RuntimeException::class, 'réellement conforme et disponible');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('does not block non-production orders', function(){
    $this->actingAs(dgAdmin());
    $co=Company::first(); $wh=Warehouse::first();
    $product=Product::factory()->create(['is_stockable'=>true,'valuation_method'=>'cmp']);
    ProductStock::create(['product_id'=>$product->id,'warehouse_id'=>$wh->id,'quantity'=>100,'reserved_quantity'=>0,'avg_cost'=>100]);
    $order=Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>Client::factory()->create()->id,'number'=>'CMD-NP','status'=>'confirme','issued_at'=>now()]);
    $order->items()->create(['product_id'=>$product->id,'description'=>'X','quantity'=>5,'unit_price'=>1000,'line_total_ht'=>5000,'line_tax'=>0,'line_total_ttc'=>5000]);
    $dn=DeliveryNote::create(['company_id'=>$co->id,'client_id'=>$order->client_id,'order_id'=>$order->id,'number'=>'BL-NP','issued_at'=>now(),'status'=>'brouillon','warehouse_id'=>$wh->id]);
    $dn->items()->create(['product_id'=>$product->id,'description'=>'X','quantity'=>5,'unit_price'=>1000]);
    app(DeliveryNoteService::class)->validate($dn);
    expect($dn->fresh()->status)->toBe('valide');
});
