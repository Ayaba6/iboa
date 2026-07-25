<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\SalesFloorWaiver;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use App\Services\SalesFloorWaiverService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('exige une dérogation maker-checker et l’invalide après modification tarifaire', function () {
    $year = FiscalYear::firstOrCreate(['label' => 'WAIVER-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Waiver Co'], [
        'email' => 'waiver@iboa.test', 'current_fiscal_year_id' => $year->id,
    ]);
    app()->instance('current_company', $company);
    foreach (['sales.submit', 'sales_below_floor.request', 'sales_below_floor.approve'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $requester = User::factory()->create(['company_id' => $company->id]);
    $requester->givePermissionTo(['sales.submit', 'sales_below_floor.request', 'sales_below_floor.approve']);
    $approver = User::factory()->create(['company_id' => $company->id]);
    $approver->givePermissionTo('sales_below_floor.approve');
    $product = Product::factory()->create([
        'type' => 'simple', 'is_manufacturable' => true, 'cout_standard' => 100,
        'weighted_avg_cost' => 90, 'margin_rate_target' => 20, 'sale_price' => 80,
        'uv_to_us_coef' => 1,
    ]);
    $client = Client::factory()->create(['credit_limit' => 10_000_000]);

    $this->actingAs($requester);
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now(),
        'items' => [[
            'product_id' => $product->id, 'description' => $product->name,
            'quantity' => 2, 'unit_price' => 80, 'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]);
    expect(fn () => app(CommercialWorkflowService::class)->submit($order))->toThrow(RuntimeException::class, 'dérogation');

    $waiver = app(SalesFloorWaiverService::class)->request($order, $order->items->first(), 'Offre stratégique documentée pour ce client.');
    expect(fn () => app(SalesFloorWaiverService::class)->approve($waiver))->toThrow(RuntimeException::class, 'propre');

    $this->actingAs($approver);
    app(SalesFloorWaiverService::class)->approve($waiver, 'Accord financier exceptionnel');
    $this->actingAs($requester);
    app(CommercialWorkflowService::class)->submit($order->fresh());
    expect($order->fresh()->status)->toBe('en_attente_validation')
        ->and(SalesFloorWaiver::where('line_id', $order->items->first()->id)->where('status', 'approuvee')->count())->toBe(1);

    $order->update(['status' => 'brouillon']);
    $order->items()->first()->update(['quantity' => 3]);
    expect(fn () => app(CommercialWorkflowService::class)->submit($order->fresh()))->toThrow(RuntimeException::class, 'dérogation');
});
