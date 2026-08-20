<?php

/**
 * [Données de test] Trois clients pour éprouver les modes de traitement d'une commande.
 *
 * Ce fichier ne teste QUE ce que le logiciel sait arbitrer aujourd'hui.
 *
 *   CRÉDIT   — 10 scénarios réels, portés par CustomerCreditExposureService.
 *   COMPTANT — BLOQUÉ : aucune règle ne compare le paiement encaissé au TTC.
 *   ACOMPTE  — BLOQUÉ : aucune colonne ne porte un pourcentage d'acompte minimum.
 *
 * Les deux derniers font l'objet de tests qui CONSTATENT l'absence de règle, au
 * lieu de faire semblant de la vérifier. Un test vert qui n'éprouve rien est pire
 * qu'un test absent : il éteint la vigilance. Le jour où la garde sera écrite,
 * ces tests échoueront — et c'est exactement ce qu'on veut d'eux.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Services\CustomerCreditExposureService;
use App\Services\TestData\OrderTestClientsSpec as Spec;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

function otcSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    return Company::firstOrCreate(['name' => 'OTC'], ['email' => 'otc@otc.io', 'current_fiscal_year_id' => $fy->id]);
}

function otcClientCredit(int $plafond = 10_000_000): Client
{
    otcSociete();

    return Client::factory()->create([
        'code' => 'CLT-TEST-CREDIT', 'name' => 'Client Test Crédit SARL',
        'payment_mode' => 'credit', 'credit_limit' => $plafond,
        'is_active' => true, 'is_blocked' => false,
        'notes' => 'DONNÉE DE TEST — lot '.Spec::BATCH,
    ]);
}

function otcEncours(Client $c, int $nouvelleCommande, ?int $plafond = null): array
{
    return app(CustomerCreditExposureService::class)->compute(
        companyId: otcSociete()->id,
        clientId: $c->id,
        limit: $plafond ?? (int) $c->credit_limit,
        isCredit: true,
        newOrderAmount: $nouvelleCommande,
    );
}

// ─── Données ─────────────────────────────────────────────────────────────────

it('donne trois codes clients distincts', function () {
    $codes = array_keys(Spec::clients());

    expect($codes)->toHaveCount(3);
    expect(array_unique($codes))->toHaveCount(3);
});

it('déclare explicitement quels clients ne sont pas testables', function () {
    $spec = Spec::clients();

    // Le blocage doit porter une CAUSE : « non testable » sans raison ne se
    // relit pas dans six mois.
    foreach ($spec as $code => $def) {
        if ($def['testable']) {
            expect($def['blocage'])->toBeNull();

            continue;
        }
        expect($def['blocage'])->toBeString();
        expect(strlen($def['blocage']))->toBeGreaterThan(30);
    }

    expect(collect($spec)->where('testable', true)->keys()->all())->toBe(['CLT-TEST-CREDIT']);
});

// ─── Crédit — scénarios §6 ───────────────────────────────────────────────────

it('juge éligible une commande sous le plafond, encours nul', function () {
    $c = otcClientCredit();
    $e = otcEncours($c, 2_000_000);

    expect($e['projected'])->toBe(2_000_000);
    expect($e['projected'] > $e['limit'])->toBeFalse();
});

it('bloque une commande qui dépasse le plafond', function () {
    $c = otcClientCredit();
    $e = otcEncours($c, 11_000_000);

    expect($e['projected'])->toBe(11_000_000);
    expect($e['projected'] > $e['limit'])->toBeTrue();
});

it('accepte une commande portant l\'encours au plafond EXACT', function () {
    $c = otcClientCredit();
    $e = otcEncours($c, 10_000_000);

    // Le plafond est une limite atteignable, pas une borne à ne pas toucher :
    // `>` et non `>=`. La distinction décide du sort d'une commande au franc près.
    expect($e['projected'])->toBe($e['limit']);
    expect($e['projected'] > $e['limit'])->toBeFalse();
});

it('cumule les commandes ouvertes dans l\'encours prévisionnel', function () {
    $co = otcSociete();
    $c = otcClientCredit();

    Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $c->id, 'number' => 'CMD-OTC-1', 'status' => 'confirme',
        'issued_at' => now(), 'total_ttc' => 8_000_000, 'invoiced_amount' => 0,
    ]);

    // 8 M engagés + 3 M nouveaux = 11 M : c'est le CUMUL qui dépasse, pas la
    // commande seule. Sans ce test, le plafond ne protégerait que des commandes
    // isolées — le cas qui ne se produit jamais en exploitation.
    $e = otcEncours($c, 3_000_000);

    expect($e['open_orders'])->toBe(8_000_000);
    expect($e['projected'])->toBe(11_000_000);
    expect($e['projected'] > $e['limit'])->toBeTrue();
});

it('ne libère pas la ligne de crédit sur un règlement NON confirmé', function () {
    $co = otcSociete();
    $c = otcClientCredit();

    DB::table('client_payments')->insert([
        'company_id' => $co->id, 'client_id' => $c->id,
        'number' => 'PAY-OTC-1', 'amount' => 5_000_000,
        // `en_attente` et non `brouillon` : l'ENUM de `client_payments` vaut
        // ('en_attente','confirme','rejete','annule'). SQLite acceptait
        // `brouillon` sans broncher — le test passait en éprouvant un état
        // impossible. MySQL le rejette, et c'est lui qui fait foi.
        'status' => 'en_attente', 'is_acompte' => 1,
        'unallocated_amount' => 5_000_000, 'allocated_amount' => 0,
        'payment_date' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $e = otcEncours($c, 2_000_000);

    // Un encaissement non confirmé n'est pas de l'argent : il ne doit rien
    // libérer. C'est la porte d'entrée classique du dépassement frauduleux.
    expect($e['deposits'])->toBe(0);
    expect($e['projected'])->toBe(2_000_000);
});

it('déduit un acompte CONFIRMÉ, et pour sa seule part non affectée', function () {
    $co = otcSociete();
    $c = otcClientCredit();

    DB::table('client_payments')->insert([
        'company_id' => $co->id, 'client_id' => $c->id,
        'number' => 'PAY-OTC-2', 'amount' => 5_000_000,
        'status' => 'confirme', 'is_acompte' => 1,
        // 3 M déjà imputés sur des factures : seuls 2 M restent affectables.
        'allocated_amount' => 3_000_000, 'unallocated_amount' => 2_000_000,
        'payment_date' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $e = otcEncours($c, 5_000_000);

    // Compter les 5 M entiers reviendrait à déduire deux fois les 3 M déjà
    // portés en diminution du reste dû des factures.
    expect($e['deposits'])->toBe(2_000_000);
    expect($e['projected'])->toBe(3_000_000);
});

it('porte l\'encours des factures non soldées', function () {
    $co = otcSociete();
    $c = otcClientCredit();

    DB::table('invoices')->insert([
        'company_id' => $co->id, 'client_id' => $c->id,
        'number' => 'FA-OTC-1', 'type' => 'facture', 'status' => 'emise',
        'issued_at' => now(), 'total_ttc' => 4_000_000,
        'remaining_amount' => 4_000_000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $e = otcEncours($c, 1_000_000);

    expect($e['outstanding'])->toBe(4_000_000);
    expect($e['projected'])->toBe(5_000_000);
});

it('ne limite rien quand le client n\'a pas de plafond', function () {
    $c = otcClientCredit(plafond: 0);
    $e = otcEncours($c, 999_000_000);

    // Plafond nul = pas de ligne de crédit définie, donc aucun contrôle — et
    // non « plafond zéro, tout est bloqué ». L'inverse ferait échouer toute
    // commande d'un client dont le plafond n'a pas encore été fixé.
    expect($e['limited'])->toBeFalse();
});

// ─── Comptant et acompte — absence de règle, constatée ───────────────────────

it('CONSTATE qu\'aucun champ ne porte un pourcentage d\'acompte minimum', function () {
    $colonnes = \Illuminate\Support\Facades\Schema::getColumnListing('clients');

    // Ce test échouera le jour où la colonne sera ajoutée. C'est voulu : il
    // signalera que les scénarios acompte deviennent enfin testables.
    expect(preg_grep('/acompte|deposit.*percent|percent.*deposit/i', $colonnes))->toBe([]);
});

it('CONSTATE qu\'un paiement partiel ne bloque pas la préparation au comptant', function () {
    // La validation de `registerPayment()` n'impose qu'un minimum de 1 franc :
    // aucune comparaison au TTC de la commande. Tant que cette règle est celle
    // du logiciel, les sept scénarios comptant restent hors d'atteinte.
    $regles = (new ReflectionClass(\App\Http\Controllers\Sales\OrderController::class))
        ->getFileName();
    $source = file_get_contents($regles);

    expect($source)->toContain("'payment_amount'    => ['required', 'integer', 'min:1']");
    expect($source)->not->toContain('total_ttc, montant insuffisant');
});
