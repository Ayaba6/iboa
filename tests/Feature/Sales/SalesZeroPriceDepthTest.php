<?php

/**
 * Garde du prix nul — profondeur et cohérence de calcul.
 *
 * `SalesZeroPriceGuardTest` couvre les chemins nominaux : saisie, soumission,
 * validation, plancher, API. Ce fichier traite ce qui les entoure et qui, sans
 * lui, laisserait la règle contournable :
 *
 *   — le NET, quand la gratuité vient d'une remise globale et non du prix ;
 *   — la CONVERSION, quand le devis a été corrompu hors des gardes ;
 *   — la NON-DIVERGENCE entre la formule de la règle et celle du moteur ;
 *   — le circuit de dérogation, qui doit survivre à la correction.
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
use App\Services\QuoteService;
use App\Services\SalesFloorWaiverService;
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


function zdSociete(): Company
{
    $exercice = FiscalYear::firstOrCreate(
        ['label' => 'ZD-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $societe = Company::firstOrCreate(
        ['name' => 'ZD Co'],
        ['email' => 'zd@iboa.test', 'current_fiscal_year_id' => $exercice->id],
    );
    app()->instance('current_company', $societe);
    Warehouse::firstOrCreate(
        ['code' => 'WZD'],
        ['name' => 'Dépôt ZD', 'company_id' => $societe->id, 'is_active' => true, 'is_default' => true],
    );

    return $societe;
}

function zdUtilisateur(array $permissions = []): User
{
    $u = User::factory()->create(['company_id' => zdSociete()->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    foreach ($permissions as $nom) {
        $u->givePermissionTo(Permission::firstOrCreate(['name' => $nom, 'guard_name' => 'web']));
    }

    return $u;
}

function zdArticle(float $cout = 6000): Product
{
    return Product::factory()->create([
        'is_sellable' => true, 'is_active' => true, 'is_manufacturable' => true,
        'production_mode' => 'mto',
        'sale_price' => 0, 'min_sale_price' => 0, 'max_sale_price' => null,
        'weighted_avg_cost' => $cout, 'cout_standard' => 0,
        'last_purchase_price' => 0, 'purchase_price' => 0,
        'margin_rate_target' => 0,
    ]);
}

/** Article sans aucune source de coût : le plancher y vaut zéro. */
function zdArticleSansCout(): Product
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

function zdPayload(Product $article, float $prix, float $remise = 0, float $quantite = 10): array
{
    return [
        'client_id'    => Client::factory()->create(['is_active' => true])->id,
        'issued_at'    => now()->toDateString(),
        'expires_at'   => now()->addDays(30)->toDateString(),
        'warehouse_id' => Warehouse::where('code', 'WZD')->value('id'),
        'price_list'   => 'TARIF GENERAL',
        'items'        => [[
            'product_id'       => $article->id,
            'description'      => 'Ligne de profondeur',
            'quantity'         => $quantite,
            'unit_price'       => $prix,
            'discount_percent' => $remise,
            'tax_rate_value'   => 18,
        ]],
    ];
}

// ── Non-divergence : la règle et le moteur ─────────────────────────────────

it('calcule le net exactement comme le moteur de documents', function (float $prix, float $qte, float $remise) {
    // `QuoteService::syncItems()` pose
    // `line_total_ht = (int) round(qty × prix × (1 − remise/100))`.
    // Si la règle s'en écartait, un montant refusé à la saisie pourrait être
    // accepté au document, ou l'inverse.
    zdSociete();

    $this->actingAs(zdUtilisateur())
        ->post(route('ventes.devis.store'), zdPayload(zdArticle(1), $prix, $remise, $qte))
        ->assertSessionHasNoErrors();

    $ligne = Quote::latest('id')->firstOrFail()->items()->first();

    expect((float) $ligne->line_total_ht)
        ->toBe(CommercialLinePriceRule::montantNetLigne($prix, (float) $ligne->quantity, $remise));
})->with([
    'sans remise'      => [7000, 10, 0],
    'remise de ligne'  => [7000, 10, 25],
    'remise à virgule' => [7333, 10, 33.33],
    'quantité unitaire'=> [7000, 1, 0],
]);

