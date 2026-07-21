<?php

/**
 * [X3 §7/§11-13] Héritage catégorie → article à la création :
 * la catégorie pose les défauts ; surcharge limitée à overridable_fields ;
 * jamais rétroactif sur les articles existants.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Database\Seeders\ItemCategorySeeder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function icCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'IC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'IC Co'], ['email' => 'ic@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    (new ItemCategorySeeder())->run();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

it('le seeder crée les 10 catégories, idempotent', function () {
    icCompany();
    expect(ItemCategory::count())->toBe(10);
    (new ItemCategorySeeder())->run();
    expect(ItemCategory::count())->toBe(10);
});

it('PF_TOLE_MTO : l\'article hérite fabriqué + MTO + vendu + stocké + CQ (§11)', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'PF_TOLE_MTO')->first();

    $p = app(ProductService::class)->create([
        'name' => 'Tôle bac prélaquée rouge 0,27', 'item_category_id' => $cat->id,
    ]);

    expect($p->is_manufacturable)->toBeTrue()
        ->and($p->production_mode)->toBe('mto')
        ->and($p->is_sellable)->toBeTrue()
        ->and($p->is_stockable)->toBeTrue()
        ->and($p->controle_qualite)->toBeTrue()
        ->and($p->type_article)->toBe('produit_fini');
});

it('MARCHANDISE : acheté + vendu + stocké, PAS fabriqué (§13)', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'MARCHANDISE')->first();

    $p = app(ProductService::class)->create([
        'name' => 'Vis de fixation pour toiture', 'item_category_id' => $cat->id,
    ]);

    expect($p->is_purchasable)->toBeTrue()
        ->and($p->is_sellable)->toBeTrue()
        ->and($p->is_stockable)->toBeTrue()
        ->and($p->is_manufacturable)->toBeFalse()
        ->and($p->production_mode)->toBeNull()
        ->and($p->type_article)->toBe('marchandise');
});

it('SERVICE_VENTE : non stocké, type service', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'SERVICE_VENTE')->first();

    $p = app(ProductService::class)->create([
        'name' => 'Prestation de pose', 'item_category_id' => $cat->id,
    ]);

    expect($p->is_stockable)->toBeFalse()
        ->and($p->type)->toBe('service')
        ->and($p->is_sellable)->toBeTrue();
});

it('surcharge REFUSÉE hors liste blanche : la catégorie impose sa valeur', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'MARCHANDISE')->first();
    $cat->update(['overridable_fields' => ['sale_price']]); // seul le prix est surchargeable

    $p = app(ProductService::class)->create([
        'name' => 'Marchandise têtue', 'item_category_id' => $cat->id,
        'is_manufacturable' => true, 'production_mode' => 'mto', // tentative interdite
        'sale_price' => 12345,
    ]);

    expect($p->is_manufacturable)->toBeFalse()      // imposé par la catégorie
        ->and($p->production_mode)->toBeNull()
        ->and((int) $p->sale_price)->toBe(12345);   // surcharge autorisée
});

it('surcharge AUTORISÉE quand le champ est en liste blanche', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $cat->update(['overridable_fields' => ['production_mode']]);

    $p = app(ProductService::class)->create([
        'name' => 'Fer spécial', 'item_category_id' => $cat->id, 'production_mode' => 'mto',
    ]);

    expect($p->production_mode)->toBe('mto');
});

it('n\'est jamais rétroactif : modifier la catégorie ne touche pas les articles existants', function () {
    icCompany();
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $p = app(ProductService::class)->create(['name' => 'Fer HA 10', 'item_category_id' => $cat->id]);
    expect($p->production_mode)->toBe('mts');

    $cat->update(['strategy' => 'mto']);
    expect($p->fresh()->production_mode)->toBe('mts'); // inchangé
});

it('priorité SITE sur global : dépôts et seuils du site appliqués (§9)', function () {
    $co = icCompany();
    $site = \App\Models\Warehouse::firstOrCreate(['code' => 'SITE-IC'], ['name' => 'Site IC', 'company_id' => $co->id, 'is_active' => true]);
    $depotSite = \App\Models\Warehouse::firstOrCreate(['code' => 'PF-IC'], ['name' => 'Dépôt PF IC', 'company_id' => $co->id, 'is_active' => true]);
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $cat->update(['site_declinable' => true, 'default_stock_min' => 100]);
    $cat->sites()->create(['site_id' => $site->id, 'pf_warehouse_id' => $depotSite->id, 'stock_min' => 500]);

    $p = app(ProductService::class)->create([
        'name' => 'Fer site', 'item_category_id' => $cat->id, 'site_id' => $site->id,
    ]);

    expect((float) $p->stock_min)->toBe(500.0)                 // site prioritaire
        ->and($p->sale_warehouse_id)->toBe($depotSite->id);

    $p2 = app(ProductService::class)->create(['name' => 'Fer global', 'item_category_id' => $cat->id]);
    expect((float) $p2->stock_min)->toBe(100.0);               // global
});
