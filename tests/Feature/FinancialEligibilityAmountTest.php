<?php

/**
 * [GO conditionnel — correction 1] Éligibilité financière au MONTANT réellement
 * encaissé (et non à la simple existence d'un BP). Méthode centrale partagée
 * entre le tableau coordinateur et la gate financière de lancement OF.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesSetting;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
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
function feOrder(Company $co, string $paymentMode): Order
{
    $client = Client::factory()->create(['payment_mode' => $paymentMode]);
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

it('scénario du rapport : BP de 50 000 sur 500 000 requis (acompte 50 %) → NON éligible', function () {
    $co = feCompany();
    SalesSetting::current()->update(['deposit_required_rate' => 50]);
    $order = feOrder($co, 'acompte');
    feBp($order, 50000);

    expect($order->fresh()->requiredBeforeProduction())->toBe(500000)
        ->and($order->fresh()->confirmedReceipts())->toBe(50000)
        ->and($order->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

it('acompte 50 % atteint (500 000 encaissés) → éligible', function () {
    $co = feCompany();
    SalesSetting::current()->update(['deposit_required_rate' => 50]);
    $order = feOrder($co, 'acompte');
    feBp($order, 500000);

    expect($order->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});

it('comptant : 100 % exigé — paiement partiel non éligible, intégral éligible', function () {
    $co = feCompany();
    $partial = feOrder($co, 'comptant');
    feBp($partial, 900000);
    $full = feOrder($co, 'comptant');
    feBp($full, 1000000);

    expect($partial->fresh()->requiredBeforeProduction())->toBe(1000000)
        ->and($partial->fresh()->isFinanciallyEligibleForProduction())->toBeFalse()
        ->and($full->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();
});

it('crédit : jamais éligible financièrement (chemin approbation gérant)', function () {
    $co = feCompany();
    $order = feOrder($co, 'credit');
    feBp($order, 1000000);

    expect($order->fresh()->requiredBeforeProduction())->toBeNull()
        ->and($order->fresh()->isFinanciallyEligibleForProduction())->toBeFalse();
});

it('la gate financière de lancement OF compte désormais le paiement caisse (BP)', function () {
    $co = feCompany();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    SalesSetting::current()->update(['deposit_required_rate' => 50]);
    $order = feOrder($co, 'acompte');
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-FE-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 250, 'product_id' => $order->items->first()->product_id, 'order_id' => $order->id,
    ]);

    // Sans encaissement : bloquée.
    expect(fn () => app(ProductionService::class)->checkFinancialGate($of->fresh()))
        ->toThrow(ValidationException::class);

    // Paiement caisse 50 % (BP) : la gate passe — même source que le tableau.
    feBp($order, 500000);
    app(ProductionService::class)->checkFinancialGate($of->fresh()); // ne lève pas
    expect(true)->toBeTrue();
});
