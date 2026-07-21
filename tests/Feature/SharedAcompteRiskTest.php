<?php

/**
 * [FIX anomalie ÉLEVÉE — acomptes libres partagés] Règle retenue (option ② de
 * l'arbitrage) : l'acompte libre est RÉSERVÉ par les commandes du client ayant
 * déjà un OF actif — la part ayant couvert leur exigence n'est plus disponible
 * pour rendre une commande sœur éligible.
 *
 * Remplace le test-sentinelle qui documentait l'anomalie (les deux commandes
 * éligibles avec le même argent).
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesSetting;
use App\Modules\Production\Models\ProductionOrder;

uses(\Tests\Concerns\RefreshDatabase::class);

function saCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SA-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SA Co'], ['email' => 'sa@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    SalesSetting::current()->update(['deposit_required_rate' => 50]);

    return $co;
}

function saOrder(Company $co, Client $client): Order
{
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-SA-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 1000000,
    ]);
    $p = Product::factory()->create(['production_mode' => 'mto']);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 250,
        'unit_price' => 4000, 'line_total_ht' => 1000000, 'line_tax' => 0, 'line_total_ttc' => 1000000, 'sort_order' => 0,
    ]);

    return $order;
}

function saAcompte(Company $co, Client $client, int $amount): void
{
    ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'status' => 'confirme',
        'is_acompte' => true, 'amount' => $amount, 'unallocated_amount' => $amount,
        'payment_date' => now(), 'number' => 'ENC-SA-' . uniqid(),
    ]);
}

it('un acompte libre déjà « consommé » par une commande avec OF actif ne rend plus la sœur éligible', function () {
    $co = saCompany();
    $client = Client::factory()->create(['payment_mode' => 'acompte']);
    $orderA = saOrder($co, $client); // requis 500 000
    $orderB = saOrder($co, $client); // requis 500 000
    saAcompte($co, $client, 500000); // UN SEUL acompte

    // Avant tout OF : les deux affichées éligibles (aucune réservation encore).
    expect($orderA->fresh()->isFinanciallyEligibleForProduction())->toBeTrue()
        ->and($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();

    // OF créé pour A → l'acompte est réservé par A : B n'est PLUS éligible.
    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SA-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 250, 'product_id' => $orderA->items->first()->product_id,
        'order_id' => $orderA->id,
    ]);

    expect($orderA->fresh()->isFinanciallyEligibleForProduction())->toBeTrue()
        ->and($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

it('un acompte suffisant pour les deux commandes les laisse toutes deux éligibles', function () {
    $co = saCompany();
    $client = Client::factory()->create(['payment_mode' => 'acompte']);
    $orderA = saOrder($co, $client);
    $orderB = saOrder($co, $client);
    saAcompte($co, $client, 1000000); // couvre 2 × 500 000

    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SA-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 250, 'product_id' => $orderA->items->first()->product_id,
        'order_id' => $orderA->id,
    ]);

    expect($orderA->fresh()->isFinanciallyEligibleForProduction())->toBeTrue()
        ->and($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});

it('un OF annulé libère la réservation d\'acompte de la sœur', function () {
    $co = saCompany();
    $client = Client::factory()->create(['payment_mode' => 'acompte']);
    $orderA = saOrder($co, $client);
    $orderB = saOrder($co, $client);
    saAcompte($co, $client, 500000);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SA-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 250, 'product_id' => $orderA->items->first()->product_id,
        'order_id' => $orderA->id,
    ]);
    expect($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();

    $of->update(['status' => 'annule']);
    expect($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});

it('le flux mono-commande à l\'acompte reste inchangé', function () {
    $co = saCompany();
    $client = Client::factory()->create(['payment_mode' => 'acompte']);
    $order = saOrder($co, $client);
    saAcompte($co, $client, 500000);

    expect($order->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});
