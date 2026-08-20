<?php

/**
 * [Ventes] Unité de vente et coût de revient sur les lignes de devis et de commande.
 *
 * Deux défauts corrigés.
 *
 * 1. AUCUNE ligne ne portait d'unité. `quote_items.unit_id` et `order_items.unit_id`
 *    existaient, les services les lisaient, mais les formulaires ne les postaient
 *    jamais : 4 lignes de devis sur 4 et 5 lignes de commande sur 5 avaient
 *    `unit_id` NULL. En métallurgie c'est structurel — une tôle bac se vend à la
 *    pièce ou au mètre linéaire, un fer à béton au kilo ou à la tonne.
 *
 * 2. `unit_cost` n'existait que sur `invoice_items` : le coût n'apparaissait qu'à
 *    la facturation, donc APRÈS la décision commerciale. La section « Marge /
 *    Cumul » de l'écran de devis n'affichait, faute de coût, que du stock.
 *
 * Le champ reste `nullable` en validation et l'unité est dérivée côté serveur :
 * 28 fichiers de test et les intégrations existantes postent des lignes sans
 * unité. Exiger le champ aurait déplacé le travail vers la réparation
 * d'appelants au lieu de corriger la donnée.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\QuoteService;
use App\Services\Sales\SalesLineDefaultsService;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function unitMarginFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'UNITMARGIN-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'UnitMargin Co'], [
        'email' => 'unitmargin@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $kg    = Unit::firstOrCreate(['name' => 'Kilogramme UM'], ['abbreviation' => 'kgum']);
    $barre = Unit::firstOrCreate(['name' => 'Barre UM'], ['abbreviation' => 'barum']);
    $tonne = Unit::firstOrCreate(['name' => 'Tonne UM'], ['abbreviation' => 'tum']);

    $client    = Client::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-UNITMARGIN'], [
        'name' => 'Dépôt UnitMargin', 'company_id' => $company->id, 'is_active' => true,
    ]);

    return compact('company', 'fy', 'user', 'client', 'warehouse', 'kg', 'barre', 'tonne');
}

/**
 * @param  array<string,mixed>  $f
 * @param  array<string,mixed>  $line
 */
function unitMarginQuote(array $f, array $line): Quote
{
    return app(QuoteService::class)->create([
        'client_id'    => $f['client']->id,
        'issued_at'    => '2026-07-29',
        'expires_at'   => '2026-10-27',
        'warehouse_id' => $f['warehouse']->id,
        'price_list'   => 'Tarif standard 2026',
        'items'        => [array_merge([
            'description'      => 'Fer à béton 12',
            'quantity'         => 10,
            'unit_price'       => 10_000,
            'discount_percent' => 0,
            'tax_rate_value'   => 18,
        ], $line)],
    ]);
}

// ── Le schéma ────────────────────────────────────────────────────────────────

it('porte unit_cost sur les lignes de devis et de commande, au type de la facture', function () {
    $expected = collect(Schema::getColumnListing('invoice_items'))->contains('unit_cost');
    expect($expected)->toBeTrue();

    foreach (['quote_items', 'order_items'] as $table) {
        expect(Schema::hasColumn($table, 'unit_cost'))->toBeTrue("{$table} devrait porter unit_cost.");
    }
});

// ── L'unité dérivée ──────────────────────────────────────────────────────────

it('retient l’unité de VENTE de l’article avant son unité de gestion', function () {
    // Le cas métier : un fer à béton géré au kilo mais vendu à la barre.
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable'  => true,
        'unit_id'      => $f['kg']->id,
        'sale_unit_id' => $f['barre']->id,
    ]);

    $quote = unitMarginQuote($f, ['product_id' => $product->id]);

    expect($quote->items->first()->unit_id)->toBe($f['barre']->id);
});

it('retombe sur l’unité de gestion quand aucune unité de vente n’est définie', function () {
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'unit_id' => $f['kg']->id, 'sale_unit_id' => null,
    ]);

    expect(unitMarginQuote($f, ['product_id' => $product->id])->items->first()->unit_id)
        ->toBe($f['kg']->id);
});

it('respecte l’unité choisie par l’utilisateur plutôt que celle de l’article', function () {
    // Une vente exceptionnelle à la tonne sur un article habituellement à la barre.
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'unit_id' => $f['kg']->id, 'sale_unit_id' => $f['barre']->id,
    ]);

    $quote = unitMarginQuote($f, ['product_id' => $product->id, 'unit_id' => $f['tonne']->id]);

    expect($quote->items->first()->unit_id)->toBe($f['tonne']->id);
});

it('applique la même dérivation aux lignes de commande', function () {
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'unit_id' => $f['kg']->id, 'sale_unit_id' => $f['barre']->id,
    ]);

    $order = app(OrderService::class)->create([
        'client_id' => $f['client']->id,
        'issued_at' => '2026-07-29',
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Fer à béton 12',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ]);

    expect($order->items->first()->unit_id)->toBe($f['barre']->id);
});

it('laisse l’unité vide sur une ligne libre sans article', function () {
    // Une ligne de prestation sans référence catalogue n'a pas d'unité à hériter :
    // inventer une valeur serait pire que l'absence.
    $f = unitMarginFixture();

    expect(unitMarginQuote($f, ['product_id' => null])->items->first()->unit_id)->toBeNull();
});