// ── La gratuité, quelle qu'en soit l'origine ───────────────────────────────

it('refuse un net nul obtenu par remise GLOBALE de 100 %', function () {
    // La remise globale ne touche ni `unit_price` ni `line_total_ht` : elle
    // s'applique au document. Seul le net, quote-part comprise, la révèle.
    zdSociete();
    $utilisateur = zdUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticle(1), 10000));

    $devis = Quote::latest('id')->firstOrFail();
    $devis->update(['global_discount_amount' => $devis->subtotal_ht]);

    $this->actingAs($utilisateur)->post(route('ventes.devis.submit', $devis->fresh()));

    expect($devis->fresh()->status)->toBe('brouillon');
});

it('refuse un net nul cumulant remise de ligne et remise globale', function () {
    zdSociete();
    $utilisateur = zdUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticle(1), 10000, 50));

    $devis = Quote::latest('id')->firstOrFail();
    $devis->update(['global_discount_amount' => $devis->subtotal_ht]);

    $this->actingAs($utilisateur)->post(route('ventes.devis.submit', $devis->fresh()));

    expect($devis->fresh()->status)->toBe('brouillon');
});

// ── Défense en profondeur à la conversion ──────────────────────────────────

it('refuse de convertir un devis VALIDÉ dont une ligne est à zéro', function () {
    // Corruption simulée : statut forcé à « valide » et ligne mise à zéro
    // directement en base, sans passer par aucune garde — ce que produiraient
    // un import, une donnée antérieure au lot, ou un chemin mal protégé.
    zdSociete();
    $utilisateur = zdUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticle(6000), 7000));

    $devis = Quote::latest('id')->firstOrFail();
    DB::table('quote_items')->where('quote_id', $devis->id)
        ->update(['unit_price' => 0, 'line_total_ht' => 0, 'line_total_ttc' => 0]);
    DB::table('quotes')->where('id', $devis->id)->update(['status' => 'valide']);

    expect(fn () => app(QuoteService::class)->convertToOrder($devis->fresh()))
        ->toThrow(\RuntimeException::class);

    expect(DB::table('orders')->where('quote_id', $devis->id)->count())->toBe(0);
});

it('convertit normalement un devis validé dont le prix est correct', function () {
    // Le pendant du précédent : la défense ne doit pas bloquer le cas sain.
    zdSociete();
    $utilisateur = zdUtilisateur();

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticle(6000), 7000));

    $devis = Quote::latest('id')->firstOrFail();
    DB::table('quotes')->where('id', $devis->id)->update(['status' => 'valide']);

    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());

    expect($commande->exists)->toBeTrue();
    expect((float) $commande->items()->first()->unit_price)->toBe(7000.0);
});

// ── Le circuit de dérogation survit à la correction ────────────────────────

it('laisse demander une dérogation pour une vente SOUS plancher', function () {
    // À 5 500 F sous un plancher de 6 000 F, le refus reste celui du plancher —
    // donc dérogeable. La correction P1 ne doit pas emporter la garde 2.
    zdSociete();
    $utilisateur = zdUtilisateur(['sales_below_floor.request', 'sales_below_floor.approve']);

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticle(6000), 5500));

    $devis = Quote::latest('id')->firstOrFail();

    $waiver = app(SalesFloorWaiverService::class)
        ->createDraft($devis, $devis->items()->first(), 'Négociation commerciale documentée avec le client');

    expect($waiver->status)->toBe('brouillon');
    expect((float) $waiver->minimum_price)->toBe(6000.0);
});

it('refuse de faire avancer un document dont une ligne est gratuite', function () {
    // Même détenteur des permissions de dérogation : à zéro, il n'y a pas de
    // dérogation à demander, et le document ne peut pas progresser.
    zdSociete();
    $utilisateur = zdUtilisateur(['sales_below_floor.request', 'sales_below_floor.approve']);

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), zdPayload(zdArticleSansCout(), 0));

    $devis = Quote::latest('id')->firstOrFail();

    expect(fn () => app(SalesFloorWaiverService::class)->assertDocumentMayProceed($devis))
        ->toThrow(\RuntimeException::class);
});

// ── Un prix positif reste valable sans coût ────────────────────────────────

