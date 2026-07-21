<?php

/**
 * [X3 P2-1/P2-2] Sous-familles (cohérence famille) + gardes §14-15 restantes :
 * vente non-vendable refusée, changement d'unité de stock bloqué avec mouvements.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\ProductFamily;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\ProductService;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\SubFamilySeeder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function sfSetup(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SF-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SF Co'], ['email' => 'sf@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    (new ItemCategorySeeder())->run();
    (new SubFamilySeeder())->run();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

it('seed sous-familles : 14 rattachées à leurs familles §5', function () {
    sfSetup();
    expect(ProductFamily::whereNotNull('parent_id')->count())->toBeGreaterThanOrEqual(14)
        ->and(ProductFamily::where('code', 'TB_PRELAQ')->first()->parent->code)->toBe('TOLES_BAC');
});

it('accepte une sous-famille appartenant à la famille de l\'article (§11 : Tôles bac / Prélaquées)', function () {
    sfSetup();
    $fam = ProductFamily::where('code', 'TOLES_BAC')->first();
    $sub = ProductFamily::where('code', 'TB_PRELAQ')->first();

    $p = app(ProductService::class)->create([
        'name' => 'Tôle bac prélaquée rouge 0,27',
        'item_category_id' => ItemCategory::where('code', 'PF_TOLE_MTO')->first()->id,
        'family_id' => $fam->id, 'sub_family_id' => $sub->id,
    ]);

    expect($p->subFamily->name)->toBe('Prélaquées');
});

it('refuse une sous-famille d\'une AUTRE famille (§5/§14)', function () {
    sfSetup();
    $tolesBac = ProductFamily::where('code', 'TOLES_BAC')->first();
    $ferHA    = ProductFamily::where('code', 'FB_HA')->first(); // sous-famille de FER_BETON

    expect(fn () => app(ProductService::class)->create([
        'name' => 'Incohérent', 'family_id' => $tolesBac->id, 'sub_family_id' => $ferHA->id,
    ]))->toThrow(\RuntimeException::class);
});

it('refuse une famille parente utilisée comme sous-famille', function () {
    sfSetup();
    $fam = ProductFamily::where('code', 'TOLES_BAC')->first();

    expect(fn () => app(ProductService::class)->create([
        'name' => 'Parent en sous-famille', 'family_id' => $fam->id, 'sub_family_id' => $fam->id,
    ]))->toThrow(\RuntimeException::class);
});

it('refuse la vente d\'un article de catégorie non vendable (REBUT) — §14', function () {
    sfSetup();
    $rebut = app(ProductService::class)->create([
        'name' => 'Rebut invendable',
        'item_category_id' => ItemCategory::where('code', 'REBUT')->first()->id,
    ]);

    expect(fn () => app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [[
            'product_id' => $rebut->id, 'description' => $rebut->name,
            'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]))->toThrow(\RuntimeException::class);
});

it('bloque le changement d\'unité de stock quand des mouvements existent — §15.5', function () {
    $co = sfSetup();
    $p = app(ProductService::class)->create([
        'name' => 'Article mouvementé',
        'item_category_id' => ItemCategory::where('code', 'MARCHANDISE')->first()->id,
        'unit_id' => Unit::firstOrCreate(['name' => 'Pièce SF'], ['abbreviation' => 'pcsf'])->id,
    ]);
    $wh = Warehouse::firstOrCreate(['code' => 'WH-SF'], ['name' => 'Dépôt SF', 'company_id' => $co->id, 'is_active' => true]);
    StockMovement::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'warehouse_id' => $wh->id,
        'type' => 'entree', 'quantity' => 3, 'occurred_at' => now(), 'created_by' => auth()->id(),
    ]);
    $autre = Unit::firstOrCreate(['name' => 'Mètre SF'], ['abbreviation' => 'msf']);

    expect(fn () => app(ProductService::class)->update($p, ['unit_id' => $autre->id]))
        ->toThrow(\RuntimeException::class);
});

it('fiche famille : onglets Général/Sous-familles/Articles/Statistiques rendent', function () {
    sfSetup();
    $fam = ProductFamily::where('code', 'TOLES_BAC')->first();
    app(ProductService::class)->create([
        'name' => 'Tôle fiche famille', 'family_id' => $fam->id,
        'sub_family_id' => ProductFamily::where('code', 'TB_PRELAQ')->first()->id,
        'item_category_id' => ItemCategory::where('code', 'PF_TOLE_MTO')->first()->id,
    ]);

    $this->get(route('product-families.show', $fam))
        ->assertOk()
        ->assertSee('Tôles bac')
        ->assertSee('Prélaquées')
        ->assertSee('Tôle fiche famille')
        ->assertSee('CA facturé HT');
});

it('article-site : priorité article-site > catégorie-site > catégorie globale (§10)', function () {
    $co = sfSetup();
    $site = Warehouse::firstOrCreate(['code' => 'SITE-PS'], ['name' => 'Site PS', 'company_id' => $co->id, 'is_active' => true]);
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $cat->update(['site_declinable' => true, 'default_stock_min' => 100, 'lead_time_days' => 5]);
    $cat->sites()->create(['site_id' => $site->id, 'stock_min' => 300]);

    $p = app(ProductService::class)->create(['name' => 'Fer article-site', 'item_category_id' => $cat->id]);

    // Sans déclinaison article : catégorie-site gagne sur global.
    $params = $p->fresh()->paramsForSite($site->id);
    expect((float) $params['stock_min'])->toBe(300.0)
        ->and((int) $params['lead_time_days'])->toBe(5);

    // Déclinaison article-site : priorité maximale.
    $p->productSites()->create(['site_id' => $site->id, 'stock_min' => 900, 'lead_time_days' => 2]);
    $params = $p->fresh()->load('productSites')->paramsForSite($site->id);
    expect((float) $params['stock_min'])->toBe(900.0)
        ->and((int) $params['lead_time_days'])->toBe(2);
});

it('article-site : endpoints HTTP upsert et suppression', function () {
    $co = sfSetup();
    $site = Warehouse::firstOrCreate(['code' => 'SITE-PS2'], ['name' => 'Site PS2', 'company_id' => $co->id, 'is_active' => true]);
    $p = app(ProductService::class)->create(['name' => 'Article sites HTTP',
        'item_category_id' => ItemCategory::where('code', 'MARCHANDISE')->first()->id]);

    $this->post(route('products.sites.store', $p), ['site_id' => $site->id, 'stock_min' => 50])
        ->assertRedirect();
    expect($p->productSites()->count())->toBe(1);

    // Upsert : même site → mise à jour, pas de doublon.
    $this->post(route('products.sites.store', $p), ['site_id' => $site->id, 'stock_min' => 80])
        ->assertRedirect();
    expect($p->productSites()->count())->toBe(1)
        ->and((float) $p->productSites()->first()->stock_min)->toBe(80.0);

    $this->delete(route('products.sites.destroy', [$p, $p->productSites()->first()]))
        ->assertRedirect();
    expect($p->productSites()->count())->toBe(0);
});

it('attributs dynamiques : obligatoire exigé, select contrôlé, valeurs sauvegardées (§10)', function () {
    sfSetup();
    $cat = ItemCategory::where('code', 'PF_TOLE_MTO')->first();
    $cat->attributes()->create(['code' => 'nuance', 'label' => 'Nuance acier', 'type' => 'select',
        'options' => ['DX51D', 'S250GD'], 'required' => true]);
    $cat->attributes()->create(['code' => 'garantie', 'label' => 'Garantie (ans)', 'type' => 'number', 'required' => false]);

    // Obligatoire manquant → refus.
    expect(fn () => app(ProductService::class)->create([
        'name' => 'Tôle sans nuance', 'item_category_id' => $cat->id,
    ]))->toThrow(\RuntimeException::class);

    // Valeur hors options → refus.
    expect(fn () => app(ProductService::class)->create([
        'name' => 'Tôle nuance inconnue', 'item_category_id' => $cat->id,
        'attributes' => ['nuance' => 'XXX'],
    ]))->toThrow(\RuntimeException::class);

    // Valide → sauvegardé et mis à jour.
    $p = app(ProductService::class)->create([
        'name' => 'Tôle nuancée', 'item_category_id' => $cat->id,
        'attributes' => ['nuance' => 'DX51D', 'garantie' => '10'],
    ]);
    expect($p->attributeValues()->count())->toBe(2)
        ->and($p->attributeValues()->whereHas('attribute', fn ($q) => $q->where('code', 'nuance'))->first()->value)->toBe('DX51D');

    app(ProductService::class)->update($p, ['attributes' => ['nuance' => 'S250GD', 'garantie' => '10']]);
    expect($p->attributeValues()->whereHas('attribute', fn ($q) => $q->where('code', 'nuance'))->first()->value)->toBe('S250GD')
        ->and($p->attributeValues()->count())->toBe(2); // upsert, pas de doublon
});

it('form famille simplifié : création racine + sous-famille via HTTP (classement pur)', function () {
    sfSetup();

    $this->get(route('product-families.create'))
        ->assertOk()
        ->assertSee('classement commercial')
        ->assertSee('Famille parente')
        ->assertDontSee('type_flux')     // champs de gestion retirés
        ->assertDontSee('Compte de stock');

    $this->post(route('product-families.store'), [
        'code' => 'ACCESS_COUV', 'name' => 'Accessoires de couverture', 'is_active' => 1,
    ])->assertRedirect();
    $racine = ProductFamily::where('code', 'ACCESS_COUV')->first();
    expect($racine)->not->toBeNull();

    $this->post(route('product-families.store'), [
        'code' => 'AC_VIS', 'name' => 'Visserie', 'parent_id' => $racine->id, 'is_active' => 1,
    ])->assertRedirect();
    expect(ProductFamily::where('code', 'AC_VIS')->first()->parent_id)->toBe($racine->id);
});
