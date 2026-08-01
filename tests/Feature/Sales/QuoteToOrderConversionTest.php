<?php

/**
 * Conversion Devis → Commande.
 *
 * Point métier vérifié :
 *  - un devis validé (accepté) se convertit en commande ;
 *  - la commande conserve le client, les lignes, quantités, prix et TVA du devis ;
 *  - un même devis ne peut pas être converti deux fois (garde converted_to_order_id).
 *
 * [BUG-A3-SALES-CONV-002] Les deux cas d'origine couvraient les MONTANTS et les
 * LIGNES, jamais les TERMES CONTRACTUELS. Ils passaient donc au vert pendant que
 * la conversion perdait le mode de prix, les conditions de paiement, le tarif et
 * le dépôt. Les champs omis ne restaient pas vides : ils prenaient le DÉFAUT de
 * la colonne `orders`, ce qui est plus grave qu'une perte, car une valeur
 * plausible remplace la valeur négociée sans rien signaler.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qtocAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QTOC'], ['email' => 'qtoc@qtoc.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('convertit un devis accepté en commande en conservant client, lignes, quantités, prix et TVA', function () {
    $this->actingAs(qtocAdmin());
    $client  = Client::factory()->create();
    $product = Product::factory()->create();
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 QTOC'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $svc   = app(QuoteService::class);
    $quote = $svc->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article devis',
            'quantity' => 7, 'unit_price' => 1500, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // Devis : 7 × 1500 = 10 500 HT ; TVA 18% = 1 890 ; TTC = 12 390.
    expect((int) $quote->subtotal_ht)->toBe(10500);
    expect((int) $quote->total_tax)->toBe(1890);

    $svc->accept($quote);              // brouillon → accepte
    $order = $svc->convertToOrder($quote->fresh());

    // Rattachement + conservation des totaux financiers.
    expect((int) $order->client_id)->toBe((int) $client->id);
    expect((int) $order->quote_id)->toBe((int) $quote->id);
    expect((int) $order->subtotal_ht)->toBe(10500);
    expect((int) $order->total_tax)->toBe(1890);
    expect((int) $order->total_ttc)->toBe(12390);

    // Conservation ligne à ligne.
    expect($order->items)->toHaveCount(1);
    $line = $order->items->first();
    expect((int) $line->product_id)->toBe((int) $product->id);
    expect((float) $line->quantity)->toEqual(7.0);
    expect((int) $line->unit_price)->toBe(1500);
    expect((float) $line->tax_rate_value)->toEqual(18.0);
    expect((int) $line->line_tax)->toBe(1890);

    // Devis marqué converti + pointeur vers la commande.
    expect($quote->fresh()->status)->toBe('converti');
    expect((int) $quote->fresh()->converted_to_order_id)->toBe((int) $order->id);
});

it('interdit de convertir deux fois le même devis', function () {
    $this->actingAs(qtocAdmin());
    $svc   = app(QuoteService::class);
    $quote = $svc->create([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'description' => 'Ligne libre', 'quantity' => 1, 'unit_price' => 5000,
            'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]);
    $svc->accept($quote);
    $svc->convertToOrder($quote->fresh());

    // Seconde conversion refusée (déjà converti).
    expect(fn () => $svc->convertToOrder($quote->fresh()))
        ->toThrow(\RuntimeException::class);
});

// ═════════════════════════════════════════════════════════════════════════════
// [BUG-A3-SALES-CONV-002] Termes contractuels
//
// Constaté en exploitation sur DEV-2026-00005 → CMD-2026-006 :
//   price_mode    ht                → ttc
//   payment_terms immediate         → NULL
//   price_list    TARIF-TEST-VENTES → NULL
//   warehouse_id  DEPTBC            → delivery_warehouse_id NULL
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Devis dont CHAQUE terme transférable porte une valeur distinctive, choisie
 * différente du défaut de la colonne cible : un champ oublié ne peut donc pas
 * passer inaperçu derrière une coïncidence.
 */
