<?php

/**
 * [Ventes] L'écran de saisie des commandes est mis au niveau de celui des devis.
 *
 * Diagnostic : l'écran commandes portait les MÊMES défauts que le devis, en plus
 * nombreux.
 *
 *  1. `unit_id` jamais posté — 100 % des lignes de commande enregistrées sans unité.
 *  2. Aucun appel au service tarifaire : ni prix conseillé, ni plancher, ni plafond.
 *     Le devis en avait un. Or la commande est le document qui ENGAGE.
 *  3. Aucune marge : `unit_cost` n'existait que sur `invoice_items`.
 *  4. Neuf exemples lisibles comme des valeurs saisies, dont « TRANSPORT PLUS » et
 *     « 11 BF 2567 » — un transporteur et une immatriculation plausibles sur un
 *     document qui commande une livraison.
 *  5. Quatre doublons : « Type de document », « N° commande », « Taxes », le « XOF »
 *     en lecture seule, plus le libellé trompeur « Prix / Devise ».
 *  6. `delivery_warehouse_id` et `price_list` marqués d'un astérisque rouge mais
 *     `nullable` côté serveur.
 *  7. Le contrôleur sérialisait TOUTES les colonnes produit — coûts inclus — parce
 *     que `withSum()` pose `select(products.*)` avant que `get($colonnes)` ne passe.
 *
 * Note assumée : `delivery_warehouse_id` (commandes) et `warehouse_id` (devis)
 * nomment la même notion. Le renommage n'a PAS été fait — il toucherait la
 * réservation de stock et le bon de préparation pour un gain purement cosmétique.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function orderParityFixture(bool $exempt = false): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'ORDPARITY-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'OrdParity Co'], [
        'email' => 'ordparity@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    $client    = Client::factory()->create(['is_active' => true, 'is_tax_exempt' => $exempt]);
    $kg        = Unit::firstOrCreate(['name' => 'Kilo OrdParity'], ['abbreviation' => 'kgop']);
    $barre     = Unit::firstOrCreate(['name' => 'Barre OrdParity'], ['abbreviation' => 'barop']);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-ORDPARITY'], [
        'name' => 'Dépôt OrdParity', 'company_id' => $company->id, 'is_active' => true, 'can_sale' => true,
    ]);

    test()->actingAs($user);

    return compact('company', 'fy', 'user', 'client', 'warehouse', 'kg', 'barre');
}

/** @param array<string,mixed> $f */
function orderParityGrant(array $f, string $suffix, array $abilities): void
{
    $role = Role::firstOrCreate(['name' => 'ordparity_'.$suffix, 'guard_name' => 'web']);
    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $f['user']->assignRole($role);
}

/** @param array<string,mixed> $f */
function orderParityHtml(array $f, string $suffix, array $extra = []): string
{
    orderParityGrant($f, $suffix, array_merge(['orders.view', 'orders.create'], $extra));

    return test()->get(route('ventes.commandes.create'))->assertOk()->getContent();
}

// ── Les ajouts ───────────────────────────────────────────────────────────────

it('expose la colonne Unité, absente jusqu’ici du tableau de lignes', function () {
    $html = orderParityHtml(orderParityFixture(), 'unite');

    expect($html)->toContain('>Unité<')
        ->and($html)->toContain('[unit_id]');
});

it('affiche l’alerte de prix plancher, que cet écran ne consultait pas', function () {
    // Le devis interrogeait SalesPricingService, la commande non — alors que c'est
    // elle qui engage l'entreprise.
    $html = orderParityHtml(orderParityFixture(), 'plancher');

    expect($html)->toContain('plancher')
        ->and($html)->toContain('_below_floor')
        ->and($html)->toContain('fetchAdvisedPrice');
});

it('dérive l’unité de l’article sur une ligne de commande', function () {
    // Cas métier : fer à béton géré au kilo, vendu à la barre.
    $f = orderParityFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'unit_id' => $f['kg']->id, 'sale_unit_id' => $f['barre']->id,
    ]);

    $order = app(OrderService::class)->create([
        'client_id' => $f['client']->id, 'issued_at' => '2026-07-30',
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Fer à béton 12',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ]);

    expect($order->items->first()->unit_id)->toBe($f['barre']->id);
});

