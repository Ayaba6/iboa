<?php

/**
 * [CDC §9] Le seuil d'acompte requis avant lancement production est paramétrable
 * (SalesSetting.deposit_required_rate, défaut 70 %). Un même taux encaissé peut
 * bloquer ou passer selon le paramétrage.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesSetting;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Illuminate\Validation\ValidationException;

uses(\Tests\Concerns\RefreshDatabase::class);

function depCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DEP-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DEP Co'], ['email' => 'dep@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

/** OF lié à une commande « acompte » avec 60 % encaissé. */
function depOrderAt60(): ProductionOrder
{
    $co = depCompany();
    $client = Client::factory()->create(['payment_mode' => 'acompte']);
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-DEP-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100000,
    ]);
    ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'status' => 'confirme',
        'is_acompte' => true, 'amount' => 60000, 'unallocated_amount' => 60000,
        'payment_date' => now(), 'number' => 'ENC-DEP-' . uniqid(),
    ]);

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-DEP-' . uniqid(), 'status' => 'brouillon', 'quantity_requested' => 10,
        'product_id' => Product::factory()->create()->id, 'order_id' => $order->id,
    ]);
}

it('bloque la production à 60 % encaissé quand le seuil est 70 % (défaut)', function () {
    $of = depOrderAt60();
    expect(SalesSetting::current()->deposit_required_rate)->toEqual(70);

    expect(fn () => app(ProductionService::class)->checkFinancialGate($of))
        ->toThrow(ValidationException::class);
});

it('laisse passer la production à 60 % encaissé quand le seuil est abaissé à 50 %', function () {
    $of = depOrderAt60();
    SalesSetting::current()->update(['deposit_required_rate' => 50]);

    app(ProductionService::class)->checkFinancialGate($of); // ne doit pas lever
    expect(true)->toBeTrue();
});
