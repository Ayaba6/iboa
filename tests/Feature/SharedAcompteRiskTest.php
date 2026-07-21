<?php

/**
 * [QA §10 — RISQUE ACOMPTES LIBRES] Un même acompte libre client peut rendre
 * PLUSIEURS commandes financièrement éligibles simultanément (l'acompte n'est
 * ni affecté ni réservé atomiquement).
 *
 * ANOMALIE CLASSÉE ÉLEVÉE — comportement ACTUEL documenté ici, PAS corrigé
 * (règle métier à trancher parmi : ① affectation obligatoire de l'acompte à une
 * commande ; ② réservation atomique de l'acompte à la création de l'OF ;
 * ③ exclusion des acomptes libres de l'éligibilité tant que non affectés).
 *
 * Ce test échouera volontairement le jour où la règle sera implémentée —
 * il faudra alors le remplacer par le test de la règle choisie.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesSetting;

uses(\Tests\Concerns\RefreshDatabase::class);

it('ANOMALIE ÉLEVÉE documentée : un même acompte libre rend deux commandes éligibles simultanément', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'SA-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SA Co'], ['email' => 'sa@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    SalesSetting::current()->update(['deposit_required_rate' => 50]);

    $client = Client::factory()->create(['payment_mode' => 'acompte']);

    $mkOrder = function () use ($co, $client) {
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
    };

    $orderA = $mkOrder(); // requis : 500 000
    $orderB = $mkOrder(); // requis : 500 000

    // UN SEUL acompte libre de 500 000 pour le client.
    ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'status' => 'confirme',
        'is_acompte' => true, 'amount' => 500000, 'unallocated_amount' => 500000,
        'payment_date' => now(), 'number' => 'ENC-SA-' . uniqid(),
    ]);

    // Comportement ACTUEL : les DEUX commandes sont éligibles avec le même argent.
    // C'est le risque documenté — 1 000 000 de production autorisée pour 500 000 encaissés.
    expect($orderA->fresh()->isFinanciallyEligibleForProduction())->toBeTrue()
        ->and($orderB->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});