it('fige le coût sur la ligne pour rendre la marge calculable', function () {
    $f = orderParityFixture();
    $product = Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $order = app(OrderService::class)->create([
        'client_id' => $f['client']->id, 'issued_at' => '2026-07-30',
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Tôle bac',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ]);

    expect((float) $order->items->first()->unit_cost)->toBe(6500.00);

    // Figé : le CUMP peut bouger, la ligne ne bouge pas.
    $product->update(['weighted_avg_cost' => 9_900]);
    expect((float) $order->items->first()->fresh()->unit_cost)->toBe(6500.00);
});

// ── Les doublons retirés ─────────────────────────────────────────────────────

it('ne répète plus le type de document ni le numéro', function () {
    $html = orderParityHtml(orderParityFixture(), 'doublons');

    expect($html)->not->toContain('Type de document')
        ->and($html)->not->toContain('N° commande')
        ->and($html)->not->toContain('Auto à la création')
        // Le statut, seule information non redondante du bloc, subsiste.
        ->and($html)->toContain('Statut');
});

it('ne répète plus le code devise et n’expose qu’un seul champ de taux', function () {
    $html = orderParityHtml(orderParityFixture(), 'devise');

    expect($html)->not->toMatch('/value="XOF"[^>]*readonly/')
        // Deux champs de même nom auraient fait gagner le dernier.
        ->and(substr_count($html, 'name="exchange_rate"'))->toBe(1);
});

it('renomme « Prix / Devise » en « Mode de prix » sans changer le champ posté', function () {
    $html = orderParityHtml(orderParityFixture(), 'modeprix');

    expect($html)->not->toContain('Prix / Devise')
        ->and($html)->toContain('Mode de prix')
        ->and($html)->toContain('name="price_mode"');
});

it('n’expose plus de champ « Taxes » concurrent de « TVA par défaut »', function () {
    $html = orderParityHtml(orderParityFixture(), 'taxes');

    expect($html)->not->toContain('default_tax_label');
});

// ── Les exemples trompeurs ───────────────────────────────────────────────────

it('ne présente plus aucun exemple lisible comme une valeur saisie', function () {
    // « TRANSPORT PLUS » et « 11 BF 2567 » sont les deux plus dangereux : un
    // magasinier les lit, charge le camion, et rien n'a été enregistré.
    $html = orderParityHtml(orderParityFixture(), 'exemples');

    foreach (['TRANSPORT PLUS', '11 BF 2567', 'OA METAL INDUSTRIE', 'PROJ-2026-0008', 'Kossodo', 'Régime réel normal'] as $sample) {
        expect($html)->not->toMatch('/placeholder="(?!Ex\. )[^"]*'.preg_quote($sample, '/').'/u');
    }
});

// ── Les astérisques tenus ────────────────────────────────────────────────────

it('exige réellement l’entrepôt de livraison et la liste de prix', function () {
    $f = orderParityFixture();
    orderParityGrant($f, 'requis', ['orders.view', 'orders.create']);

    $this->post(route('ventes.commandes.store'), [
        'client_id' => $f['client']->id,
        'issued_at' => '2026-07-30',
        'items'     => [[
            'description' => 'Tôle bac', 'quantity' => 10,
            'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ])->assertSessionHasErrors(['delivery_warehouse_id', 'price_list']);

    expect(Order::count())->toBe(0);
});

it('porte l’attribut required côté navigateur aussi', function () {
    // Un `required` serveur seul laisse l'utilisateur soumettre pour rien ; un
    // `required` navigateur seul se contourne avec n'importe quel client HTTP.
    $html = orderParityHtml(orderParityFixture(), 'requisnav');

    expect($html)->toMatch('/name="delivery_warehouse_id" required/')
        ->and($html)->toMatch('/name="price_list" required/');
});

// ── La fuite de coûts ────────────────────────────────────────────────────────

it('ne sérialise AUCUN coût sans le droit de voir la marge', function () {
    // `withSum()` posait `select(products.*)` avant que les colonnes ne soient
    // passées à `get()` : elles étaient AJOUTÉES, pas substituées. Cet écran
    // sérialisait donc tous les coûts produit pour tout utilisateur.
    $f = orderParityFixture();
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = orderParityHtml($f, 'sansmarge');

    foreach (['weighted_avg_cost', 'cout_standard', 'last_purchase_price', 'purchase_price'] as $costField) {
        expect($html)->not->toContain('"'.$costField.'":');
    }
    expect($html)->not->toContain('Taux de marge');
});

it('affiche la marge au porteur de sales.view_margin', function () {
    $f = orderParityFixture();
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = orderParityHtml($f, 'avecmarge', ['sales.view_margin']);

    expect($html)->toContain('Taux de marge')
        ->and($html)->toContain('Marge brute')
        ->and($html)->toContain('"weighted_avg_cost":');
});

// ── Le libellé de taxation dérivé ────────────────────────────────────────────

it('dérive le libellé de taxation du taux réellement appliqué', function () {
    $f = orderParityFixture();
    $product = Product::factory()->create(['is_sellable' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => $f['client']->id, 'issued_at' => '2026-07-30',
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Tôle bac',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ]);

    expect($order->default_tax_label)->toBe('TVA 18%');
});

it('ignore un libellé posté à la main : il ne peut plus contredire les lignes', function () {
    $f = orderParityFixture();
    $product = Product::factory()->create(['is_sellable' => true]);

    $order = app(OrderService::class)->create([
        'client_id'         => $f['client']->id,
        'issued_at'         => '2026-07-30',
        'default_tax_label' => 'Exonéré',
        'items'             => [[
            'product_id' => $product->id, 'description' => 'Tôle bac',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        ]],
    ]);

    expect($order->default_tax_label)->toBe('TVA 18%');
});

it('dérive « Exonéré » de l’exonération du client', function () {
    $f = orderParityFixture(exempt: true);
    $product = Product::factory()->create(['is_sellable' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => $f['client']->id, 'issued_at' => '2026-07-30',
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Tôle bac',
            'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]);

    expect($order->default_tax_label)->toBe('Exonéré');
});
