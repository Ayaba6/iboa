<?php

/**
 * [MTS §1-§2] Écran de planification « production pour stock ».
 *
 * L'écran affichait quatre articles en « Rupture », un besoin net de 0 partout,
 * et aucun bouton de création d'OF. Trois causes distinctes :
 *
 *   1. `cible = $p->stock_max ?: $p->stock_min ?: 0` — une colonne decimal
 *      remonte la chaîne « 0.00 », VRAIE en PHP. La cible valait donc « 0.00 »,
 *      soit 0 après conversion : besoin nul quoi qu'il arrive.
 *   2. Le même piège rendait « 0 » dans la colonne Min et « — » dans Cible, pour
 *      le même zéro sous-jacent.
 *   3. La formule ignorait les réceptions attendues et la demande client, et ne
 *      lisait jamais `reorder_point` — pourtant en base et exigé par le cahier.
 *
 * Un article sans aucun seuil n'est plus déclaré « en rupture » : il n'est pas
 * piloté. Le distinguer donne la bonne action — compléter la fiche article — au
 * lieu d'accuser un stock qui n'a jamais eu de cible.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function mtsUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTS-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'MTS Co'], [
        'email' => 'mts@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WMTS'], [
        'name' => 'WMTS', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'mts_planificateur', 'guard_name' => 'web']);
    foreach (['production.view', 'production.create'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/** Article MTS, avec le stock physique voulu. */
function mtsProduct(array $seuils = [], float $stock = 0): Product
{
    $p = Product::factory()->create(array_merge([
        'production_mode' => 'mts', 'is_stockable' => true, 'is_active' => true,
    ], $seuils));

    ProductStock::create([
        'product_id' => $p->id,
        'warehouse_id' => Warehouse::where('code', 'WMTS')->value('id'),
        'quantity' => $stock, 'reserved_quantity' => 0, 'avg_cost' => 500,
    ]);

    return $p;
}

/** Commande fournisseur engagée : $recu déjà réceptionné sur $commande. */
function mtsPurchaseOrder(Product $p, float $commande, float $recu = 0, string $status = 'confirme'): void
{
    $co = Company::first();
    $poId = DB::table('purchase_orders')->insertGetId([
        'company_id' => $co->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'CF-MTS-'.uniqid(), 'status' => $status,
        'ordered_at' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $poId, 'product_id' => $p->id,
        'description' => $p->name, 'quantity' => $commande, 'received_quantity' => $recu,
        'unit_price' => 1000, 'line_total_ht' => 1000 * $commande, 'line_tax' => 0,
        'line_total_ttc' => 1000 * $commande,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Commande client ferme : $livre déjà livré sur $commande. */
function mtsSalesOrder(Product $p, float $commande, float $livre = 0, string $status = 'confirme'): void
{
    $co = Company::first();
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-MTS-'.uniqid(), 'status' => $status, 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name,
        'quantity' => $commande, 'delivered_quantity' => $livre,
        'unit_price' => 2000, 'line_total_ht' => 2000 * $commande, 'line_tax' => 0,
        'line_total_ttc' => 2000 * $commande,
    ]);
}

/** @return array<string,mixed> la ligne calculée pour cet article */
function mtsRow(Product $p): array
{
    $rows = test()->get(route('production.orders.mts'))->assertOk()
        ->original->getData()['rows'];

    return collect($rows)->firstWhere(fn ($r) => $r['p']->id === $p->id);
}

// ── 1. Article non paramétré ─────────────────────────────────────────────────

it('ne déclare pas « en rupture » un article sans aucun seuil', function () {
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);

    expect(mtsRow($p)['etat'])->toBe('non_parametre');
});

it('affiche « Seuil non défini » et renvoie vers la fiche article', function () {
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);

    $html = $this->get(route('production.orders.mts'))->assertOk()->getContent();

    expect($html)->toContain('Seuil non défini')
        ->and($html)->toContain(route('products.edit', $p));
});

it('affiche « — » et non « 0 » pour un seuil et une cible absents', function () {
    // Le piège d'origine : « 0.00 » est une chaîne VRAIE en PHP. La colonne Min
    // rendait donc « 0 » quand la colonne Cible rendait « — », pour le même zéro.
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);
    $row = mtsRow($p);

    expect($row['seuil'])->toBe(0.0)
        ->and($row['cible'])->toBe(0.0)
        ->and($row['parametre'])->toBeFalse();
});

// ── 2. Résolution des seuils ─────────────────────────────────────────────────

it('retient le stock maximum comme cible quand il est défini', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'reorder_point' => 200, 'stock_min' => 100]);

    expect(mtsRow($p)['cible'])->toBe(500.0);
});

