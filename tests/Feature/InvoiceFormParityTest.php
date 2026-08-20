<?php

/**
 * [Ventes] L'écran de saisie des factures est mis au niveau du devis et de la commande.
 *
 * Défauts corrigés :
 *
 *  1. Fuite de coûts — `withSum()` posait `select(products.*)` avant que les colonnes
 *     n'arrivent à `get()`, qui les AJOUTAIT au lieu de les substituer. `create()` et
 *     `edit()` sérialisaient donc CUMP, coût standard, dernier prix d'achat, taux de
 *     marge cible et référence fournisseur pour tout utilisateur habilité à facturer.
 *     C'étaient les DEUX DERNIÈRES occurrences du motif dans `app/`.
 *  2. `unit_id` jamais posté — aucune ligne de facture ne portait d'unité.
 *  3. Aucun appel au service tarifaire : ni plancher, ni plafond, sur le document qui
 *     engage comptablement.
 *  4. `unit_cost` existait déjà sur `invoice_items` mais n'était affiché nulle part :
 *     on facturait sans voir sa marge.
 *  5. Dix exemples lisibles comme des valeurs saisies, dont « TRANSPORT PLUS »,
 *     « 11 BF 2567 » et « Coris Bank International ».
 *  6. Trois doublons : « N° facture » en lecture seule, le champ « Taxes », le « XOF »
 *     en lecture seule.
 *
 * À NOTER : « Type de document » est CONSERVÉ sur cet écran. Ce n'est pas un doublon
 * mais un vrai choix à cinq valeurs — standard, proforma, acompte, partielle,
 * récurrente. Le scan automatique l'avait épinglé par correspondance de chaîne.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function invParityFixture(bool $exempt = false): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'INVPARITY-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'InvParity Co'], [
        'email' => 'invparity@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user   = User::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['is_active' => true, 'is_tax_exempt' => $exempt]);
    $kg     = Unit::firstOrCreate(['name' => 'Kilo InvParity'], ['abbreviation' => 'kgip']);
    $barre  = Unit::firstOrCreate(['name' => 'Barre InvParity'], ['abbreviation' => 'barip']);

    test()->actingAs($user);

    return compact('company', 'fy', 'user', 'client', 'kg', 'barre');
}

/** @param array<string,mixed> $f */
function invParityGrant(array $f, string $suffix, array $abilities): void
{
    $role = Role::firstOrCreate(['name' => 'invparity_'.$suffix, 'guard_name' => 'web']);
    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $f['user']->assignRole($role);
}

/** @param array<string,mixed> $f */
function invParityHtml(array $f, string $suffix, array $extra = []): string
{
    invParityGrant($f, $suffix, array_merge(['invoices.view', 'invoices.create'], $extra));

    return test()->get(route('ventes.factures.create'))->assertOk()->getContent();
}

/**
 * @param  array<string,mixed>  $f
 * @param  array<string,mixed>  $line
 */
function invParityInvoice(array $f, array $line = [], array $header = []): App\Models\Invoice
{
    return app(InvoiceService::class)->create(array_merge([
        'client_id' => $f['client']->id,
        'issued_at' => '2026-07-30',
        'due_at'    => '2026-08-29',
        'items'     => [array_merge([
            'description'      => 'Tôle bac 0,40',
            'quantity'         => 10,
            'unit_price'       => 10_000,
            'discount_percent' => 0,
            'tax_rate_value'   => 18,
        ], $line)],
    ], $header));
}

// ── Le schéma ────────────────────────────────────────────────────────────────

it('aligne unit_cost sur les lignes de devis et de commande', function () {
    // Avant : `invoice_items.unit_cost` était NOT NULL défaut 0.00, alors que
    // `quote_items` et `order_items` l'acceptaient nul. Un coût INCONNU devenait donc
    // 0,00 sur la facture — indiscernable d'un coût réellement nul, alors qu'un zéro
    // affiche 100 % de marge et masque exactement le cas à surveiller.
    foreach (['quote_items', 'order_items', 'invoice_items'] as $table) {
        expect(Schema::hasColumn($table, 'unit_cost'))->toBeTrue();
    }

    $f = invParityFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'weighted_avg_cost' => 0, 'cout_standard' => 0,
        'last_purchase_price' => 0, 'purchase_price' => 0,
    ]);

    // Un article sans coût renseigné produit NULL, pas 0.
    expect(invParityInvoice($f, ['product_id' => $product->id])->items->first()->unit_cost)->toBeNull();
});

// ── L'unité et le coût dérivés ───────────────────────────────────────────────

it('dérive l’unité de vente de l’article sur une ligne de facture', function () {
    $f = invParityFixture();
    $product = Product::factory()->create([
        'is_sellable' => true, 'unit_id' => $f['kg']->id, 'sale_unit_id' => $f['barre']->id,
    ]);

    expect(invParityInvoice($f, ['product_id' => $product->id])->items->first()->unit_id)
        ->toBe($f['barre']->id);
});

