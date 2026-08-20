<?php

/**
 * [UI] Suppression des répétitions de l'en-tête du devis.
 *
 * Cinq redondances retirées de « Informations générales » :
 *
 *  1. « Type de document = Devis » (lecture seule) — répétait le titre de la page.
 *  2. « N° devis » (lecture seule) — le numéro figurait TROIS fois : ici, dans le
 *     titre, et dans la barre d'état basse (« Document : … »).
 *  3. « Taxes » (`default_tax_label`) — libellé stocké, validé, `fillable`, mais
 *     JAMAIS lu par aucune logique et absent de toute fiche ou PDF. Il doublonnait
 *     « TVA par défaut », seul champ pilotant réellement la TVA des lignes, et
 *     pouvait le contredire. La valeur est désormais dérivée de l'état réel.
 *  4. Le « XOF » en lecture seule accolé au taux de change — écho du champ
 *     « Devise », affiché en dur même après changement de devise.
 *  5. Le libellé « Prix / Devise » — le sélecteur ne porte que le mode de prix ;
 *     « Devise » y était une troisième mention de la devise.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QuoteService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function dedupFixture(bool $exempt = false): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DEDUP-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Dedup Co'], [
        'email' => 'dedup@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    $role = Role::firstOrCreate(['name' => 'dedup_commercial', 'guard_name' => 'web']);
    foreach (['quotes.view', 'quotes.create'] as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
    test()->actingAs($user);

    $client    = Client::factory()->create(['is_active' => true, 'is_tax_exempt' => $exempt]);
    $product   = Product::factory()->create(['is_sellable' => true]);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-DEDUP'], [
        'name' => 'Dépôt Dedup', 'company_id' => $company->id, 'is_active' => true,
    ]);

    return compact('company', 'fy', 'user', 'client', 'product', 'warehouse');
}

/**
 * @param  array<string,mixed>  $f
 * @param  array<string,mixed>  $extra
 */
function dedupQuote(array $f, array $extra = [], float $taxRate = 18): Quote
{
    return app(QuoteService::class)->create(array_merge([
        'client_id'    => $f['client']->id,
        'issued_at'    => '2026-07-30',
        'expires_at'   => '2026-08-29',
        'warehouse_id' => $f['warehouse']->id,
        'price_list'   => 'Tarif standard 2026',
        'items'        => [[
            'product_id'       => $f['product']->id,
            'description'      => 'Tôle bac 0,40',
            'quantity'         => 10,
            'unit_price'       => 10_000,
            'discount_percent' => 0,
            'tax_rate_value'   => $taxRate,
        ]],
    ], $extra));
}

// ── Les champs retirés ───────────────────────────────────────────────────────

it('n’affiche plus le type de document, déjà porté par le titre', function () {
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->not->toContain('Type de document')
        // Le titre, lui, l'annonce bien.
        ->and($html)->toContain('Devis :');
});

it('n’affiche plus le numéro dans un champ : le titre et la barre d’état le portent', function () {
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->not->toContain('N° devis')
        ->and($html)->not->toContain('Auto à la création')
        // La barre d'état basse reste la source d'information.
        ->and($html)->toContain('Document :');
});

it('conserve le statut, qui n’était affiché nulle part ailleurs', function () {
    // Retirer le champ « N° devis » ne doit pas emporter le badge de statut qu'il
    // hébergeait : c'était la seule information non redondante du bloc.
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->toContain('Statut')
        ->and($html)->toContain('Brouillon');
});

it('n’expose plus de champ « Taxes » concurrent de « TVA par défaut »', function () {
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->not->toContain('default_tax_label')
        // Le champ qui pilote réellement la TVA des lignes demeure.
        ->and($html)->toContain('TVA par défaut');
});

it('ne répète plus le code devise à côté du taux de change', function () {
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    // Plus d'input en lecture seule figé sur « XOF »…
    expect($html)->not->toMatch('/value="XOF"[^>]*readonly/')
        // …et un SEUL champ `exchange_rate` : deux champs de même nom auraient fait
        // gagner le dernier au détriment du premier.
        ->and(substr_count($html, 'name="exchange_rate"'))->toBe(1);
});

it('nomme le sélecteur par ce qu’il fait : le mode de prix', function () {
    dedupFixture();
    $html = $this->get(route('ventes.devis.create'))->assertOk()->getContent();

    expect($html)->not->toContain('Prix / Devise')
        ->and($html)->toContain('Mode de prix')
        // Le champ posté est inchangé : c'est un renommage d'étiquette.
        ->and($html)->toContain('name="price_mode"');
});

// ── La valeur dérivée remplace la saisie parallèle ───────────────────────────

it('dérive le libellé de taxation du taux réellement appliqué', function () {
    $f = dedupFixture();

    expect(dedupQuote($f, [], taxRate: 18)->default_tax_label)->toBe('TVA 18%');
});

it('dérive « Exonéré » du mode de prix exonéré', function () {
    $f = dedupFixture();

    expect(dedupQuote($f, ['price_mode' => 'exonere'])->default_tax_label)->toBe('Exonéré');
});

it('dérive « Exonéré » de l’exonération du client', function () {
    // Le client exonéré était l'une des trois sources d'exonération ; le libellé
    // était la seule à pouvoir les contredire, faute d'être lue.
    $f = dedupFixture(exempt: true);

    expect(dedupQuote($f)->default_tax_label)->toBe('Exonéré');
});

it('dérive « Exonéré » quand aucune ligne ne porte de taxe', function () {
    $f = dedupFixture();

    expect(dedupQuote($f, [], taxRate: 0)->default_tax_label)->toBe('Exonéré');
});

it('retient le taux le PLUS ÉLEVÉ des lignes, celui que le client lit', function () {
    $f = dedupFixture();

    $quote = app(QuoteService::class)->create([
        'client_id'    => $f['client']->id,
        'issued_at'    => '2026-07-30',
        'expires_at'   => '2026-08-29',
        'warehouse_id' => $f['warehouse']->id,
        'price_list'   => 'Tarif standard 2026',
        'items'        => [
            ['product_id' => $f['product']->id, 'description' => 'Ligne à 5 %',  'quantity' => 1, 'unit_price' => 1_000, 'discount_percent' => 0, 'tax_rate_value' => 5],
            ['product_id' => $f['product']->id, 'description' => 'Ligne à 18 %', 'quantity' => 1, 'unit_price' => 1_000, 'discount_percent' => 0, 'tax_rate_value' => 18],
        ],
    ]);

    expect($quote->default_tax_label)->toBe('TVA 18%');
});

it('ignore un libellé posté à la main : il ne peut plus contredire les lignes', function () {
    // C'était le défaut : « Taxes = Exonéré » avec des lignes à 18 % produisait un
    // devis étiqueté exonéré et taxé. La dérivation reprend la main.
    $f = dedupFixture();

    expect(dedupQuote($f, ['default_tax_label' => 'Exonéré'], taxRate: 18)->default_tax_label)
        ->toBe('TVA 18%');
});
