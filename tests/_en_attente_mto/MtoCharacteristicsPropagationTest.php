<?php

/**
 * [BUG-A3-MTO-TECH-010] Les caractéristiques demandées par le client suivent
 * la chaîne devis → commande → ordre de fabrication.
 *
 * `quote_items` et `order_items` ne portaient que `nb_toles` et
 * `metrage_par_tole`. Couleur, épaisseur, profil, nombre d'ondes, largeur,
 * matière, revêtement et tolérances n'existaient que sur `production_orders`,
 * où l'OF les héritait de la NOMENCLATURE — donc de ce que l'atelier sait
 * faire, pas de ce qui a été vendu.
 *
 * Deux commandes du même article en deux couleurs produisaient exactement le
 * même OF. La seule trace de la couleur voulue était la désignation en texte
 * libre, et rien ne garantit qu'un libellé « beige 27/100 » concorde avec les
 * valeurs structurées.
 *
 * [BUG-A3-MTO-ALLOC-011] L'OF vise désormais la LIGNE de commande. Sur une
 * commande portant deux fois le même article dans deux couleurs, `order_id`
 * seul ne dit pas laquelle est produite.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\QuoteService;
use App\Support\MtoCharacteristics;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/** Caractéristiques distinctives : aucune ne peut venir d'un défaut d'article. */
const MTC_DEMANDE = [
    'sheet_type'          => 'Acier prélaqué',
    'color'               => 'Beige',
    'couleur_ral'         => 'RAL 1015',
    'revetement'          => 'Polyester 25µ',
    'profil'              => 'Bac 4 ondes',
    'nb_ondes'            => 4,
    'thickness'           => 0.270,
    'usable_width'        => 1000.0,
    'largeur_totale'      => 1200.0,
    'tolerance_longueur'  => 5.000,
    'tolerance_epaisseur'  => 0.020,
];

function mtcSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MTC Co'], ['email' => 'mtc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

/** Tôle bac MTO, fabricable, avec nomenclature active portant d'AUTRES valeurs. */
function mtcArticle(): Product
{
    $cat = ItemCategory::firstOrCreate(['code' => 'PF_TOLE_MTO_C'], [
        'name' => 'Tôles MTO', 'strategy' => 'mto',
        'is_sellable' => true, 'is_stockable' => true, 'is_manufactured' => true,
    ]);
    $p = Product::factory()->create([
        'production_mode' => 'mto', 'item_category_id' => $cat->id,
        'is_sellable' => true, 'is_manufacturable' => true, 'is_active' => true,
        'sale_price' => 4000,
    ]);

    // La nomenclature porte des valeurs DIFFÉRENTES de la demande client : si
    // l'OF les reprenait, le test le verrait immédiatement.
    BillOfMaterial::firstOrCreate(
        ['product_id' => $p->id, 'name' => 'BOM MTC'],
        [
            'company_id' => mtcSociete()->id, 'is_active' => true,
            'sheet_type' => 'Acier galvanisé', 'thickness' => 0.400, 'usable_width' => 900.0,
        ]
    );

    return $p;
}

function mtcDevis(Product $produit, array $caracs = MTC_DEMANDE): Quote
{
    $unit = Unit::firstOrCreate(['name' => 'ML MTC'], ['abbreviation' => 'mlc']);
    $tva  = TaxRate::firstOrCreate(['name' => 'TVA 18 MTC'], ['short_name' => 'TVA18C', 'rate' => 18, 'is_active' => true]);

    return app(QuoteService::class)->create([
        'client_id' => Client::factory()->create(['is_active' => true, 'payment_mode' => Client::PAYMENT_CASH])->id,
        'issued_at' => now()->toDateString(),
        'items'     => [array_merge([
            'product_id' => $produit->id, 'description' => 'Tôle bac prélaquée',
            'nb_toles' => 10, 'metrage_par_tole' => 5, 'quantity' => 1,
            'unit_price' => 4000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ], $caracs)],
    ]);
}

