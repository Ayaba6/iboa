<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Services\CustomerCreditExposureService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

function creditExposureContext(): array
{
    $fy = FiscalYear::create([
        'label' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::create([
        'name' => 'OA METAL CREDIT TEST', 'email' => 'credit@oa-metal.test',
        'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);
    $client = Client::factory()->create([
        'payment_mode' => 'credit',
        'credit_limit' => 150000,
    ]);

    return compact('fy', 'company', 'client');
}

function exposureOrder(array $ctx, string $number, int $amount, string $status = 'brouillon'): Order
{
    return Order::create([
        'company_id' => $ctx['company']->id,
        'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $ctx['client']->id,
        'number' => $number,
        'status' => $status,
        'issued_at' => now(),
        'subtotal_ht' => $amount,
        'total_ttc' => $amount,
        'invoiced_amount' => 0,
    ]);
}

it('bloque une nouvelle commande quand commandes ouvertes et nouvelle commande dépassent le plafond', function () {
    $ctx = creditExposureContext();
    exposureOrder($ctx, 'CMD-OUVERTE', 80000, 'confirme');
    $candidate = exposureOrder($ctx, 'CMD-NOUVELLE', 80000);

    expect(fn () => DB::transaction(
        fn () => app(CustomerCreditExposureService::class)->assertMaySubmit($candidate)
    ))->toThrow(RuntimeException::class, 'encours prévisionnel 160 000 FCFA supérieur au plafond 150 000 FCFA');
});

it('déduit uniquement les acomptes confirmés non affectés de l encours prévisionnel', function () {
    $ctx = creditExposureContext();
    exposureOrder($ctx, 'CMD-OUVERTE', 80000, 'confirme');
    $candidate = exposureOrder($ctx, 'CMD-NOUVELLE', 80000);

    DB::table('client_payments')->insert([
        'company_id' => $ctx['company']->id,
        'client_id' => $ctx['client']->id,
        'number' => 'ENC-ACOMPTE-001',
        'payment_date' => now()->toDateString(),
        'amount' => 20000,
        'allocated_amount' => 0,
        'unallocated_amount' => 20000,
        'is_acompte' => true,
        'status' => 'confirme',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exposure = DB::transaction(
        fn () => app(CustomerCreditExposureService::class)->assertMaySubmit($candidate)
    );

    expect($exposure['open_orders'])->toBe(80000)
        ->and($exposure['new_order'])->toBe(80000)
        ->and($exposure['deposits'])->toBe(20000)
        ->and($exposure['projected'])->toBe(140000);
});
