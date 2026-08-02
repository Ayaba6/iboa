<?php
use App\Models\Client; use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Models\Order; use App\Models\Product; use App\Models\Quote;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\SalesProductionService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function kpiAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'KP'],['email'=>'kp@kp.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}

it('computes production-aware sales KPIs', function(){
    $this->actingAs(kpiAdmin()); $co=Company::first();
    $client=Client::factory()->create(); $p=Product::factory()->create();
    // commande en production (OF en_cours)
    $o1=Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'C1','status'=>'confirme','issued_at'=>now()]);
    ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF1','status'=>'en_cours','order_id'=>$o1->id,'quantity_requested'=>10]);
    // commande prête à livrer (OF terminé + output)
    $o2=Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'C2','status'=>'en_preparation','issued_at'=>now()]);
    $of2=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF2','status'=>'termine','order_id'=>$o2->id,'product_id'=>$p->id,'quantity_requested'=>5,'quantity_produced'=>5,'finished_at'=>now()]);
    $of2->outputs()->create(['company_id'=>$co->id,'product_id'=>$p->id,'length'=>6,'quantity'=>5,'total_meters'=>30,'produced_at'=>now()]);
    // livrée non facturée
    Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'C3','status'=>'livre','issued_at'=>now()]);
    // Devis : 1 converti, 1 brouillon, 1 refuse.
    // Le brouillon n'est PAS au denominateur — il n'a jamais ete propose au
    // client, donc il n'a pas pu etre converti. Le refuse, lui, compte : c'est
    // une offre sortie et perdue. Attendu : 1 converti / 2 offres sorties = 50 %.
    Quote::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'Q1','status'=>'converti','issued_at'=>now()]);
    Quote::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'Q2','status'=>'brouillon','issued_at'=>now()]);
    Quote::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'Q3','status'=>'refuse','issued_at'=>now()]);

    $k=app(SalesProductionService::class)->dashboardKpis();
    expect($k['en_production'])->toBe(1);
    expect($k['pretes_a_livrer'])->toBe(1);
    expect($k['livrees_non_facturees'])->toBe(1);
    expect($k['taux_transfo'])->toEqual(50.0);
});
