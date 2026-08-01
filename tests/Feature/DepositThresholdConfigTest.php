<?php

/**
 * [BUG-A3-SALES-DEPOSIT-004] Le seuil d'acompte exigible avant production n'est
 * PAS structuré — et ce fichier le constate au lieu de le simuler.
 *
 * Il affirmait auparavant : « le seuil d'acompte requis avant lancement
 * production est paramétrable (SalesSetting.deposit_required_rate, défaut 70 %),
 * un même taux encaissé peut bloquer ou passer selon le paramétrage ». Les deux
 * cas passaient au vert. Ils reposaient sur un client
 * `payment_mode => 'acompte'` — valeur qu'aucune fiche client ne peut porter :
 * les Form Requests valident `in:cash,credit`, la liste déroulante n'offre que
 * ces deux options, et la colonne était un ENUM('cash','credit') avant sa
 * relaxation en varchar pour la seule parité de tests. Zéro ligne de la base
 * porte 'acompte'.
 *
 * Le paramètre existe donc, mais il ne désigne AUCUN client. Quatre supports se
 * disputent la notion sans qu'aucun ne la porte de bout en bout :
 *   - `sales_settings.deposit_required_rate` : taux global, ne dit pas qui y est soumis ;
 *   - `payment_terms.deposit_required` / `deposit_rate` : structurés, mais
 *     `clients.payment_terms` est un varchar libre — aucune clé étrangère ;
 *   - `item_categories.deposit_required` : écrit par l'écran, lu par aucun code ;
 *   - `clients.condition_paiement` : texte libre (« 50 % à la commande, solde
 *     avant livraison »), inexploitable par une règle.
 *
 * Une chaîne libre ne doit pas piloter une règle financière critique : tant que
 * la source structurée n'existe pas, la garde refuse (fail-closed) plutôt que
 * d'inventer un seuil. Ces cas resteront tels quels jusqu'à la fermeture de
 * BUG-A3-SALES-DEPOSIT-004, où ils redeviendront des tests de comportement.
 */

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\SalesSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Tests\Concerns\RefreshDatabase::class);

it('n\'accepte aucun mode de règlement « acompte » par le chemin applicatif', function () {
    expect(Client::PAYMENT_MODES)->toBe(['cash', 'credit']);
    expect((new StoreClientRequest)->rules()['payment_mode'])->toContain('in:cash,credit');

    // `UpdateClientRequest::rules()` lit `$this->route('client')` pour composer
    // ses règles d'unicité : il lui faut un résolveur de route.
    $client = Client::factory()->create();
    $update = new UpdateClientRequest;
    $update->setRouteResolver(fn () => new class($client)
    {
        public function __construct(private $client) {}

        public function parameter($nom, $defaut = null)
        {
            return $this->client;
        }
    });
    expect($update->rules()['payment_mode'])->toContain('in:cash,credit');
});

it('ne relie aucun client à un taux d\'acompte structuré', function () {
    // Le support structuré existe...
    expect(Schema::hasColumn('payment_terms', 'deposit_required'))->toBeTrue();
    expect(Schema::hasColumn('payment_terms', 'deposit_rate'))->toBeTrue();

    // ...mais rien ne l'attache à un client : pas de clé étrangère, seulement un
    // libellé libre, et aucun pourcentage porté par la fiche client.
    expect(Schema::hasColumn('clients', 'payment_term_id'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'payment_terms_id'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'deposit_rate'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'deposit_required_rate'))->toBeFalse();
});

it('conserve un taux global qui ne désigne aucun client', function () {
    // `SalesSetting::current()` est rattaché à une société : il en faut une.
    $fy = \App\Models\FiscalYear::firstOrCreate(['label' => 'DEP-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = \App\Models\Company::firstOrCreate(['name' => 'DEP Co'], ['email' => 'dep@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    // Le paramètre reste défini — il n'est pas supprimé, il est seulement sans
    // sujet. Le retirer masquerait l'anomalie au lieu de la documenter.
    expect(SalesSetting::current()->deposit_required_rate)->not->toBeNull();

    $client = Client::factory()->create(['payment_mode' => Client::PAYMENT_CASH]);
    expect(DB::table('clients')->where('payment_mode', 'acompte')->count())->toBe(0);
    expect($client->fresh()->payment_mode)->toBe(Client::PAYMENT_CASH);
});