it('conserve les caractéristiques demandées sur la ligne de devis', function () {
    mtcSociete();
    $devis = mtcDevis(mtcArticle());
    $ligne = $devis->items->first();

    $ecarts = [];
    foreach (MTC_DEMANDE as $champ => $attendu) {
        if ((string) $ligne->{$champ} !== (string) $attendu) {
            $ecarts[$champ] = sprintf('attendu « %s », obtenu « %s »', $attendu, $ligne->{$champ} ?? 'NULL');
        }
    }
    expect($ecarts)->toBe([]);

    // Le triplet tôle bac reste décomposé : 10 × 5 = 50, et les trois valeurs
    // restent lisibles séparément.
    expect((float) $ligne->nb_toles)->toBe(10.0)
        ->and((float) $ligne->metrage_par_tole)->toBe(5.0)
        ->and((float) $ligne->quantity)->toBe(50.0);
});

it('reporte les caractéristiques du devis sur la commande', function () {
    mtcSociete();
    $devis = mtcDevis(mtcArticle());
    $devis->update(['status' => 'valide']);

    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());
    $ligne = $commande->fresh()->items->first();

    $ecarts = [];
    foreach (MTC_DEMANDE as $champ => $attendu) {
        if ((string) $ligne->{$champ} !== (string) $attendu) {
            $ecarts[$champ] = sprintf('attendu « %s », obtenu « %s »', $attendu, $ligne->{$champ} ?? 'NULL');
        }
    }
    expect($ecarts)->toBe([]);
});

it('fait hériter l\'OF de la commande, pas de la nomenclature', function () {
    mtcSociete();
    $produit = mtcArticle();
    $devis = mtcDevis($produit);
    $devis->update(['status' => 'valide']);
    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());

    event(new \App\Events\OrderConfirmed($commande->fresh()));

    $of = ProductionOrder::where('order_id', $commande->id)->first();
    expect($of)->not->toBeNull();

    // La nomenclature porte « Acier galvanisé / 0,400 / 900 ». La commande
    // demande « Acier prélaqué / 0,270 / 1000 ». C'est la commande qui gagne.
    expect($of->sheet_type)->toBe('Acier prélaqué');
    expect((float) $of->thickness)->toBe(0.27);
    expect((float) $of->usable_width)->toBe(1000.0);
    expect($of->color)->toBe('Beige');
    expect($of->profil)->toBe('Bac 4 ondes');
    expect((int) $of->nb_ondes)->toBe(4);
    expect($of->revetement)->toBe('Polyester 25µ');
});

it('rattache l\'OF à la LIGNE de commande, pas seulement à la commande', function () {
    mtcSociete();
    $produit = mtcArticle();
    $devis = mtcDevis($produit);
    $devis->update(['status' => 'valide']);
    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());

    event(new \App\Events\OrderConfirmed($commande->fresh()));

    $of = ProductionOrder::where('order_id', $commande->id)->first();
    expect($of->order_item_id)->toBe($commande->fresh()->items->first()->id);
});

it('retombe sur la nomenclature seulement pour ce que la vente n\'a pas précisé', function () {
    mtcSociete();
    $produit = mtcArticle();

    // La vente ne précise ni la matière, ni l'épaisseur, ni la largeur.
    $partiel = MTC_DEMANDE;
    unset($partiel['sheet_type'], $partiel['thickness'], $partiel['usable_width']);

    $devis = mtcDevis($produit, $partiel);
    $devis->update(['status' => 'valide']);
    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());

    event(new \App\Events\OrderConfirmed($commande->fresh()));
    $of = ProductionOrder::where('order_id', $commande->id)->first();

    // Repli sur la nomenclature pour les trois champs absents...
    expect($of->sheet_type)->toBe('Acier galvanisé');
    expect((float) $of->thickness)->toBe(0.4);
    expect((float) $of->usable_width)->toBe(900.0);

    // ...et la demande client conservée pour le reste.
    expect($of->color)->toBe('Beige');
    expect($of->profil)->toBe('Bac 4 ondes');
});

it('déclare la liste des caractéristiques en un seul endroit', function () {
    // Garde d'architecture : la liste est recopiée sur trois tables. Écrite
    // trois fois dans le code, elle finirait par diverger d'un champ — et le
    // champ perdu serait silencieux, comme les conditions de paiement à la
    // conversion (BUG-A3-SALES-CONV-002).
    foreach (MtoCharacteristics::CHAMPS as $champ) {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('quote_items', $champ))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('order_items', $champ))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('production_orders', $champ))->toBeTrue();
    }
});
