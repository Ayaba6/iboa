<?php

/**
 * [FIX A1 — rapport de test MTO] Anti double comptage du coût matière.
 *
 * Quand une bobine est consommée physiquement (ProductionConsumption) ET que la
 * nomenclature backflushe le MÊME produit à la déclaration, la matière ne doit
 * être comptée qu'UNE fois dans le coût de revient (le réel bobine prime).
 * Un composant BOM différent (non tracé par bobine) reste compté normalement.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('ne compte la matière qu’une fois quand bobine consommée ET backflush BOM du même produit', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'DDP'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DDP Co'], ['email' => 'ddp@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole($r);
    $this->actingAs($u);

    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-DDP'], [
        'name' => 'WH DDP', 'is_active' => true, 'is_default' => true,
        'can_production' => true, 'can_stock' => true, 'can_sale' => true, 'can_purchase' => true,
    ]);

    $finished = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $bobine   = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']); // matière tracée par bobine
    $visserie = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']); // composant sans bobine

    ProductStock::create(['product_id' => $bobine->id,   'warehouse_id' => $wh->id, 'quantity' => 500, 'reserved_quantity' => 0, 'avg_cost' => 850]);
    ProductStock::create(['product_id' => $visserie->id, 'warehouse_id' => $wh->id, 'quantity' => 500, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $finished->id, 'name' => 'BOM DDP', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $bobine->id,   'quantity_per_meter' => 1.7513, 'sort_order' => 1]);
    $bom->lines()->create(['product_id' => $visserie->id, 'quantity_per_meter' => 0.5,    'sort_order' => 2]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-DDP-001',
        'status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 0,
        'product_id' => $finished->id, 'bill_of_material_id' => $bom->id,
    ]);

    // Consommation PHYSIQUE de la bobine (réel : 182 kg × 850 = 154 700)
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $bobine->id, 'reference' => 'BOB-DDP-001',
        'initial_weight' => 500, 'remaining_weight' => 500, 'cost_per_kg' => 850,
        'purchase_price' => 425000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 182, 101);

    // Déclaration 100 ml → backflush BOM : bobine 175,13 (à EXCLURE) + visserie 50 × 100 = 5 000 (à garder)
    app(ProductionStockService::class)->recordOutput($order->fresh(), [
        'quantity' => 100, 'length' => 1, 'warehouse_id' => $wh->id,
    ]);

    $cost = app(ProductionCostService::class)->compute($order->fresh(), ['overhead_rate' => 0]);

    // Matière = bobine réelle (154 700) + visserie backflushée (0,5 × 100 × 100 = 5 000)
    // SANS le backflush bobine (175,13 × 850 = 148 860) — plus de double comptage.
    expect((int) $cost->material_cost)->toBe(154700 + 5000);
});