function qtocDevisNegocie(): array
{
    $u = qtocAdmin();
    test()->actingAs($u);
    $co = Company::where('name', 'QTOC')->firstOrFail();
    app()->instance('current_company', $co);

    $depot = Warehouse::firstOrCreate(['code' => 'DEPTBC'], ['name' => 'Dépôt tôle bac', 'company_id' => $co->id, 'is_active' => true]);

    // `quotes.sales_rep_id` et `orders.sales_rep_id` référencent `users`, alors
    // que `clients.sales_rep_id` et `commissions.sales_rep_id` référencent
    // `sales_reps` : même nom de colonne, deux cibles. Anomalie de schéma
    // signalée à part ; ici on respecte la contrainte réelle.
    $rep = User::factory()->create(['company_id' => $co->id, 'name' => 'Représentant Conversion']);
    $unit = Unit::firstOrCreate(['name' => 'Mètre QTOC'], ['abbreviation' => 'mqtoc']);
    $tva = TaxRate::firstOrCreate(['name' => 'TVA 18 QTOC'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $devis = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'DEV-CONV-'.uniqid(), 'status' => 'valide',
        'issued_at' => now()->toDateString(), 'expires_at' => now()->addDays(30)->toDateString(),
        'currency_code' => 'XOF', 'exchange_rate' => 1,
        'subtotal_ht' => 200000, 'total_discount' => 0, 'total_tax' => 36000, 'total_ttc' => 236000,
        'reference' => 'REF-CLIENT-CONV', 'notes' => 'Termes négociés à conserver.',
        // Valeurs négociées — aucune ne coïncide avec le défaut de `orders`.
        'price_mode'            => 'ht',        // défaut commande : 'ttc'
        'net_prices'            => true,        // défaut commande : 0
        'default_tax_label'     => 'EXO',       // défaut commande : 'TVA 18%' — portée fiscale
        'priority'              => 'haute',     // défaut commande : 'normale'
        'payment_terms'         => 'immediate',
        'payment_method'        => 'especes',
        'price_list'            => 'TARIF-TEST-VENTES',
        'project_reference'     => 'PRJ-CONV-002',
        'delivery_address'      => 'Zone industrielle Kossodo, Ouagadougou',
        'delivery_location'     => 'Chantier Kossodo',
        'incoterm'              => 'DAP',
        'fiscal_representative' => 'Cabinet Sawadogo',
        'fiscal_regime'         => 'reel_normal',
        'warehouse_id'          => $depot->id,
        'sales_rep_id'          => $rep->id,
        'contact_id'            => null,
    ]);

    $devis->items()->create([
        'product_id' => Product::factory()->create(['production_mode' => 'mto'])->id,
        'description' => 'Tôle bac prélaquée', 'unit_id' => $unit->id,
        'quantity' => 50, 'nb_toles' => 10, 'metrage_par_tole' => 5,
        'unit_price' => 4000, 'discount_percent' => 0,
        'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        'line_total_ht' => 200000, 'line_tax' => 36000, 'line_total_ttc' => 236000, 'sort_order' => 0,
    ]);

    return ['devis' => $devis, 'depot' => $depot, 'rep' => $rep];
}

it('reporte sur la commande chaque terme négocié du devis', function () {
    ['devis' => $devis, 'depot' => $depot, 'rep' => $rep] = qtocDevisNegocie();

    $commande = app(QuoteService::class)->convertToOrder($devis->fresh())->fresh();

    $attendus = [
        'price_mode'            => 'ht',
        'default_tax_label'     => 'EXO',
        'priority'              => 'haute',
        'payment_terms'         => 'immediate',
        'payment_method'        => 'especes',
        'price_list'            => 'TARIF-TEST-VENTES',
        'project_reference'     => 'PRJ-CONV-002',
        'delivery_address'      => 'Zone industrielle Kossodo, Ouagadougou',
        'delivery_location'     => 'Chantier Kossodo',
        'incoterm'              => 'DAP',
        'fiscal_representative' => 'Cabinet Sawadogo',
        'fiscal_regime'         => 'reel_normal',
    ];

    // Rapport groupé : la liste complète des pertes vaut mieux qu'un échec sur le
    // premier champ, qui masquerait les onze autres.
    $ecarts = [];
    foreach ($attendus as $champ => $attendu) {
        $obtenu = $commande->{$champ};
        if ((string) $obtenu !== (string) $attendu) {
            $ecarts[$champ] = sprintf('attendu « %s », obtenu « %s »', $attendu, $obtenu ?? 'NULL');
        }
    }
    expect($ecarts)->toBe([]);

    expect((bool) $commande->net_prices)->toBeTrue();
    expect((int) $commande->sales_rep_id)->toBe((int) $rep->id);
    expect((int) $commande->delivery_warehouse_id)->toBe((int) $depot->id);
});

it('ne laisse aucun défaut de colonne écraser une valeur du devis', function () {
    ['devis' => $devis] = qtocDevisNegocie();

    $commande = app(QuoteService::class)->convertToOrder($devis->fresh())->fresh();

    // Les défauts de `orders` qui produisent une valeur plausible et fausse.
    expect($commande->price_mode)->not->toBe('ttc');
    expect($commande->default_tax_label)->not->toBe('TVA 18%');
    expect($commande->priority)->not->toBe('normale');
});

it('conserve les trois quantités distinctes de la tôle bac', function () {
    ['devis' => $devis] = qtocDevisNegocie();

    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());
    $ligne = $commande->fresh()->items->first();

    // Les fusionner rendrait le métrage irrécupérable côté production.
    expect((float) $ligne->quantity)->toBe(50.0)
        ->and((float) $ligne->nb_toles)->toBe(10.0)
        ->and((float) $ligne->metrage_par_tole)->toBe(5.0);
});
