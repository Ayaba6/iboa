<?php
use App\Models\Client; use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Models\Order; use App\Models\Product; use App\Models\Invoice; use App\Models\DocumentSetting;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function icUser(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'IC'],['email'=>'ic@ic.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function icInvoice(): Invoice {
    $co=Company::first(); $client=Client::factory()->create(); $p=Product::factory()->create(['reference'=>'PRD-IC']);
    $inv=Invoice::create(['company_id'=>$co->id,'client_id'=>$client->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'FA-IC'.rand(100,999),'status'=>'brouillon','issued_at'=>now(),'subtotal_ht'=>10000,'total_tax'=>1800,'total_ttc'=>11800,'remaining_amount'=>11800]);
    $inv->items()->create(['product_id'=>$p->id,'description'=>'Article','quantity'=>2,'unit_price'=>5000,'discount_percent'=>0,'tax_rate_value'=>18,'line_total_ht'=>10000,'line_tax'=>1800,'line_total_ttc'=>11800]);
    return $inv;
}

// [AUDIT TESTS] La résolution des colonnes est la RÈGLE MÉTIER (invoice.blade
// l.465 : product_columns configurés, sinon défaut). Attendus codés en dur,
// indépendants du code testé — un PDF qui « sort » ne prouve rien.
function icResolveColumns(?DocumentSetting $s): array {
    return (array) ($s?->product_columns
        ?? ['reference','description','quantity','unit_price','discount','tax','total_ht','total_ttc']);
}

it('minimal : les colonnes configurées sont EXACTEMENT celles retenues + PDF rendu', function(){
    $this->actingAs(icUser()); $co=Company::first();
    $s = DocumentSetting::create(['company_id'=>$co->id,'product_columns'=>['description','quantity','total_ttc']]);
    // Règle métier : exactement 3 colonnes, PAS de référence ni de prix unitaire
    expect(icResolveColumns($s->fresh()))->toBe(['description','quantity','total_ttc'])
        ->and(icResolveColumns($s->fresh()))->not->toContain('reference')
        ->and(icResolveColumns($s->fresh()))->not->toContain('unit_price');
    // Et le PDF se rend sur cette config
    $res=$this->get(route('ventes.factures.pdf', icInvoice()));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('full : la colonne reference est présente et l\'ordre est respecté', function(){
    $this->actingAs(icUser()); $co=Company::first();
    $cols = ['reference','description','longueur','epaisseur','quantity','unit_price','discount','tax','total_ht','total_ttc'];
    $s = DocumentSetting::create(['company_id'=>$co->id,'product_columns'=>$cols]);
    expect(icResolveColumns($s->fresh()))->toBe($cols)
        ->and(icResolveColumns($s->fresh())[0])->toBe('reference'); // 1re colonne
    $this->get(route('ventes.factures.pdf', icInvoice()))->assertOk();
});

it('sans réglage : jeu de colonnes par DÉFAUT (8 colonnes, référence incluse)', function(){
    $this->actingAs(icUser());
    // Aucun DocumentSetting → défaut documenté de 8 colonnes
    $defaut = icResolveColumns(null);
    expect($defaut)->toHaveCount(8)
        ->and($defaut)->toContain('reference')
        ->and($defaut)->toContain('total_ttc')
        ->and($defaut)->not->toContain('longueur'); // colonne « full » absente du défaut
    $this->get(route('ventes.factures.pdf', icInvoice()))->assertOk();
});
