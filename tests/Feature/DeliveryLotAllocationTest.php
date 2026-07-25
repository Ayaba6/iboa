<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('alloue une livraison sur plusieurs lots et fige le COGS historique', function () {
    $fiscalYear = FiscalYear::firstOrCreate(['label' => 'LOTS-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Lots COGS'], [
        'email' => 'lots-cogs@iboa.test', 'current_fiscal_year_id' => $fiscalYear->id,
    ]);
    app()->instance('current_company', $company);
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'code' => 'PF-LOTS', 'name' => 'PF Lots',
        'type' => 'produit_fini', 'can_sale' => true, 'can_delivery' => true,
        'can_stock' => true, 'is_active' => true,
    ]);
    $product = Product::factory()->create([
        'is_stockable' => true, 'has_lot_number' => true, 'valuation_method' => 'cmp',
        'weighted_avg_cost' => 220,
    ]);
    ProductStock::create([
        'company_id' => $company->id, 'product_id' => $product->id,
        'warehouse_id' => $warehouse->id, 'quantity' => 10, 'reserved_quantity' => 0, 'avg_cost' => 220,
    ]);
    $lotA = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-A', 'quantity' => 4, 'unit_cost' => 100,
        'received_at' => '2026-01-01', 'status' => 'disponible',
    ]);
    $lotB = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-B', 'quantity' => 6, 'unit_cost' => 300,
        'received_at' => '2026-02-01', 'status' => 'disponible',
    ]);

    $delivery = DeliveryNote::create([
        'company_id' => $company->id, 'client_id' => Client::factory()->create()->id,
        'number' => 'BL-MULTI-LOTS', 'status' => 'brouillon', 'warehouse_id' => $warehouse->id,
        'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $item = $delivery->items()->create([
        'product_id' => $product->id, 'description' => 'Produit multi-lots',
        'quantity' => 7, 'unit_price' => 1000,
    ]);

    $service = app(DeliveryNoteService::class);
    $service->validate($delivery);

    $allocations = $item->lotAllocations()->orderBy('stock_lot_id')->get();
    expect($allocations)->toHaveCount(2)
        ->and((float) $allocations[0]->quantity)->toBe(4.0)
        ->and((float) $allocations[0]->total_cost)->toBe(400.0)
        ->and((float) $allocations[1]->quantity)->toBe(3.0)
        ->and((float) $allocations[1]->total_cost)->toBe(900.0)
        ->and((float) $lotA->fresh()->quantity)->toBe(0.0)
        ->and((float) $lotB->fresh()->quantity)->toBe(3.0)
        ->and((float) StockMovement::where('reference_type', 'delivery_note')->where('reference_id', $delivery->id)->sum('total_cost'))->toBe(1300.0);

    $invoice = $service->createInvoice($delivery->fresh());
    app(InvoiceService::class)->validate($invoice);
    expect((float) $invoice->fresh('items')->items->first()->unit_cost)->toBe(185.71);
    $cogs = JournalEntry::where('reference', $invoice->number.'-STK')->first();
    expect($cogs)->not->toBeNull()
        ->and((float) $cogs->total_debit)->toBe(1300.0)
        ->and((float) $cogs->total_credit)->toBe(1300.0);

    $invoice->update(['status' => 'annulee']);
    $service->cancelValidated($delivery->fresh());
    expect((float) $lotA->fresh()->quantity)->toBe(4.0)
        ->and((float) $lotB->fresh()->quantity)->toBe(6.0)
        ->and($item->lotAllocations()->whereNull('reversed_at')->count())->toBe(0);
});
