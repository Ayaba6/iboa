<?php

/**
 * [GO conditionnel — correction 1] Éligibilité financière au MONTANT réellement
 * encaissé (et non à la simple existence d'un BP). Méthode centrale partagée
 * entre le tableau coordinateur et la gate financière de lancement OF.
 *
 * [BUG-A3-MTO-FIN-001] Ce fichier éprouvait auparavant des clients
 * `payment_mode => 'acompte'` et `'comptant'`. Ni l'une ni l'autre de ces
 * valeurs n'est saisissable : les Form Requests valident `in:cash,credit` et la
 * liste déroulante n'offre que deux options. La base ne contient que 'cash' et
 * 'credit'. Le cas nommé « comptant : 100 % exigé » — le seul qui couvrait
 * exactement le défaut survenu en exploitation — passait au vert sur un mode
 * inexistant pendant que le mode réel, 'cash', échappait à toute vérification.
 * Les scénarios sont donc rejoués sur les valeurs réelles.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Services\Production\ProductionFinancialRequirement;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function feCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'FE-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'FE Co'], ['email' => 'fe@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

/** Commande confirmée 1 000 000 TTC, client en mode donné, ligne MTO. */
function feOrder(Company $co, string $paymentMode, int $plafond = 0): Order
{
    $client = Client::factory()->create(['payment_mode' => $paymentMode, 'credit_limit' => $plafond]);
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-FE-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 1000000,
    ]);
    $p = Product::factory()->create(['production_mode' => 'mto', 'sale_price' => 4000]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 250,
        'unit_price' => 4000, 'line_total_ht' => 1000000, 'line_tax' => 0, 'line_total_ttc' => 1000000, 'sort_order' => 0,
    ]);

    return $order;
}

function feBp(Order $order, int $amount): BonPreparation
{
    return BonPreparation::create([
        'company_id' => $order->company_id, 'order_id' => $order->id,
        'number' => 'BP-FE-' . uniqid(), 'status' => 'en_attente', 'payment_amount' => $amount,
    ]);
}

it('scénario du rapport : BP de 50 000 sur 1 000 000 requis → NON éligible', function () {
    $co = feCompany();
    $order = feOrder($co, Client::PAYMENT_CASH);
    feBp($order, 50000);

    expect($order->fresh()->requiredBeforeProduction())->toBe(1000000)
        ->and($order->fresh()->confirmedReceipts())->toBe(50000)
        ->and($order->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

it('comptant : 100 % exigé — paiement partiel non éligible, intégral éligible', function () {
    $co = feCompany();
    $partial = feOrder($co, Client::PAYMENT_CASH);
    feBp($partial, 900000);
    $full = feOrder($co, Client::PAYMENT_CASH);
    feBp($full, 1000000);

    expect($partial->fresh()->requiredBeforeProduction())->toBe(1000000)
        ->and($partial->fresh()->isFinanciallyEligibleForProduction())->toBeFalse()
        ->and($full->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});

it('comptant : un franc manquant reste un manque', function () {
    $co = feCompany();
    $order = feOrder($co, Client::PAYMENT_CASH);
    feBp($order, 999999);

    expect($order->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

/**
 * CHANGEMENT DE RÈGLE MÉTIER — à valider explicitement.
 *
 * Ce cas affirmait auparavant : « crédit : jamais éligible financièrement ». La
 * consigne §2 de la correction impose désormais d'évaluer le crédit sur le
 * plafond, l'exposition courante et prévisionnelle, les impayés échus et les
 * dérogations. Un client à crédit dont l'encours prévisionnel reste sous son
 * plafond devient donc éligible sans approbation gérant préalable.
 *
 * Le plafond NUL reste un refus : il signifie « aucun crédit accordé », et non
 * « crédit illimité » — c'est le sens que lui donnerait `CustomerCreditExposureService`,
 * dont le `limited = false` sert l'affichage commercial, pas une garde de production.
 */
it('crédit : refusé sans plafond, autorisé sous plafond', function () {
    $co = feCompany();

    $sansPlafond = feOrder($co, Client::PAYMENT_CREDIT, plafond: 0);
    expect($sansPlafond->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();

    $sousPlafond = feOrder($co, Client::PAYMENT_CREDIT, plafond: 5_000_000);
    $exigence = $sousPlafond->fresh()->productionFinancialRequirement();
    expect($exigence->type)->toBe(ProductionFinancialRequirement::TYPE_CREDIT)
        ->and($exigence->satisfied)->toBeTrue();

    // Plafond inférieur au TTC : cette commande seule le fait dépasser.
    $auDessus = feOrder($co, Client::PAYMENT_CREDIT, plafond: 999_999);
    expect($auDessus->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

it('la gate financière de lancement OF compte le paiement caisse (BP)', function () {
    $co = feCompany();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $order = feOrder($co, Client::PAYMENT_CASH);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-FE-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 250, 'product_id' => $order->items->first()->product_id, 'order_id' => $order->id,
    ]);

    // Sans encaissement : bloquée.
    expect(fn () => app(ProductionService::class)->checkFinancialGate($of->fresh()))
        ->toThrow(ValidationException::class);

    // Paiement caisse intégral (BP) : la gate passe — même source que le tableau.
    feBp($order, 1000000);
    app(ProductionService::class)->checkFinancialGate($of->fresh()); // ne lève pas
    expect($order->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});
