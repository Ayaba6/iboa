<?php

/**
 * [Ventes] Le stock disponible du formulaire devis se calcule à la création
 * comme à la modification.
 *
 * Le formulaire est partagé entre `create` et `edit`. Il affiche « Stock
 * disponible » = réel − réservé, et le passe en rouge quand il devient négatif :
 * c'est l'alerte qui dit au vendeur qu'il engage du stock déjà promis ailleurs.
 *
 * `QuoteController::create()` fournissait `reserved_qty` ; `edit()` ne le
 * fournissait pas. En JavaScript, une propriété absente donne
 * `parseFloat(undefined) || 0` : à la modification, le disponible valait donc le
 * stock réel et l'alerte ne se déclenchait jamais. Sans erreur, sans trace.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function devisContexte(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DEV'], ['email' => 'dev@dev.io', 'current_fiscal_year_id' => $fy->id]);
    $w = Warehouse::firstOrCreate(['code' => 'WD'], ['name' => 'WD', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $produit = Product::factory()->create(['is_active' => true, 'is_sellable' => true, 'sale_price' => 1000]);
    // 100 en stock dont 80 déjà réservés : le disponible réel est 20.
    ProductStock::create([
        'product_id' => $produit->id, 'warehouse_id' => $w->id,
        'quantity' => 100, 'reserved_quantity' => 80,
    ]);

    $client = Client::factory()->create();

    return ['user' => $u, 'produit' => $produit, 'client' => $client, 'company' => $co];
}

it('expose la quantité réservée à la création d\'un devis', function () {
    ['user' => $u, 'produit' => $p] = devisContexte();

    $produits = $this->actingAs($u)->get(route('ventes.devis.create'))->assertOk()->viewData('products');
    $ligne = $produits->firstWhere('id', $p->id);

    expect((float) $ligne->stock_qty)->toBe(100.0);
    expect((float) $ligne->reserved_qty)->toBe(80.0);
});

it('expose la quantité réservée à la MODIFICATION d\'un devis', function () {
    ['user' => $u, 'produit' => $p, 'client' => $c, 'company' => $co] = devisContexte();

    $devis = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $c->id,
        'number' => 'DV-STOCK-1', 'status' => 'brouillon', 'issued_at' => now(),
    ]);

    $produits = $this->actingAs($u)->get(route('ventes.devis.edit', $devis))->assertOk()->viewData('products');
    $ligne = $produits->firstWhere('id', $p->id);

    // Le champ manquait ICI : le formulaire lisait `undefined`, le ramenait à 0,
    // et affichait 100 disponibles au lieu de 20.
    expect($ligne->reserved_qty)->not->toBeNull();
    expect((float) $ligne->reserved_qty)->toBe(80.0);
    expect((float) $ligne->stock_qty - (float) $ligne->reserved_qty)->toBe(20.0);
});

it('donne le même disponible sur les deux écrans', function () {
    ['user' => $u, 'produit' => $p, 'client' => $c, 'company' => $co] = devisContexte();

    $devis = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $c->id,
        'number' => 'DV-STOCK-2', 'status' => 'brouillon', 'issued_at' => now(),
    ]);

    $creation = $this->actingAs($u)->get(route('ventes.devis.create'))->viewData('products')->firstWhere('id', $p->id);
    $edition  = $this->actingAs($u)->get(route('ventes.devis.edit', $devis))->viewData('products')->firstWhere('id', $p->id);

    // L'égalité est la propriété testée, pas la valeur : un même article ne peut
    // pas être plus disponible selon la porte par laquelle on entre.
    expect((float) $edition->stock_qty - (float) $edition->reserved_qty)
        ->toBe((float) $creation->stock_qty - (float) $creation->reserved_qty);
});
