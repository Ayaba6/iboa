<?php

/**
 * [PRO-01] Substitution de composant dans la nomenclature.
 */

use App\Models\Company;
use App\Models\Product;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;

uses(\Tests\Concerns\RefreshDatabase::class);

it('enregistre un composant substituable et résout la relation', function () {
    $co = Company::firstOrCreate(['name' => 'BOM Co'], ['email' => 'bom@iboa.test']);
    $pf = Product::factory()->create(['name' => 'Tôle bac PF']);
    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'code' => 'NOM-1', 'name' => 'Nomenclature test',
        'quantite_base' => 1, 'statut' => 'exploitation', 'is_active' => true,
    ]);
    $main = Product::factory()->create(['name' => 'Bobine 0,30']);
    $sub  = Product::factory()->create(['name' => 'Bobine 0,32 (substitut)']);

    $line = $bom->lines()->create([
        'product_id' => $main->id, 'substitute_product_id' => $sub->id,
        'substitute_note' => 'Autorisé si rupture', 'quantity_per_meter' => 1.2, 'sort_order' => 1,
    ]);

    $fresh = BomLine::with(['product', 'substitute'])->find($line->id);
    expect($fresh->substitute_product_id)->toBe($sub->id);
    expect($fresh->substitute->name)->toBe('Bobine 0,32 (substitut)');
    expect($fresh->substitute_note)->toBe('Autorisé si rupture');
    // le composant principal reste distinct
    expect($fresh->product->name)->toBe('Bobine 0,30');
});
