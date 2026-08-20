<?php

/**
 * Garde du prix d'une ligne commerciale — non-régression.
 *
 * [BUG-A3-SALES-ZERO-PRICE-026] Une vente à zéro traversait devis, soumission,
 * validation et conversion sans un seul refus, dès lors que l'article n'avait
 * aucun coût connu. La seule garde en place dérivait du coût : sans coût, elle
 * valait zéro, et zéro n'est pas inférieur à zéro.
 *
 * [BUG-A3-SALES-PRICE-API-028] Deux définitions du plancher coexistaient. L'API
 * lisait `min_sale_price`, la validation calculait `max(configuré, économique)`.
 * L'écran annonçait donc « 0 F, aucune validation requise » là où la soumission
 * refusait à 6 000 F.
 *
 * Deux règles, distinctes et non substituables :
 *
 *   GARDE 1  prix net > 0          sans dérogation possible — c'est une gratuité
 *   GARDE 2  prix net >= plancher  dérogeable par le circuit existant
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\CommercialLinePriceRule;
use App\Services\Sales\SalesPriceFloorService;
use App\Services\Sales\SalesPricingService;
use App\Services\SalesPriceGuardService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/*
 * INDÉPENDANCE DES ATTENDUS — ces tests ne comptent JAMAIS globalement.
 *
 * `Tests\Feature\Sales\MySqlOrderLineConcurrencyTest` commet volontairement son
 * décor (ses workers ouvrent leurs propres connexions et doivent le voir), si
 * bien que la base n'est plus vide pour les fichiers exécutés ensuite dans le
 * même processus. Un `DB::table('orders')->count() === 0` y devient faux sans
 * qu'aucune règle métier ait bougé, et un `Quote::firstOrFail()` rend le devis
 * d'un AUTRE test.
 *
 * L'attendu porte donc toujours sur l'objet du test, désigné par sa clé.
 */


function zpSociete(): Company
{
    $exercice = FiscalYear::firstOrCreate(
        ['label' => 'ZP-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $societe = Company::firstOrCreate(
        ['name' => 'ZP Co'],
        ['email' => 'zp@iboa.test', 'current_fiscal_year_id' => $exercice->id],
    );
    app()->instance('current_company', $societe);
    Warehouse::firstOrCreate(
        ['code' => 'WZP'],
        ['name' => 'Dépôt ZP', 'company_id' => $societe->id, 'is_active' => true, 'is_default' => true],
    );

    return $societe;
}

function zpUtilisateur(array $permissions = []): User
{
    $u = User::factory()->create(['company_id' => zpSociete()->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    foreach ($permissions as $nom) {
        $u->givePermissionTo(Permission::firstOrCreate(['name' => $nom, 'guard_name' => 'web']));
    }

    return $u;
}

/** Article dont le coût est connu : le plancher économique vaut ce coût. */
function zpArticleAvecCout(float $cout = 6000, float $plancherConfigure = 0): Product
{
    return Product::factory()->create([
        'is_sellable' => true, 'is_active' => true, 'is_manufacturable' => true,
        'production_mode' => 'mto',
        'sale_price' => 0, 'min_sale_price' => $plancherConfigure, 'max_sale_price' => null,
        'weighted_avg_cost' => $cout, 'cout_standard' => 0,
        'last_purchase_price' => 0, 'purchase_price' => 0,
        'margin_rate_target' => 0,
    ]);
}

/**
 * Article sans AUCUN coût connu — le cas qui a révélé le lot 026.
 *
 * `production_mode` est renseigné à dessein : `FulfillmentStrategyResolver`
 * refuse toute ligne sans stratégie d'approvisionnement, et ce refus masquerait
 * celui qu'on veut observer. Une seule variable change : l'absence de coût.
 */
function zpArticleSansCout(): Product
{
    return Product::factory()->create([
        'is_sellable' => true, 'is_active' => true, 'is_manufacturable' => false,
        'is_stockable' => false, 'production_mode' => 'achat_revente',
        'sale_price' => 0, 'min_sale_price' => 0, 'max_sale_price' => null,
        'weighted_avg_cost' => 0, 'cout_standard' => 0,
        'last_purchase_price' => 0, 'purchase_price' => 0,
        'margin_rate_target' => 0,
    ]);
}

/** Charge utile complète : la route exige plus que le service. */
function zpDevisPayload(Product $article, float $prix, ?string $statut = null, float $remise = 0): array
{
    $donnees = [
        'client_id'    => Client::factory()->create(['is_active' => true])->id,
        'issued_at'    => now()->toDateString(),
        'expires_at'   => now()->addDays(30)->toDateString(),
        'warehouse_id' => Warehouse::where('code', 'WZP')->value('id'),
        'price_list'   => 'TARIF GENERAL',
        'items'        => [[
            'product_id'       => $article->id,
            'description'      => 'Ligne de vente',
            'quantity'         => 10,
            'unit_price'       => $prix,
            'discount_percent' => $remise,
            'tax_rate_value'   => 18,
        ]],
    ];

    if ($statut !== null) {
        $donnees['status'] = $statut;
    }

    return $donnees;
}

// ── GARDE 1 : prix nul, indépendante du coût ────────────────────────────────

it('1. accepte un brouillon à zéro — le prix peut rester à fixer', function () {
    zpSociete();

    $article = zpArticleSansCout();

    $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), zpDevisPayload($article, 0))
        ->assertSessionHasNoErrors();

    // L'attendu porte sur LE devis creé, pas sur un compteur global.
    $devis = Quote::latest('id')->firstOrFail();
    expect($devis->status)->toBe('brouillon');
    expect($devis->items()->where('product_id', $article->id)->count())->toBe(1);
});

it('2. refuse la création directe à zéro en statut engageant, SANS coût connu', function () {
    // Le cœur du lot 026 : ce chemin était accepté, faute de plancher.
    zpSociete();
    $article = zpArticleSansCout();

    expect(app(SalesPriceGuardService::class)->effectiveFloor($article))->toBe(0.0);

    $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), zpDevisPayload($article, 0, 'envoye'))
        ->assertSessionHasErrors('items.0.unit_price');
});

