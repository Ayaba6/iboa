<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockValuationAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\FinishedGoodsValuationService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Modules\Quality\Services\QualityReleaseService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('isole le produit fini en quarantaine jusqu’à sa libération qualité', function () {
    $fiscalYear = FiscalYear::firstOrCreate(['label' => 'QREL'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Qualité Release Co'], [
        'email' => 'quality-release@iboa.test', 'current_fiscal_year_id' => $fiscalYear->id,
    ]);
    app()->instance('current_company', $company);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $quarantine = Warehouse::create([
        'company_id' => $company->id, 'code' => 'QUA-REL', 'name' => 'Quarantaine release',
        'type' => 'quarantaine', 'can_stock' => true, 'is_active' => true,
    ]);
    $sellable = Warehouse::create([
        'company_id' => $company->id, 'code' => 'PF-REL', 'name' => 'PF vendable',
        'type' => 'produit_fini', 'can_production' => true, 'can_sale' => true,
        'can_delivery' => true, 'quality_warehouse_id' => $quarantine->id, 'is_active' => true,
    ]);
    $product = Product::factory()->create(['is_stockable' => true]);
    $order = ProductionOrder::factory()->create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'product_id' => $product->id,
        'status' => 'en_cours',
        'quantity_requested' => 2,
        'quantity_produced' => 0,
        'controle_qualite_obligatoire' => true,
    ]);

    $output = app(ProductionStockService::class)->recordOutput($order, [
        'warehouse_id' => $sellable->id,
        'quantity' => 2,
        'length' => 1,
        'unit_cost' => 500,
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now(), 'validated_by' => $user->id]);

    expect($output->fresh()->warehouse_id)->toBe($quarantine->id)
        ->and($output->fresh()->release_warehouse_id)->toBe($sellable->id)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $quarantine->id)->value('quantity'))->toBe(2.0)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $sellable->id)->value('quantity'))->toBe(0.0)
        ->and($order->batches()->count())->toBe(1);

    ProductionQualityControl::create([
        'company_id' => $company->id,
        'production_order_id' => $order->id,
        'thickness_ok' => true,
        'length_ok' => true,
        'color_ok' => true,
        'visual_ok' => true,
        'status' => 'conforme',
        'controlled_at' => now(),
        'created_by' => $user->id,
    ]);
    expect(fn () => app(ProductionService::class)->finish($order->fresh()))
        ->toThrow(ValidationException::class, 'Libération qualité');
    $batch = $order->batches()->first();
    app(QualityReleaseService::class)->decide($batch, 'libere', 'Contrôle conforme');

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $quarantine->id)->value('quantity'))->toBe(0.0)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $sellable->id)->value('quantity'))->toBe(2.0)
        ->and($output->fresh()->quality_released_at)->not->toBeNull();

    app(ProductionService::class)->finish($order->fresh());
    expect($order->fresh()->status)->toBe('termine');

    ProductionCost::where('production_order_id', $order->id)->update([
        'material_cost' => 1000,
        'labor_cost' => 100,
        'machine_cost' => 100,
        'total_cost' => 1200,
        'cost_per_unit' => 600,
    ]);
    app(FinishedGoodsValuationService::class)->revalue($order->fresh());
    app(FinishedGoodsValuationService::class)->revalue($order->fresh()); // idempotence

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $sellable->id)->value('avg_cost'))->toBe(600.0)
        ->and((float) $output->fresh()->stockMovement->unit_cost)->toBe(500.0)
        ->and((float) $output->fresh()->stockMovement->total_cost)->toBe(1000.0)
        ->and((float) StockMovement::where('idempotency_key', 'quality-release-output:'.$output->id)->value('unit_cost'))->toBe(500.0)
        ->and(StockMovement::where('idempotency_key', 'production-valuation-adjustment:'.$order->id.':'.$output->id)->value('type'))->toBe('valuation_adjustment')
        ->and((float) StockMovement::where('idempotency_key', 'production-valuation-adjustment:'.$order->id.':'.$output->id)->value('total_cost'))->toBe(200.0)
        ->and(StockValuationAdjustment::where('production_order_id', $order->id)->count())->toBe(1)
        ->and((float) $product->fresh()->weighted_avg_cost)->toBe(600.0);
});
