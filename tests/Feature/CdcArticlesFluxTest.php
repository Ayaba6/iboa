<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Rules\ProductFlux;
use App\Rules\WarehouseAllows;
use Illuminate\Support\Facades\Validator;

/**
 * CDC « Règles de gestion des articles » — type de flux A/V/F et
 * propriétés des dépôts (Production / Ventes / Achat / Stock).
 */

function makeFluxProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'is_purchasable'    => false,
        'is_sellable'       => false,
        'is_manufacturable' => false,
    ], $attrs));
}

it('refuse un article non vendable dans le flux vente', function () {
    $p = makeFluxProduct(['is_purchasable' => true]);

    $v = Validator::make(['product_id' => $p->id], ['product_id' => [new ProductFlux('vendu')]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('product_id'))->toContain('vendable');
});

it('accepte un article vendable dans le flux vente', function () {
    $p = makeFluxProduct(['is_sellable' => true]);

    $v = Validator::make(['product_id' => $p->id], ['product_id' => [new ProductFlux('vendu')]]);
    expect($v->passes())->toBeTrue();
});

it('refuse un article non achetable dans le flux achat', function () {
    $p = makeFluxProduct(['is_sellable' => true]);

    $v = Validator::make(['product_id' => $p->id], ['product_id' => [new ProductFlux('achete')]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('product_id'))->toContain('achetable');
});

it('refuse un article non fabricable dans le flux fabrication', function () {
    $p = makeFluxProduct(['is_sellable' => true, 'is_purchasable' => true]);

    $v = Validator::make(['product_id' => $p->id], ['product_id' => [new ProductFlux('fabrique')]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('product_id'))->toContain('fabricable');
});

it('accepte un article fabricable dans le flux fabrication', function () {
    $p = makeFluxProduct(['is_manufacturable' => true]);

    $v = Validator::make(['product_id' => $p->id], ['product_id' => [new ProductFlux('fabrique')]]);
    expect($v->passes())->toBeTrue();
});

it('refuse une vente depuis un dépôt sans propriété Ventes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $w = Warehouse::create([
        'company_id' => $user->company_id ?? \App\Models\Company::factory()->create()->id,
        'name'       => 'Dépôt production seul',
        'code'       => 'DEP-NOSALE',
        'can_production' => true,
        'can_sale'       => false,
        'can_purchase'   => true,
        'can_stock'      => true,
        'is_active'      => true,
    ]);

    $v = Validator::make(['warehouse_id' => $w->id], ['warehouse_id' => [new WarehouseAllows('can_sale')]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('warehouse_id'))->toContain('vente');

    $ok = Validator::make(['warehouse_id' => $w->id], ['warehouse_id' => [new WarehouseAllows('can_production')]]);
    expect($ok->passes())->toBeTrue();
});