it('accepte un prix positif quand aucun plancher n’existe', function () {
    // La correction interdit le zéro, pas les petits prix : sans coût ni
    // plancher configuré, rien ne justifie d'inventer un minimum.
    zdSociete();
    $article = zdArticleSansCout();

    expect(app(SalesPriceFloorService::class)->minimumPrice($article))->toBe(0.0);

    $charge = zdPayload($article, 1);
    $charge['status'] = 'envoye';

    $this->actingAs(zdUtilisateur())
        ->post(route('ventes.devis.store'), $charge)
        ->assertSessionHasNoErrors();
});

// ── Quantité — caractérisation, aucun défaut à corriger ────────────────────

it('refuse une quantité nulle ou négative', function (float $quantite) {
    // [BUG-A3-SALES-ZERO-QTY-030] non confirmé : `min:0.0001` couvre déjà les
    // deux cas. Consigné ici pour que la règle cesse de dépendre d'une lecture.
    zdSociete();

    $charge = zdPayload(zdArticle(6000), 7000, 0, $quantite);
    $charge['status'] = 'envoye';

    $this->actingAs(zdUtilisateur())
        ->post(route('ventes.devis.store'), $charge)
        ->assertSessionHasErrors('items.0.quantity');
})->with(['nulle' => [0], 'négative' => [-5]]);

// ── Precision monetaire : la devise decide, pas une constante ──────────────

it('traite le zero selon les decimales de CHAQUE devise', function () {
    // XOF n'a pas de decimale : 0,4 F n'existe pas et vaut zero. Une devise a
    // deux decimales, elle, connait le centime — 0,01 n'y est pas nul. Si la
    // regle etait codee pour le seul franc CFA, ce test le montrerait.
    zdSociete();
    CommercialLinePriceRule::oublierDecimales();

    \App\Models\Currency::firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro test', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true],
    );

    expect(CommercialLinePriceRule::decimalesMonetaires('XOF'))->toBe(0);
    expect(CommercialLinePriceRule::decimalesMonetaires('EUR'))->toBe(2);

    // Le meme montant, juge differemment selon la monnaie.
    expect(CommercialLinePriceRule::estGratuit(0.4, 'XOF'))->toBeTrue();
    expect(CommercialLinePriceRule::estGratuit(0.4, 'EUR'))->toBeFalse();
    expect(CommercialLinePriceRule::estGratuit(0.01, 'EUR'))->toBeFalse();
    expect(CommercialLinePriceRule::estGratuit(0.001, 'EUR'))->toBeTrue();

    // Une devise inconnue retombe sur la devise par defaut, jamais sur un
    // chiffre invente.
    expect(CommercialLinePriceRule::decimalesMonetaires('ZZZ'))
        ->toBe(CommercialLinePriceRule::decimalesMonetaires());
});

it('somme les montants nets exactement comme le total du devis', function () {
    // §8 : une seule repartition de la remise globale. Si la garde en avait une
    // seconde, la somme des nets de ligne cesserait d'egaler le total du
    // document.
    zdSociete();
    $utilisateur = zdUtilisateur();
    $article = zdArticle(1);

    $charge = zdPayload($article, 10000, 0, 1);
    $charge['items'][] = [
        'product_id' => zdArticle(1)->id, 'description' => 'Ligne B',
        'quantity' => 1, 'unit_price' => 30000, 'discount_percent' => 0,
        'tax_rate_value' => 18,
    ];

    $this->actingAs($utilisateur)->post(route('ventes.devis.store'), $charge);

    $devis = Quote::latest('id')->firstOrFail();
    $devis->update(['global_discount_amount' => 4000]);
    $devis->refresh();

    $ratio = (float) $devis->global_discount_amount / (float) $devis->subtotal_ht;

    $sommeNets = 0.0;
    foreach ($devis->items as $ligne) {
        $sommeNets += CommercialLinePriceRule::montantNetLigne(
            (float) $ligne->unit_price,
            (float) $ligne->quantity,
            (float) ($ligne->discount_percent ?? 0),
            $ratio,
            $devis->currency_code,
        );
    }

    $totalNetDocument = (float) $devis->subtotal_ht - (float) $devis->global_discount_amount;

    expect(round($sommeNets))->toBe(round($totalNetDocument));
});

