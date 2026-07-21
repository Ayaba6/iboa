<?php

/**
 * [X3 §14-15] Gardes serveur liées à la catégorie :
 * OF interdit pour une catégorie non fabriquée ; changement de catégorie
 * bloqué si mouvements ; stocké→non stocké bloqué si stock physique.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\ProductionService;
use App\Services\ProductService;
use Database\Seeders\ItemCategorySeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function gdCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'GD-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'GD Co'], ['email' => 'gd@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    (new ItemCategorySeeder())->run();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

it('refuse un OF pour un article de catégorie MARCHANDISE (non fabriquée)', function () {
    gdCompany();
    $cat = ItemCategory::where('code', 'MARCHANDISE')->first();
    $vis = app(ProductService::class)->create(['name' => 'Vis toiture', 'item_category_id' => $cat->id]);

    expect(fn () => app(ProductionService::class)->create([
        'product_id' => $vis->id, 'quantity_requested' => 100,
    ]))->toThrow(ValidationException::class);
});

it('autorise un OF pour une catégorie fabriquée (PF_FER_MTS)', function () {
    gdCompany();
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $fer = app(ProductService::class)->create(['name' => 'Fer HA 10', 'item_category_id' => $cat->id]);

    $of = app(ProductionService::class)->create([
        'product_id' => $fer->id, 'quantity_requested' => 100,
    ]);
    expect($of->id)->toBeGreaterThan(0);
});

it('bloque le changement de catégorie quand l\'article a des mouvements de stock', function () {
    $co = gdCompany();
    $mts = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $marchandise = ItemCategory::where('code', 'MARCHANDISE')->first();
    $p = app(ProductService::class)->create(['name' => 'Fer mouvementé', 'item_category_id' => $mts->id]);
    $wh = Warehouse::firstOrCreate(['code' => 'WH-GD'], ['name' => 'Dépôt GD', 'company_id' => $co->id, 'is_active' => true]);
    StockMovement::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'warehouse_id' => $wh->id,
        'type' => 'entree', 'quantity' => 10, 'occurred_at' => now(), 'created_by' => auth()->id(),
    ]);

    expect(fn () => app(ProductService::class)->update($p, ['item_category_id' => $marchandise->id]))
        ->toThrow(\RuntimeException::class);

    expect($p->fresh()->item_category_id)->toBe($mts->id);
});

it('autorise le changement de catégorie sans mouvement', function () {
    gdCompany();
    $mts = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $sous = ItemCategory::where('code', 'SOUS_PRODUIT')->first();
    $p = app(ProductService::class)->create(['name' => 'Fer neuf', 'item_category_id' => $mts->id]);

    app(ProductService::class)->update($p, ['item_category_id' => $sous->id]);
    expect($p->fresh()->item_category_id)->toBe($sous->id);
});

it('bloque le passage stocké → non stocké quand un stock physique existe', function () {
    $co = gdCompany();
    $cat = ItemCategory::where('code', 'MARCHANDISE')->first();
    $p = app(ProductService::class)->create(['name' => 'Article stocké', 'item_category_id' => $cat->id]);
    $wh = Warehouse::firstOrCreate(['code' => 'WH-GD2'], ['name' => 'Dépôt GD2', 'company_id' => $co->id, 'is_active' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0]);

    expect(fn () => app(ProductService::class)->update($p, ['is_stockable' => false]))
        ->toThrow(\RuntimeException::class);
});
