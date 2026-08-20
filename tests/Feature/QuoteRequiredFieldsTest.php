<?php

/**
 * [Ventes §UI] Les astérisques rouges du formulaire de devis sont tenus.
 *
 * Défaut corrigé. « Entrepôt / Dépôt », « Liste de prix » et « Date de validité »
 * portaient l'astérisque rouge à l'écran, mais :
 *   - les champs n'avaient pas l'attribut `required` côté navigateur ;
 *   - les règles étaient `nullable` dans StoreQuoteRequest ET UpdateQuoteRequest.
 *
 * Un astérisque décoratif est pire qu'aucun : il fait croire à un contrôle qui
 * n'existe pas. Un devis partait sans entrepôt de départ — donc sans base pour
 * la réservation de stock — et sans date de validité, donc sans péremption.
 *
 * Corrigé aux DEUX niveaux. Ce test le prouve aux deux niveaux, car un `required`
 * HTML seul se contourne avec n'importe quel client HTTP.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function quoteReqFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QUOTEREQ-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'QuoteReq Co'], [
        'email' => 'quotereq@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    $role = Role::firstOrCreate(['name' => 'quotereq_commercial', 'guard_name' => 'web']);
    foreach (['quotes.view', 'quotes.create', 'quotes.edit'] as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
    test()->actingAs($user);

    $client    = Client::factory()->create(['is_active' => true]);
    $unit      = Unit::firstOrCreate(['name' => 'Kg QuoteReq'], ['abbreviation' => 'kgqr']);
    $product   = Product::factory()->create(['is_sellable' => true]);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-QUOTEREQ'], [
        'name' => 'Dépôt QuoteReq', 'company_id' => $company->id, 'is_active' => true,
    ]);

    return compact('company', 'fy', 'user', 'client', 'unit', 'product', 'warehouse');
}

/**
 * Charge utile COMPLÈTE et valide. Chaque test en retire une seule clé, pour que
 * l'échec attendu ne puisse venir que de celle-là.
 *
 * @param  array<string,mixed>  $f
 * @return array<string,mixed>
 */
function quoteReqPayload(array $f): array
{
    return [
        'client_id'    => $f['client']->id,
        'issued_at'    => '2026-07-29',
        'expires_at'   => '2026-10-27',
        'warehouse_id' => $f['warehouse']->id,
        'price_list'   => 'Tarif standard 2026',
        'items'        => [[
            'product_id'       => $f['product']->id,
            'description'      => 'Tôle bac 0,40',
            'quantity'         => 10,
            'unit_price'       => 10_000,
            'discount_percent' => 0,
            'unit_id'          => $f['unit']->id,
            'tax_rate_value'   => 18,
        ]],
    ];
}

// ── Le formulaire annonce les contraintes ────────────────────────────────────

it('porte l’attribut required sur les cinq champs marqués d’un astérisque', function () {
    quoteReqFixture();

    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    foreach (['client_id', 'issued_at', 'expires_at', 'warehouse_id', 'price_list'] as $field) {
        expect($html)->toMatch('/<(?:input|select)[^>]*name="'.$field.'"[^>]* required/')
            ->and(true)->toBeTrue("Le champ {$field} devrait porter required.");
    }
});

it('n’affiche aucun exemple qui puisse se lire comme une valeur saisie', function () {
    // Le nom de la société, une adresse de chantier plausible et une référence de
    // projet crédible étaient affichés en placeholder sur des champs VIDES. Le
    // préfixe « Ex. : » est ce qui les distingue d'une saisie réelle.
    quoteReqFixture();

    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    foreach (['OA METAL INDUSTRIE', 'PROJ-2026-0008', 'Chantier – Kossodo', 'Régime réel normal'] as $sample) {
        expect($html)->not->toMatch('/placeholder="(?!Ex\. )[^"]*'.preg_quote($sample, '/').'/u');
    }
});

// ── Le serveur refuse, indépendamment du navigateur ──────────────────────────

it('refuse un devis sans entrepôt de départ', function () {
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    unset($payload['warehouse_id']);

    $this->post(route('ventes.devis.store'), $payload)->assertSessionHasErrors('warehouse_id');
    expect(Quote::count())->toBe(0);
});

it('refuse un devis sans liste de prix', function () {
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    unset($payload['price_list']);

    $this->post(route('ventes.devis.store'), $payload)->assertSessionHasErrors('price_list');
    expect(Quote::count())->toBe(0);
});

it('refuse un devis sans date de validité', function () {
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    unset($payload['expires_at']);

    $this->post(route('ventes.devis.store'), $payload)->assertSessionHasErrors('expires_at');
    expect(Quote::count())->toBe(0);
});

it('explique en français ce qui manque et pourquoi', function () {
    // Un refus sans motif intelligible pousse l'utilisateur à contourner.
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    unset($payload['warehouse_id'], $payload['expires_at']);

    $errors = $this->post(route('ventes.devis.store'), $payload)
        ->assertSessionHasErrors(['warehouse_id', 'expires_at'])
        ->getSession()->get('errors');

    expect($errors->first('warehouse_id'))->toContain('réservation de stock')
        ->and($errors->first('expires_at'))->toContain('n\'expire jamais');
});

it('refuse un entrepôt inexistant, pas seulement l’absence', function () {
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    $payload['warehouse_id'] = 999_999;

    $this->post(route('ventes.devis.store'), $payload)->assertSessionHasErrors('warehouse_id');
    expect(Quote::count())->toBe(0);
});

// ── Le cas nominal reste possible ────────────────────────────────────────────

it('accepte un devis complet et enregistre les trois champs', function () {
    $f = quoteReqFixture();

    $this->post(route('ventes.devis.store'), quoteReqPayload($f))->assertSessionHasNoErrors();

    $quote = Quote::firstOrFail();
    expect($quote->warehouse_id)->toBe($f['warehouse']->id)
        ->and($quote->price_list)->toBe('Tarif standard 2026')
        ->and($quote->expires_at?->format('Y-m-d'))->toBe('2026-10-27');
});

it('exige aussi une date de validité POSTÉRIEURE à l’émission', function () {
    // `required` sans `after` laisserait passer un devis expiré à sa création.
    $f = quoteReqFixture();
    $payload = quoteReqPayload($f);
    $payload['expires_at'] = '2026-07-28';

    $this->post(route('ventes.devis.store'), $payload)->assertSessionHasErrors('expires_at');
    expect(Quote::count())->toBe(0);
});

// ── La modification ne doit pas rouvrir la porte ─────────────────────────────

it('empêche de vider à la modification ce que la création exige', function () {
    $f = quoteReqFixture();
    $this->post(route('ventes.devis.store'), quoteReqPayload($f))->assertSessionHasNoErrors();
    $quote = Quote::firstOrFail();

    $payload = quoteReqPayload($f);
    unset($payload['warehouse_id'], $payload['price_list']);

    $this->put(route('ventes.devis.update', $quote), $payload)
        ->assertSessionHasErrors(['warehouse_id', 'price_list']);

    // La valeur d'origine tient bon.
    expect($quote->fresh()->warehouse_id)->toBe($f['warehouse']->id);
});