it('fige le coût sur la ligne, insensible aux réceptions ultérieures', function () {
    $f = invParityFixture();
    $product = Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $invoice = invParityInvoice($f, ['product_id' => $product->id]);
    expect((float) $invoice->items->first()->unit_cost)->toBe(6500.00);

    $product->update(['weighted_avg_cost' => 9_900]);
    expect((float) $invoice->items->first()->fresh()->unit_cost)->toBe(6500.00);
});

// ── L'écran ──────────────────────────────────────────────────────────────────

it('expose la colonne Unité et la garde de prix plancher', function () {
    $html = invParityHtml(invParityFixture(), 'ecran');

    expect($html)->toContain('>Unité<')
        ->and($html)->toContain('[unit_id]')
        ->and($html)->toContain('_below_floor')
        ->and($html)->toContain('fetchAdvisedPrice');
});

it('retire le numéro en doublon mais conserve le statut', function () {
    $html = invParityHtml(invParityFixture(), 'doublons');

    expect($html)->not->toContain('N° facture')
        ->and($html)->not->toContain('Auto à la création')
        ->and($html)->toContain('Statut');
});

it('CONSERVE « Type de document » : c’est un vrai choix, pas un doublon', function () {
    // Cinq valeurs métier distinctes. Le supprimer aurait retiré la possibilité
    // d'émettre une proforma ou une facture d'acompte.
    $html = invParityHtml(invParityFixture(), 'typedoc');

    expect($html)->toContain('Type de document')
        ->and($html)->toContain('proforma')
        ->and($html)->toContain('acompte');
});

it('ne répète plus le code devise et n’expose qu’un seul champ de taux', function () {
    $html = invParityHtml(invParityFixture(), 'devise');

    expect($html)->not->toMatch('/value="XOF"[^>]*readonly/')
        ->and(substr_count($html, 'name="exchange_rate"'))->toBe(1);
});

it('n’expose plus de champ « Taxes » concurrent de « TVA par défaut »', function () {
    $html = invParityHtml(invParityFixture(), 'taxes');

    expect($html)->not->toContain('default_tax_label')
        ->and($html)->not->toContain('Prix / Devise')
        ->and($html)->toContain('Mode de prix');
});

it('ne présente plus aucun exemple lisible comme une valeur saisie', function () {
    $html = invParityHtml(invParityFixture(), 'exemples');

    foreach (['TRANSPORT PLUS', '11 BF 2567', 'Coris Bank International', 'OA METAL INDUSTRIE', 'PROJ-2026-0008', 'Kossodo'] as $sample) {
        expect($html)->not->toMatch('/placeholder="(?!Ex\. )[^"]*'.preg_quote($sample, '/').'/u');
    }
});

// ── La fuite de coûts ────────────────────────────────────────────────────────

it('ne sérialise AUCUN coût sans le droit de voir la marge', function () {
    $f = invParityFixture();
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = invParityHtml($f, 'sansmarge');

    foreach (['weighted_avg_cost', 'cout_standard', 'last_purchase_price', 'purchase_price'] as $costField) {
        expect($html)->not->toContain('"'.$costField.'":');
    }
    expect($html)->not->toContain('Taux de marge');
});

it('affiche la marge au porteur de sales.view_margin', function () {
    $f = invParityFixture();
    Product::factory()->create(['is_sellable' => true, 'weighted_avg_cost' => 6_500]);

    $html = invParityHtml($f, 'avecmarge', ['sales.view_margin']);

    expect($html)->toContain('Taux de marge')
        ->and($html)->toContain('Marge brute')
        ->and($html)->toContain('"weighted_avg_cost":');
});

// ── Le libellé de taxation dérivé, règle UNIQUE ──────────────────────────────

it('dérive le libellé de taxation du taux réellement appliqué', function () {
    $f = invParityFixture();

    expect(invParityInvoice($f)->default_tax_label)->toBe('TVA 18%');
});

it('ignore un libellé posté à la main', function () {
    $f = invParityFixture();

    expect(invParityInvoice($f, [], ['default_tax_label' => 'Exonéré'])->default_tax_label)
        ->toBe('TVA 18%');
});

it('dérive « Exonéré » de l’exonération du client', function () {
    $f = invParityFixture(exempt: true);

    expect(invParityInvoice($f, ['tax_rate_value' => 0])->default_tax_label)->toBe('Exonéré');
});

it('applique la MÊME règle de libellé sur les trois documents', function () {
    // La règle vivait en deux exemplaires — devis et commande — et j'allais la
    // recopier une troisième fois. Elle est désormais dans un service unique auquel
    // les trois services délèguent : recopiée, elle finirait par diverger.
    foreach (['QuoteService', 'OrderService', 'InvoiceService'] as $service) {
        $source = file_get_contents(app_path('Services/'.$service.'.php'));
        expect($source)->toContain('SalesTaxLabelService::class)->derive')
            ->and($source)->not->toContain('private function deriveTaxLabel');
    }
});