it('3. refuse la SOUMISSION d’un brouillon à zéro sans coût connu', function () {
    zpSociete();
    $utilisateur = zpUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zpDevisPayload(zpArticleSansCout(), 0));
    $devis = Quote::latest('id')->firstOrFail();

    $this->actingAs($utilisateur)->post(route('ventes.devis.submit', $devis));

    expect($devis->fresh()->status)->toBe('brouillon');
});

it('4. refuse la VALIDATION d’un devis à zéro sans coût connu', function () {
    zpSociete();
    $utilisateur = zpUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zpDevisPayload(zpArticleSansCout(), 0));
    $devis = Quote::latest('id')->firstOrFail();

    $this->actingAs($utilisateur)->post(route('ventes.devis.submit', $devis));
    $this->actingAs($utilisateur)->post(route('ventes.devis.validate-internal', $devis));

    expect($devis->fresh()->status)->toBe('brouillon');
});

it('5. ne convertit jamais un devis resté brouillon', function () {
    zpSociete();
    $utilisateur = zpUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zpDevisPayload(zpArticleSansCout(), 0));
    $devis = Quote::latest('id')->firstOrFail();

    $this->actingAs($utilisateur)->post(route('ventes.devis.submit', $devis));
    $this->actingAs($utilisateur)->post(route('ventes.devis.convert', $devis));

    expect(DB::table('orders')->where('quote_id', $devis->id)->count())->toBe(0);
});

it('6. refuse une remise de 100 % — le NET fait foi, pas le prix affiché', function () {
    // 10 000 F remisés à 100 % valent zéro. Contrôler `unit_price` seul
    // laisserait passer la gratuité par la porte de la remise.
    zpSociete();

    $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), zpDevisPayload(zpArticleAvecCout(1), 10000, 'envoye', 100))
        ->assertSessionHasErrors('items.0.unit_price');
});