// ── §2 : tous les chemins engageants, pas seulement le principal ──────────

it('refuse la CONFIRMATION DIRECTE d’une commande à prix nul', function () {
    // Le circuit direct brouillon → confirme n’échappait à aucune garde parce
    // qu’il n’en avait aucune. Il aboutit pourtant au même statut que la
    // validation et déclenche le même événement OrderConfirmed : réservation de
    // stock, ordre de fabrication automatique pour les articles MTO.
    zdSociete();
    $utilisateur = zdUtilisateur();
    $article = zdArticle(6000);
    $client = Client::factory()->create(['is_active' => true]);

    $this->actingAs($utilisateur);

    $commande = app(\App\Services\OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'currency_code' => 'XOF',
        'items' => [[
            'product_id' => $article->id,
            'description' => 'Ligne saine à la création',
            'quantity' => 2,
            'unit_price' => 12000,
            'discount_percent' => 0,
            'tax_rate_value' => 18,
        ]],
    ]);

    expect($commande->status)->toBe('brouillon');

    // La ligne devient gratuite APRÈS la création — exactement ce que le
    // contrôle de saisie ne peut pas voir.
    \App\Models\OrderItem::where('order_id', $commande->id)->update(['unit_price' => 0]);

    expect(fn () => app(\App\Services\OrderService::class)->confirm($commande->fresh()))
        ->toThrow(\RuntimeException::class);

    expect(\App\Models\Order::findOrFail($commande->id)->status)->toBe('brouillon');
});

it('refuse de VALIDER une facture directe à prix nul', function () {
    // Une facture directe ne descend d’aucun devis : aucune garde amont ne l’a
    // vue passer. C’était le chemin le plus court vers une vente à zéro.
    $societe = zdSociete();
    $utilisateur = zdUtilisateur();
    $article = zdArticle(6000);
    $client = Client::factory()->create(['is_active' => true]);

    $facture = \App\Models\Invoice::factory()->create([
        // Société explicite : la factory tirerait la sienne, et le scope
        // multi-société masquerait ensuite la facture au relecteur.
        'company_id' => $societe->id,
        'fiscal_year_id' => $societe->current_fiscal_year_id,
        'client_id' => $client->id,
        'status' => 'brouillon',
        'currency_code' => 'XOF',
        'subtotal_ht' => 0,
        'global_discount_amount' => 0,
    ]);

    \App\Models\InvoiceItem::create([
        'invoice_id' => $facture->id,
        'product_id' => $article->id,
        'description' => 'Ligne offerte',
        'quantity' => 3,
        'unit_price' => 0,
        'discount_percent' => 0,
        'tax_rate_value' => 18,
        'line_total_ht' => 0,
        'line_tax' => 0,
        'line_total_ttc' => 0,
    ]);

    $this->actingAs($utilisateur);

    expect(fn () => app(\App\Services\CommercialWorkflowService::class)
        ->validateInvoice($facture->fresh()))
        ->toThrow(\RuntimeException::class);

    expect(\App\Models\Invoice::findOrFail($facture->id)->status)->toBe('brouillon');
});

// ── §11 : le champ doit franchir le transport, pas seulement exister ──────

it('expose requires_manual_price sur l’endpoint HTTP consommé par les écrans', function () {
    // Les trois formulaires de vente lisent `d.requires_manual_price` sur cette
    // route. Un champ correct dans le service mais absent de la réponse laisse
    // l’écran afficher 0 sans explication — le défaut d’origine.
    zdSociete();
    $utilisateur = zdUtilisateur();
    $article = zdArticle(6000);   // coût connu, aucun prix de vente configuré
    $client = Client::factory()->create(['is_active' => true]);

    $reponse = $this->actingAs($utilisateur)->getJson(
        route('ventes.api.prix', ['product_id' => $article->id, 'client_id' => $client->id, 'qty' => 1])
    );

    $reponse->assertOk()
        ->assertJson([
            'requires_manual_price' => true,
            'price_configured' => false,
            // Pas d’approbation à demander sur un prix qui n’a pas été donné.
            'requires_validation' => false,
        ]);

    expect((float) $reponse->json('floor'))->toBe(6000.0);
});
