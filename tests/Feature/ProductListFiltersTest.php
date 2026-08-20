<?php

/**
 * [Articles] Filtres de la liste — un filtre vidé ne doit rien filtrer.
 *
 * `isset()` est vrai pour une chaîne vide. Les selects du formulaire soumettent
 * `family_id=` quand on choisit « Toutes les familles », ce qui produisait
 * `WHERE family_id = ''` : zéro résultat. La liste se vidait au moment précis
 * où l'utilisateur RETIRAIT son filtre — le bug était atteignable en un clic.
 *
 * Les filtres ajoutés plus tard employaient déjà `!empty()`, d'où deux
 * comportements opposés dans la même requête.
 */

use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Repositories\ProductRepository;

uses(\Tests\Concerns\RefreshDatabase::class);

function listeArticles(array $filtres = []): int
{
    return app(ProductRepository::class)->search($filtres, 100)->total();
}

beforeEach(function () {
    Company::firstOrCreate(['name' => 'FLT'], ['email' => 'flt@flt.io']);
    // Ni ProductFamily ni Brand n'ont de factory : création directe.
    $this->famille = ProductFamily::create(['name' => 'Famille filtre', 'code' => 'FLT-FAM', 'is_active' => true]);
    $this->marque = Brand::create(['name' => 'Marque filtre', 'is_active' => true]);

    Product::factory()->count(3)->create(['is_active' => true, 'family_id' => $this->famille->id]);
    Product::factory()->count(2)->create(['is_active' => true]);
});

it('ne filtre rien quand le filtre est vidé', function (string $champ) {
    $total = listeArticles();
    expect($total)->toBe(5);

    // Le cas qui cassait : la valeur soumise est une chaîne vide, pas une absence.
    expect(listeArticles([$champ => '']))->toBe($total);
})->with(['search', 'family_id', 'brand_id', 'item_category_id', 'low_stock']);

it('filtre réellement quand le filtre est renseigné', function () {
    expect(listeArticles(['family_id' => $this->famille->id]))->toBe(3);
});

it('trouve un article par son code article comme par son nom', function () {
    $p = Product::factory()->create(['is_active' => true, 'code_article' => 'ZZZ-UNIQUE-42', 'name' => 'Libellé distinctif']);

    expect(listeArticles(['search' => 'ZZZ-UNIQUE-42']))->toBe(1);
    expect(listeArticles(['search' => 'distinctif']))->toBe(1);
    expect(app(ProductRepository::class)->search(['search' => 'ZZZ-UNIQUE-42'], 100)->first()->id)->toBe($p->id);
});

it('remonte en tension un article sous son minimum sans aucune ligne de stock', function () {
    // Le filtre passait par whereHas('productStocks') : un article dépourvu de
    // ligne était invisible, alors qu'un stock nul est la tension maximale.
    $p = Product::factory()->create([
        'is_active' => true, 'is_stockable' => true,
        'stock_min' => 100, 'reorder_point' => 150,
    ]);
    expect(ProductStock::where('product_id', $p->id)->count())->toBe(0);

    $ids = app(ProductRepository::class)->search(['low_stock' => 1], 100)->pluck('id');
    expect($ids)->toContain($p->id);
});

it('exclut de la tension un article au-dessus de son minimum', function () {
    $co = Company::firstOrCreate(['name' => 'FLT'], ['email' => 'flt@flt.io']);
    $w = Warehouse::firstOrCreate(['code' => 'FLT-W'], [
        'name' => 'FLT-W', 'company_id' => $co->id, 'type' => 'matiere_premiere',
        'is_active' => true, 'is_default' => false,
    ]);
    $p = Product::factory()->create(['is_active' => true, 'is_stockable' => true, 'stock_min' => 100]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'quantity' => 500, 'reserved_quantity' => 0]);

    expect(app(ProductRepository::class)->search(['low_stock' => 1], 100)->pluck('id'))->not->toContain($p->id);
});

it('donne le même nombre que le service de stock', function () {
    $p = Product::factory()->create([
        'is_active' => true, 'is_stockable' => true, 'stock_min' => 100,
    ]);

    $svc = app(\App\Services\StockInsightsService::class);

    // La liste des articles et les écrans stock répondent à la même question :
    // ils doivent le faire avec la même définition, pas deux requêtes jumelles.
    expect(listeArticles(['low_stock' => 1]))->toBe($svc->compter($svc->enTensionQuery()));
    expect(listeArticles(['low_stock' => 1]))->toBeGreaterThan(0);
});