it('7. accepte une remise de 99 % — le net reste positif', function () {
    // Le net vaut 100 F, au-dessus du plancher de 1 F : la garde 1 passe, la
    // garde 2 aussi.
    zpSociete();

    $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), zpDevisPayload(zpArticleAvecCout(1), 10000, 'envoye', 99))
        ->assertSessionHasNoErrors();
});

it('8. refuse zéro même à qui peut approuver une vente sous plancher', function () {
    // Approuver une remise n'est pas offrir la marchandise. Tant que la
    // gratuité explicite n'existe pas [FEATURE-A3-SALES-FREE-LINE-027], aucune
    // permission ne transforme un zéro en vente valide.
    zpSociete();
    $utilisateur = zpUtilisateur(['sales_below_floor.request', 'sales_below_floor.approve']);

    expect($utilisateur->can('sales_below_floor.approve'))->toBeTrue();

    $this->actingAs($utilisateur)
        ->post(route('ventes.devis.store'), zpDevisPayload(zpArticleSansCout(), 0, 'envoye'))
        ->assertSessionHasErrors('items.0.unit_price');
});

// ── GARDE 2 : plancher, avec coût connu ─────────────────────────────────────

it('9. échelonne les refus autour du plancher', function (float $prix, bool $accepte) {
    zpSociete();
    $article = zpArticleAvecCout(6000);

    $reponse = $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), zpDevisPayload($article, $prix, 'envoye'));

    $accepte
        ? $reponse->assertSessionHasNoErrors()
        : $reponse->assertSessionHasErrors('items.0.unit_price');
})->with([
    'zéro — gratuité'      => [0, false],
    'un franc'             => [1, false],
    'juste sous plancher'  => [5999, false],
    'au plancher'          => [6000, true],
    'au-dessus'            => [6001, true],
]);

// ── Plancher : une seule définition ─────────────────────────────────────────

it('10. retient le plus contraignant des deux planchers', function (float $cout, float $configure, float $attendu) {
    zpSociete();
    $article = zpArticleAvecCout($cout, $configure);

    expect(app(SalesPriceFloorService::class)->minimumPrice($article))->toBe($attendu);
})->with([
    'coût seul'                  => [6000, 0, 6000.0],
    'plancher configuré seul'    => [0, 8000, 8000.0],
    'configuré au-dessus du coût'=> [6000, 8000, 8000.0],
    'coût au-dessus du configuré'=> [9000, 8000, 9000.0],
    'aucun des deux'             => [0, 0, 0.0],
]);

it('11. ne laisse pas l’API et la garde diverger', function (float $cout, float $configure) {
    // Le test qui empêche le retour de [BUG-A3-SALES-PRICE-API-028] : deux
    // définitions concurrentes du même seuil, dont la plus permissive
    // s'affichait à l'écran.
    zpSociete();
    $article = zpArticleAvecCout($cout, $configure);
    $client = Client::factory()->create(['is_active' => true]);

    $api = app(SalesPricingService::class)->resolve($client, $article, null, 10);
    $garde = app(SalesPriceGuardService::class)->explain($article);

    expect((float) $api['floor'])->toBe((float) $garde['minimum_price']);
    expect((float) $api['minimum_price'])->toBe((float) $garde['minimum_price']);
})->with([
    'coût seul'               => [6000, 0],
    'plancher configuré seul' => [0, 8000],
    'les deux'                => [6000, 8000],
    'aucun coût'              => [0, 0],
]);

it('12. annonce le plancher économique dans l’API, prix catalogue nul', function () {
    zpSociete();
    $article = zpArticleAvecCout(6000);
    $client = Client::factory()->create(['is_active' => true]);

    $api = app(SalesPricingService::class)->resolve($client, $article, null, 10);

    expect((float) $api['price'])->toBe(0.0);
    expect((float) $api['floor'])->toBe(6000.0);
    expect($api['below_floor'])->toBeTrue();

    // Les deux drapeaux ne disent pas la même chose et s'excluent :
    //   requires_manual_price  aucun prix n'existe — il faut le SAISIR
    //   requires_validation    un prix existe mais heurte une règle — il faut
    //                          le FAIRE APPROUVER
    // Réclamer l'approbation d'un prix qui n'a pas encore été donné n'a pas de
    // sens : l'interface enverrait le commercial chercher un visa sur du vide.
    // Le refus, lui, reste porté par la garde 1 côté document — l'API décrit,
    // elle n'autorise pas.
    expect($api['requires_manual_price'])->toBeTrue();
    expect($api['requires_validation'])->toBeFalse();
    expect($api['price_configured'])->toBeFalse();
    expect($api['cost_source'])->toBe('cmp_produit_fini');
});