it('fait du point de commande le seuil de déclenchement, devant le minimum', function () {
    // `reorder_point` existait en base et n'était jamais lu : l'état se calculait
    // sur `stock_min`, ignorant le seuil que le cahier désigne comme déclencheur.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'reorder_point' => 200, 'stock_min' => 100], stock: 150);
    $row = mtsRow($p);

    expect($row['seuil'])->toBe(200.0)
        ->and($row['etat'])->toBe('sous_min'); // 150 < 200
});

it('se rabat sur le point de commande puis le minimum à défaut de maximum', function () {
    mtsUser();
    $surReorder = mtsProduct(['stock_max' => null, 'reorder_point' => 300, 'stock_min' => 100]);
    $surMin     = mtsProduct(['stock_max' => null, 'reorder_point' => 0, 'stock_min' => 120]);

    expect(mtsRow($surReorder)['cible'])->toBe(300.0)
        ->and(mtsRow($surMin)['cible'])->toBe(120.0);
});

// ── 3. Formule du besoin net ─────────────────────────────────────────────────

it('déduit les réceptions fournisseurs attendues du besoin', function () {
    // Cible 500, stock 0 → besoin brut 500. Une commande fournisseur de 200 en
    // cours le ramène à 300 : on ne fabrique pas ce qu'on a déjà acheté.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200);

    $row = mtsRow($p);
    expect($row['recu'])->toBe(200.0)
        ->and($row['besoin'])->toBe(300.0);
});

it('ne déduit pas une commande fournisseur déjà entièrement reçue', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, recu: 200);

    expect(mtsRow($p)['recu'])->toBe(0.0);
});

it('ne déduit pas une commande fournisseur annulée ou encore en brouillon', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, status: 'annule');
    mtsPurchaseOrder($p, commande: 150, status: 'brouillon');

    expect(mtsRow($p)['recu'])->toBe(0.0);
});

it('compte une commande fournisseur « partiellement_recu » au masculin', function () {
    // L'énumération réelle est au MASCULIN (`envoye`, `confirme`,
    // `partiellement_recu`). Écrits au féminin, ces statuts ne correspondent à
    // aucune ligne et le terme vaudrait silencieusement zéro — c'est le défaut
    // constaté dans PurchaseInsightsService.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, recu: 50, status: 'partiellement_recu');

    expect(mtsRow($p)['recu'])->toBe(150.0);
});

it('ajoute la demande client ferme non encore livrée', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsSalesOrder($p, commande: 80);

    $row = mtsRow($p);
    expect($row['client'])->toBe(80.0)
        ->and($row['besoin'])->toBe(580.0);
});

it('ne compte pas la part déjà livrée d’une commande client', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsSalesOrder($p, commande: 80, livre: 30);

    expect(mtsRow($p)['client'])->toBe(50.0);
});

it('ajoute le stock de sécurité à la cible', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_securite' => 50, 'stock_min' => 0, 'reorder_point' => 0]);

    expect(mtsRow($p)['besoin'])->toBe(550.0);
});

it('combine tous les termes de la formule', function () {
    // 400 cible + 50 sécurité + 100 demande − 120 dispo − 30 attendu = 400
    mtsUser();
    $p = mtsProduct(['stock_max' => 400, 'stock_securite' => 50, 'stock_min' => 0, 'reorder_point' => 0], stock: 120);
    mtsSalesOrder($p, commande: 100);
    mtsPurchaseOrder($p, commande: 30);

    expect(mtsRow($p)['besoin'])->toBe(400.0);
});

it('ne descend jamais sous zéro quand l’approvisionnement couvre la cible', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 100, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0], stock: 500);

    expect(mtsRow($p)['besoin'])->toBe(0.0);
});

// ── 4. Action proposée ───────────────────────────────────────────────────────

it('propose la création d’un OF dès qu’un besoin est calculé', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);

    $html = $this->get(route('production.orders.mts'))->assertOk()->getContent();

    expect(mtsRow($p)['besoin'])->toBe(500.0)
        ->and($html)->toContain('Créer OF MTS')
        // Blade échappe les « & » de l'URL en « &amp; » : on compare donc sur les
        // paramètres, pas sur l'URL brute rendue par route().
        ->and($html)->toContain('product_id='.$p->id)
        ->and($html)->toContain('qty=500');
});

it('ne propose rien quand le stock couvre déjà la cible', function () {
    mtsUser();
    mtsProduct(['stock_max' => 100, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0], stock: 500);

    expect($this->get(route('production.orders.mts'))->assertOk()->getContent())
        ->not->toContain('Créer OF MTS');
});
