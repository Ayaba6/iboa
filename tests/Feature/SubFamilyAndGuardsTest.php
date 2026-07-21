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