it('13. n’exige ni validation ni saisie quand le prix couvre le plancher', function () {
    zpSociete();
    $article = zpArticleAvecCout(6000);
    $article->update(['sale_price' => 7000]);
    $client = Client::factory()->create(['is_active' => true]);

    $api = app(SalesPricingService::class)->resolve($client, $article->fresh(), null, 10);

    expect((float) $api['price'])->toBe(7000.0);
    expect($api['below_floor'])->toBeFalse();
    expect($api['requires_validation'])->toBeFalse();
    expect($api['requires_manual_price'])->toBeFalse();
    expect($api['price_configured'])->toBeTrue();
});

it('14. signale un prix à saisir quand aucun coût ne donne de plancher', function () {
    // Sans coût, le plancher vaut zéro : l'API ne doit pas pour autant présenter
    // « 0 FCFA » comme une proposition commerciale.
    zpSociete();
    $article = zpArticleSansCout();
    $client = Client::factory()->create(['is_active' => true]);

    $api = app(SalesPricingService::class)->resolve($client, $article, null, 10);

    expect((float) $api['minimum_price'])->toBe(0.0);
    expect($api['cost_source'])->toBe('aucun_cout_connu');
    expect($api['requires_manual_price'])->toBeTrue();
});

it('15. ne propose jamais le plancher comme prix de vente', function () {
    // Un minimum n'est pas un tarif : le prix résolu reste nul, à charge du
    // commercial de le saisir.
    zpSociete();
    $article = zpArticleAvecCout(6000);
    $client = Client::factory()->create(['is_active' => true]);

    $api = app(SalesPricingService::class)->resolve($client, $article, null, 10);

    expect((float) $api['price'])->toBe(0.0);
    expect((float) $api['price'])->not->toBe((float) $api['minimum_price']);
});

// ── Quantité nulle — §21, constat sans correction ───────────────────────────

it('16. refuse déjà une quantité nulle', function () {
    // Documenté ici : la règle existe (`min:0.0001`), aucun lot n'est requis.
    zpSociete();

    $charge = zpDevisPayload(zpArticleAvecCout(6000), 7000, 'envoye');
    $charge['items'][0]['quantity'] = 0;

    $this->actingAs(zpUtilisateur())
        ->post(route('ventes.devis.store'), $charge)
        ->assertSessionHasErrors('items.0.quantity');
});

it('25. se regle sur les decimales de la devise, sans seuil arbitraire', function () {
    // Le franc CFA n'a pas de decimale : 0,4 F n'existe pas et vaut zero une
    // fois exprime dans la devise. Aucune tolerance en dur ne doit remplacer
    // cette regle, sous peine de recreer une definition parallele du zero.
    CommercialLinePriceRule::oublierDecimales();

    expect(CommercialLinePriceRule::decimalesMonetaires())->toBe(0);
    expect(CommercialLinePriceRule::estGratuit(0.4))->toBeTrue();
    expect(CommercialLinePriceRule::estGratuit(0))->toBeTrue();
    expect(CommercialLinePriceRule::estGratuit(1))->toBeFalse();

    // Garde de code : la constante proscrite ne doit pas revenir par la bande.
    $source = file_get_contents(app_path('Services/Sales/CommercialLinePriceRule.php'));
    $code = '';
    foreach (token_get_all($source) as $jeton) {
        if (is_array($jeton) && in_array($jeton[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($jeton) ? $jeton[1] : $jeton;
    }
    expect($code)->not->toContain('0.005');
});