// ── Le coût dérivé ───────────────────────────────────────────────────────────

it('retient le coût moyen pondéré en priorité', function () {
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable'        => true,
        'weighted_avg_cost'  => 6_500,
        'cout_standard'      => 6_000,
        'last_purchase_price' => 5_800,
        'purchase_price'     => 5_500,
    ]);

    expect((float) unitMarginQuote($f, ['product_id' => $product->id])->items->first()->unit_cost)
        ->toBe(6500.00);
});

it('descend la chaîne de repli quand le coût moyen est absent', function () {
    $f = unitMarginFixture();
    $service = app(SalesLineDefaultsService::class);

    $sansCump = Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 0, 'cout_standard' => 6_000]);
    expect($service->resolveUnitCost($sansCump))->toBe(6000.00);

    $sansStandard = Product::factory()->create([
        'is_sellable' => true, 'weighted_avg_cost' => 0, 'cout_standard' => 0, 'last_purchase_price' => 5_800,
    ]);
    expect($service->resolveUnitCost($sansStandard))->toBe(5800.00);
});

it('ne retient JAMAIS un coût nul — il afficherait 100 % de marge', function () {
    // Le piège : un coût à zéro produirait une marge parfaite et masquerait
    // précisément le cas à surveiller, un article dont le coût n'est pas renseigné.
    $f = unitMarginFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'weighted_avg_cost' => 0, 'cout_standard' => 0,
        'last_purchase_price' => 0, 'purchase_price' => 0,
    ]);

    expect(app(SalesLineDefaultsService::class)->resolveUnitCost($product))->toBeNull()
        ->and(unitMarginQuote($f, ['product_id' => $product->id])->items->first()->unit_cost)->toBeNull();
});

it('FIGE le coût : le faire évoluer ensuite ne change pas la ligne', function () {
    // Le CUMP bouge à chaque réception. Une marge recalculée aujourd'hui sur un
    // devis de la semaine dernière ne serait pas celle vue en négociant, et ne
    // serait donc pas auditable.
    $f = unitMarginFixture();
    $product = Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $quote = unitMarginQuote($f, ['product_id' => $product->id]);
    $product->update(['weighted_avg_cost' => 9_900]);

    expect((float) $quote->items->first()->fresh()->unit_cost)->toBe(6500.00);
});

it('respecte un coût explicitement fourni, sans le remplacer', function () {
    $f = unitMarginFixture();
    $product = Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $quote = unitMarginQuote($f, ['product_id' => $product->id, 'unit_cost' => 7_200]);

    expect((float) $quote->items->first()->unit_cost)->toBe(7200.00);
});

// ── L'écran ──────────────────────────────────────────────────────────────────

/** @param array<string,mixed> $f */
function unitMarginGrant(array $f, string $suffix, array $abilities): void
{
    $role = Role::firstOrCreate(['name' => 'unitmargin_'.$suffix, 'guard_name' => 'web']);
    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $f['user']->assignRole($role);
}

it('expose la colonne Unité et l’alerte de prix plancher', function () {
    $f = unitMarginFixture();
    unitMarginGrant($f, 'saisie', ['quotes.view', 'quotes.create']);

    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->toContain('>Unité<')
        ->and($html)->toContain('[unit_id]')
        // Le dépassement de plancher était calculé puis jamais affiché : seul le
        // plafond, pourtant simple conseil, était signalé.
        ->and($html)->toContain('plancher')
        ->and($html)->toContain('_below_floor');
});

it('n’expose AUCUN coût à qui n’a pas le droit de voir la marge', function () {
    $f = unitMarginFixture();
    unitMarginGrant($f, 'sansmarge', ['quotes.view', 'quotes.create']);
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    // L'assertion porte sur la forme SÉRIALISÉE `"weighted_avg_cost":` — avec les
    // guillemets doubles et le deux-points du JSON. Chercher le simple nom
    // d'attribut ne prouverait rien : il figure aussi dans le helper JavaScript
    // qui énumère les sources de coût, présent quelle que soit la permission et
    // qui ne trouve alors aucune valeur. Ce qui doit être absent, c'est la DONNÉE.
    foreach (['weighted_avg_cost', 'cout_standard', 'last_purchase_price', 'purchase_price'] as $costField) {
        expect($html)->not->toContain('"'.$costField.'":');
    }

    // …et le bloc qui l'afficherait.
    expect($html)->not->toContain('Taux de marge');
});

it('affiche le bloc de marge au porteur de sales.view_margin', function () {
    $f = unitMarginFixture();
    unitMarginGrant($f, 'avecmarge', ['quotes.view', 'quotes.create', 'sales.view_margin']);
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    // Même discriminateur que le test précédent, en positif : la donnée EST là.
    expect($html)->toContain('Taux de marge')
        ->and($html)->toContain('Lignes sans coût')
        ->and($html)->toContain('"weighted_avg_cost":');
});

it('accorde le droit de voir la marge au responsable commercial, pas au magasinier', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    expect(Role::where('name', 'responsable_commercial')->first()?->permissions->pluck('name')->contains('sales.view_margin'))
        ->toBeTrue('Qui négocie les prix doit voir la marge.')
        ->and(Role::where('name', 'magasinier')->first()?->permissions->pluck('name')->contains('sales.view_margin'))
        ->toBeFalse('Un prix de revient ne se diffuse pas au magasin.');
});
