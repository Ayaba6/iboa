<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\SalesInsightsService;

uses(\Tests\Concerns\RefreshDatabase::class);

it('isole les indicateurs ventes par société et compte les statuts réels', function () {
    $fiscalYear = FiscalYear::create(['label' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    $companyA = Company::create(['name' => 'Société A', 'email' => 'a@iboa.test']);
    $companyB = Company::create(['name' => 'Société B', 'email' => 'b@iboa.test']);

    app()->instance('current_company', $companyA);

    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();

    Invoice::create([
        'company_id' => $companyA->id,
        'client_id' => $clientA->id,
        'number' => 'FA-ISO-001',
        'type' => 'facture',
        'status' => 'emise',
        'issued_at' => now(),
        'subtotal_ht' => 100000,
        'total_ttc' => 118000,
        'remaining_amount' => 118000,
    ]);

    Invoice::create([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'number' => 'FB-ISO-001',
        'type' => 'facture',
        'status' => 'emise',
        'issued_at' => now(),
        'subtotal_ht' => 900000,
        'total_ttc' => 1062000,
        'remaining_amount' => 1062000,
    ]);

    Order::create([
        'company_id' => $companyA->id,
        'client_id' => $clientA->id,
        'fiscal_year_id' => $fiscalYear->id,
        'number' => 'CA-ISO-001',
        'status' => 'confirme',
        'issued_at' => now(),
    ]);

    Order::create([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'fiscal_year_id' => $fiscalYear->id,
        'number' => 'CB-ISO-001',
        'status' => 'confirme',
        'issued_at' => now(),
    ]);

    $kpis = app(SalesInsightsService::class)->dashboardKpis();

    expect($kpis['ca_month'])->toBe(100000)
        ->and($kpis['outstanding'])->toBe(118000)
        ->and($kpis['orders_in_progress'])->toBe(1);
});