<?php

/**
 * [Stock] Indicateurs de disponibilité — définition unique, partagée.
 *
 * Quatre défauts corrigés, un test chacun :
 *
 *  1. Les compteurs partaient de `product_stocks` en INNER JOIN. Un article sans
 *     ligne de stock était invisible — donc l'article à zéro absolu, celui qu'un
 *     écran de réapprovisionnement existe pour montrer, n'apparaissait nulle part.
 *  2. `/stocks` comptait des LIGNES, `/stocks/dashboard` des ARTICLES : un même
 *     article sur trois dépôts donnait 3 d'un côté, 1 de l'autre.
 *  3. `/stocks` employait `dispo <= stock_min`, le tableau de bord
 *     `dispo < stock_min AND dispo > 0` : un article à zéro se comptait deux fois
 *     sur le premier écran, en rupture ET sous le minimum.
 *  4. Une ligne vide en quarantaine était comptée comme rupture, alors que le vide
 *     est l'état sain d'un dépôt de tri.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\StockInsightsService;

uses(\Tests\Concerns\RefreshDatabase::class);

function indicSvc(): StockInsightsService
{
    return app(StockInsightsService::class);
}

function indicCompany(): Company
{
    return Company::firstOrCreate(['name' => 'STK'], ['email' => 'stk@stk.io']);
}

function indicWarehouse(string $code, string $type = 'matiere_premiere'): Warehouse
{
    return Warehouse::firstOrCreate(['code' => $code], [
        'name' => $code, 'company_id' => indicCompany()->id,
        'type' => $type, 'is_active' => true, 'is_default' => false,
    ]);
}

function indicProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'is_active' => true, 'is_stockable' => true,
        'stock_min' => 0, 'reorder_point' => 0,
    ], $attrs));
}

it('compte en rupture un article dépourvu de toute ligne de stock', function () {
    indicCompany();
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);

    // Aucune ProductStock créée : c'est tout le point. L'ancien INNER JOIN
    // rendait cet article — le plus urgent — strictement invisible.
    expect(ProductStock::where('product_id', $p->id)->count())->toBe(0);

    $codes = indicSvc()->ruptureQuery()->get()->pluck('id');
    expect($codes)->toContain($p->id);
});

it('propose au réapprovisionnement un article sans ligne de stock', function () {
    indicCompany();
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150, 'stock_max' => 500]);

    $alerte = indicSvc()->restockAlertsQuery()->get()->firstWhere('id', $p->id);

    expect($alerte)->not->toBeNull();
    expect((float) $alerte->available_qty)->toBe(0.0);
    // Suggestion = combler jusqu'au maximum, pas seulement franchir le seuil.
    expect((float) $alerte->suggested_qty)->toBe(500.0);
});

it('compte un article présent sur deux dépôts une seule fois', function () {
    indicCompany();
    $a = indicWarehouse('STK-A');
    $b = indicWarehouse('STK-B');
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);

    foreach ([$a, $b] as $w) {
        ProductStock::create([
            'product_id' => $p->id, 'warehouse_id' => $w->id,
            'quantity' => 0, 'reserved_quantity' => 0,
        ]);
    }

    // Deux lignes, un seul article en rupture : on ne commande pas deux fois.
    expect(indicSvc()->ruptureQuery()->get()->where('id', $p->id)->count())->toBe(1);
});

it('additionne les dépôts avant de juger la disponibilité', function () {
    indicCompany();
    $a = indicWarehouse('STK-A');
    $b = indicWarehouse('STK-B');
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);

    // 60 + 60 = 120 : au-dessus du minimum. Ligne à ligne, chacune paraîtrait
    // en dessous — c'est le total détenu qui décide, pas chaque étagère.
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $a->id, 'quantity' => 60, 'reserved_quantity' => 0]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $b->id, 'quantity' => 60, 'reserved_quantity' => 0]);

    expect(indicSvc()->sousMinimumQuery()->get()->pluck('id'))->not->toContain($p->id);
    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->not->toContain($p->id);
});

it('ignore les dépôts de quarantaine, rebuts et chutes', function (string $type) {
    indicCompany();
    $tri = indicWarehouse('STK-'.$type, $type);
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);

    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $tri->id, 'quantity' => 500, 'reserved_quantity' => 0]);

    // 500 en quarantaine ne sauve pas d'une rupture : cette quantité n'est pas
    // disponible. L'article reste en rupture malgré un stock physique.
    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->toContain($p->id);
})->with(['quarantaine', 'rebuts', 'chutes']);

it('n\'oppose pas rupture et sous-minimum sur le même article', function () {
    indicCompany();
    $w = indicWarehouse('STK-A');
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'quantity' => 0, 'reserved_quantity' => 0]);

    // Un article à zéro est EN RUPTURE, et rien d'autre. Le compter aussi sous
    // le minimum le ferait figurer deux fois dans un total censé partitionner.
    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->toContain($p->id);
    expect(indicSvc()->sousMinimumQuery()->get()->pluck('id'))->not->toContain($p->id);
});

it('distingue le sous-minimum de la rupture', function () {
    indicCompany();
    $w = indicWarehouse('STK-A');
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'quantity' => 40, 'reserved_quantity' => 0]);

    expect(indicSvc()->sousMinimumQuery()->get()->pluck('id'))->toContain($p->id);
    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->not->toContain($p->id);
});

it('ne déclare pas en rupture un article sans politique de stock', function () {
    indicCompany();
    $w = indicWarehouse('STK-A');
    // Sous-produit type : alimenté par les déclarations de production, à zéro
    // par nature. Son vide n'est pas une alerte, c'est son état normal.
    $p = indicProduct(['stock_min' => 0, 'reorder_point' => 0]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'quantity' => 0, 'reserved_quantity' => 0]);

    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->not->toContain($p->id);
    expect(indicSvc()->sansPolitiqueStockQuery()->get()->pluck('id'))->toContain($p->id);
});

it('déduit les quantités réservées de la disponibilité', function () {
    indicCompany();
    $w = indicWarehouse('STK-A');
    $p = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);
    // 120 détenus dont 120 réservés : rien n'est disponible pour un nouveau besoin.
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'quantity' => 120, 'reserved_quantity' => 120]);

    expect(indicSvc()->ruptureQuery()->get()->pluck('id'))->toContain($p->id);
});

it('fait dire la même chose à la liste des stocks et au tableau de bord', function () {
    indicCompany();
    $w = indicWarehouse('STK-A');
    $q = indicWarehouse('STK-QUAR', 'quarantaine');

    indicProduct(['stock_min' => 100, 'reorder_point' => 150]);                       // sans ligne
    $b = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);                  // sous mini
    ProductStock::create(['product_id' => $b->id, 'warehouse_id' => $w->id, 'quantity' => 40, 'reserved_quantity' => 0]);
    $c = indicProduct(['stock_min' => 100, 'reorder_point' => 150]);                  // quarantaine seule
    ProductStock::create(['product_id' => $c->id, 'warehouse_id' => $q->id, 'quantity' => 999, 'reserved_quantity' => 0]);

    $svc = indicSvc();
    $kpis = $svc->dashboardKpis();

    // Les deux écrans consomment désormais les mêmes méthodes : l'égalité n'est
    // plus une coïncidence de données, c'est une propriété du code.
    expect($kpis['rupture_count'])->toBe($svc->compter($svc->ruptureQuery()));
    expect($kpis['below_min_count'])->toBe($svc->compter($svc->sousMinimumQuery()));
    expect($kpis['rupture_count'])->toBe(2);   // l'article sans ligne + celui bloqué en quarantaine
    expect($kpis['below_min_count'])->toBe(1); // celui à 40
});
