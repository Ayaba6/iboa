<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function createAuditedLotDelivery(): array
{
    $year = FiscalYear::firstOrCreate(['label' => 'AUDIT-LOTS-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $company = Company::firstOrCreate(['name' => 'Audit allocations'], ['email' => 'audit-allocations@iboa.test', 'current_fiscal_year_id' => $year->id]);
    app()->instance('current_company', $company);
    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);
    $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'AUD-PF', 'name' => 'Audit PF', 'type' => 'produit_fini', 'can_sale' => true, 'can_delivery' => true, 'can_stock' => true, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'weighted_avg_cost' => 125]);
    ProductStock::create(['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 125]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'AUD-LOT-1', 'quantity' => 5, 'unit_cost' => 125, 'received_at' => '2026-01-01', 'status' => 'disponible']);
    $delivery = DeliveryNote::create(['company_id' => $company->id, 'client_id' => Client::factory()->create()->id, 'number' => 'BL-AUDIT-LOTS', 'status' => 'brouillon', 'warehouse_id' => $warehouse->id, 'issued_at' => now()]);
    $item = $delivery->items()->create(['product_id' => $product->id, 'description' => 'Audit allocations', 'quantity' => 3, 'unit_price' => 500]);
    app(DeliveryNoteService::class)->validate($delivery);

    return [$delivery->fresh(), $item->fresh()];
}

it('certifie une livraison dont les allocations et mouvements sont cohérents', function () {
    createAuditedLotDelivery();

    expect(Artisan::call('a3:audit-delivery-allocations'))->toBe(0)
        ->and(Artisan::output())->toContain('Anomalies: 0');
});

it('échoue si le COGS figé ne correspond plus à quantité fois coût historique', function () {
    [, $item] = createAuditedLotDelivery();
    $item->lotAllocations()->firstOrFail()->update(['total_cost' => 1]);

    expect(Artisan::call('a3:audit-delivery-allocations'))->toBe(1)
        ->and(Artisan::output())->toContain('cost_mismatch');
});
