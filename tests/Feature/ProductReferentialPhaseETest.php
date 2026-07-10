<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\ProductRepository;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function refAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'REF'], ['email' => 'ref@ref.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('refuse un article vendable avec prix de vente inférieur au prix d’achat (vente à perte)', function () {
    $this->actingAs(refAdmin());
    $fam = ProductFamily::create(['name' => 'Négoce', 'code' => 'NEG']);

    $payload = [
        'name' => 'Article perte', 'reference' => 'REF-PERTE', 'type' => 'simple',
        'is_sellable' => 1, 'is_purchasable' => 1, 'is_stockable' => 1,
        'product_family_id' => $fam->id, 'purchase_price' => 3000, 'sale_price' => 2000,
    ];

    // Vente < achat → rejet avec message explicite, article non créé.
    $this->post(route('products.store'), $payload)->assertSessionHasErrors('sale_price');
    expect(Product::where('reference', 'REF-PERTE')->exists())->toBeFalse();

    // Vente ≥ achat → accepté.
    $this->post(route('products.store'), array_merge($payload, ['reference' => 'REF-OK', 'sale_price' => 5000]))
        ->assertSessionHasNoErrors();
    expect(Product::where('reference', 'REF-OK')->exists())->toBeTrue();
});

it('persists the enriched referential fields', function () {
    $this->actingAs(refAdmin());
    $f1 = ProductFamily::create(['name' => 'Niveau 1', 'code' => 'N1']);
    $f2 = ProductFamily::create(['name' => 'Niveau 2', 'code' => 'N2']);
    $f3 = ProductFamily::create(['name' => 'Niveau 3', 'code' => 'N3']);
    $ua = Unit::firstOrCreate(['abbreviation' => 'kg'], ['name' => 'Kilo', 'type' => 'poids']);
    $uv = Unit::firstOrCreate(['abbreviation' => 'ml'], ['name' => 'Mètre', 'type' => 'longueur']);
    $wh = Warehouse::create(['company_id' => Company::first()->id, 'code' => 'W1', 'name' => 'W1', 'type' => 'produit_fini', 'is_active' => true]);

    $p = Product::factory()->create([
        'reference' => 'REF-ENR', 'code_article' => 'ART9999999', 'statut' => 'actif',
        'famille1_id' => $f1->id, 'famille2_id' => $f2->id, 'famille3_id' => $f3->id,
        'purchase_unit_id' => $ua->id, 'sale_unit_id' => $uv->id,
        'ua_to_us_coef' => 1000, 'uv_to_us_coef' => 1, 'gross_weight_per_us' => 3.14, 'net_weight_per_us' => 3.10,
        'allow_negative_stock' => true, 'stock_securite' => 50, 'main_warehouse_id' => $wh->id,
    ]);

    $p->refresh();
    expect($p->code_article)->toBe('ART9999999');
    expect($p->famille1->code)->toBe('N1');
    expect($p->famille3->code)->toBe('N3');
    expect($p->purchaseUnit->abbreviation)->toBe('kg');
    expect($p->saleUnit->abbreviation)->toBe('ml');
    expect($p->mainWarehouse->code)->toBe('W1');
    expect($p->allow_negative_stock)->toBeTrue();
    expect((float) $p->stock_securite)->toBe(50.0);
});

it('filters products by statut via scope', function () {
    $this->actingAs(refAdmin());
    Product::factory()->create(['reference' => 'ACT', 'statut' => 'actif']);
    Product::factory()->create(['reference' => 'SOM', 'statut' => 'sommeil']);

    expect(Product::actif()->where('reference', 'ACT')->exists())->toBeTrue();
    expect(Product::actif()->where('reference', 'SOM')->exists())->toBeFalse();
});

it('searches by code_article, statut and famille1 in the repository', function () {
    $this->actingAs(refAdmin());
    $f1 = ProductFamily::create(['name' => 'Tôles', 'code' => 'TOL']);
    Product::factory()->create(['reference' => 'P1', 'code_article' => 'CODEABC01', 'statut' => 'actif', 'famille1_id' => $f1->id]);
    Product::factory()->create(['reference' => 'P2', 'code_article' => 'ZZZ', 'statut' => 'sommeil']);

    $repo = app(ProductRepository::class);
    expect($repo->search(['code_article' => 'CODEABC'])->total())->toBe(1);
    expect($repo->search(['statut' => 'sommeil'])->total())->toBe(1);
    expect($repo->search(['famille1_id' => $f1->id])->total())->toBe(1);
});
