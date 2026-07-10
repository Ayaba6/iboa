<?php

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function costCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Cost Co'], ['email' => 'cost@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function costAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => costCompany()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('computes a full cost of production', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();

    $machine = ProductionMachine::create([
        'company_id' => $co->id, 'code' => 'MX', 'name' => 'Profileuse',
        'type' => 'profilage', 'hourly_cost' => 6000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L', 'name' => 'L1', 'is_active' => true]);
    $bom  = BillOfMaterial::create([
        'company_id' => $co->id, 'name' => 'Bac', 'labor_per_unit' => 200, 'machine_time_per_unit' => 3,
        'consumption_per_meter' => 3, 'standard_waste_rate' => 5, 'is_active' => true,
    ]);
    $product = Product::factory()->create(['sale_price' => 5000]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7000',
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 10,
        'product_id' => $product->id, 'bill_of_material_id' => $bom->id, 'production_line_id' => $line->id,
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-C1', 'initial_weight' => 1000,
        'remaining_weight' => 1000, 'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100); // material = 100*500 = 50000

    // output meters for cost/meter
    $order->outputs()->create([
        'company_id' => $co->id, 'product_id' => $product->id, 'length' => 6, 'quantity' => 10,
        'total_meters' => 60, 'produced_at' => now(),
    ]);

    $cost = app(ProductionCostService::class)->compute($order, ['overhead_rate' => 10]);

    expect($cost->material_cost)->toBe(50000);          // consommé
    expect($cost->labor_cost)->toBe(2000);              // 200 * 10
    expect($cost->machine_cost)->toBe(3000);            // (3min*10)/60 * 6000 = 0.5h * 6000
    expect($cost->overhead_cost)->toBe(5500);           // 10% of (50000+2000+3000)
    expect($cost->total_cost)->toBe(60500);
    expect((float) $cost->cost_per_meter)->toEqual(round(60500 / 60, 2));
    expect((float) $cost->cost_per_unit)->toEqual(6050.0);
    expect($cost->margin)->toBe(5000 * 10 - 60500);     // revenue 50000 - 60500 = -10500
});

it('persists cost via the compute route and is idempotent', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();
    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7001',
        'status' => 'termine', 'quantity_requested' => 5, 'quantity_produced' => 5,
    ]);

    $this->post(route('production.orders.cost', $order), ['overhead_rate' => 0])->assertRedirect();
    $this->post(route('production.orders.cost', $order), ['overhead_rate' => 0])->assertRedirect();

    expect(\App\Modules\Production\Models\ProductionCost::where('production_order_id', $order->id)->count())->toBe(1);
});

it('includes BOM component consumption (product_stocks) in material cost, valued at CMP', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();
    $wh = \App\Models\Warehouse::firstOrCreate(['code' => 'WH-COST'], [
        'name' => 'WH Cost', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
        'can_production' => true, 'can_stock' => true, 'can_sale' => true, 'can_purchase' => true,
    ]);

    $finished  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $component = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    \App\Models\ProductStock::create([
        'product_id' => $component->id, 'warehouse_id' => $wh->id,
        'quantity' => 200, 'reserved_quantity' => 0, 'avg_cost' => 800,
    ]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $finished->id, 'name' => 'BOM Cost', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $component->id, 'quantity_per_meter' => 2, 'sort_order' => 1]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7100',
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => $finished->id, 'bill_of_material_id' => $bom->id,
    ]);

    // Déclare 10 → consomme 20 composants (Bug #19) valorisés au CMP 800 = 16 000.
    $output = app(\App\Modules\Production\Services\ProductionStockService::class)
        ->recordOutput($order, ['quantity' => 10, 'length' => 6, 'warehouse_id' => $wh->id]);

    // Le mouvement de sortie composant porte bien une valeur (pas total_cost=0).
    $mv = \App\Models\StockMovement::where('type', 'sortie')
        ->where('reference_type', \App\Modules\Production\Models\ProductionOutput::class)
        ->where('reference_id', $output->id)->first();
    expect((float) $mv->total_cost)->toEqual(16000.0);

    // Le coût de revient intègre cette matière.
    $cost = app(ProductionCostService::class)->compute($order->fresh(), ['overhead_rate' => 0]);
    expect((int) $cost->material_cost)->toBe(16000);
});

it('ventile le coût de revient vers la comptabilité analytique (CDC §13.3/§15.2)', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();

    $machine = ProductionMachine::create([
        'company_id' => $co->id, 'code' => 'MX2', 'name' => 'Profileuse 2',
        'type' => 'profilage', 'hourly_cost' => 6000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L2', 'name' => 'Ligne 2', 'is_active' => true]);
    $bom  = BillOfMaterial::create([
        'company_id' => $co->id, 'name' => 'Bac2', 'labor_per_unit' => 200, 'machine_time_per_unit' => 3,
        'consumption_per_meter' => 3, 'standard_waste_rate' => 5, 'is_active' => true,
    ]);
    $product = Product::factory()->create(['sale_price' => 5000]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7010',
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 10,
        'product_id' => $product->id, 'bill_of_material_id' => $bom->id, 'production_line_id' => $line->id,
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-C2', 'initial_weight' => 1000,
        'remaining_weight' => 1000, 'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100);
    $order->outputs()->create([
        'company_id' => $co->id, 'product_id' => $product->id, 'length' => 6, 'quantity' => 10,
        'total_meters' => 60, 'produced_at' => now(),
    ]);

    app(ProductionCostService::class)->compute($order, ['overhead_rate' => 10]);

    $costCenter = \App\Models\CostCenter::where('code', 'LINE-L2')->first();
    expect($costCenter)->not->toBeNull()
        ->and($costCenter->name)->toBe('Ligne 2');

    $lines = \App\Models\AnalyticLine::where('ref_type', ProductionOrder::class)
        ->where('ref_id', $order->id)->get()->keyBy('category');

    // [CDC coût industriel] 'machine' = dépréciation équipement ; 'energie',
    // 'maintenance' et 'emballage' sont désormais des catégories distinctes
    // (absentes ici car la machine n'a pas de taux énergie/maintenance ni la
    // BOM de coût emballage).
    expect($lines)->toHaveCount(4)
        ->and((float) $lines['matiere']->amount)->toBe(50000.0)
        ->and((float) $lines['main_oeuvre']->amount)->toBe(2000.0)
        ->and((float) $lines['machine']->amount)->toBe(3000.0)
        ->and((float) $lines['overhead']->amount)->toBe(5500.0)
        ->and($lines['matiere']->cost_center_id)->toBe($costCenter->id);

    // Recompute : idempotent, pas de doublons.
    app(ProductionCostService::class)->compute($order, ['overhead_rate' => 10]);
    expect(\App\Models\AnalyticLine::where('ref_type', ProductionOrder::class)->where('ref_id', $order->id)->count())->toBe(4);
});

it('calcule les 5 composantes industrielles : matière, MO, énergie, maintenance, emballage (CDC)', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();

    $machine = ProductionMachine::create([
        'company_id' => $co->id, 'code' => 'MX3', 'name' => 'Profileuse 3',
        'type' => 'profilage', 'hourly_cost' => 6000,
        'energy_cost_per_hour' => 2000, 'maintenance_cost_per_hour' => 1000,
        'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L3', 'name' => 'Ligne 3', 'is_active' => true]);
    $bom  = BillOfMaterial::create([
        'company_id' => $co->id, 'name' => 'Bac3', 'labor_per_unit' => 200, 'machine_time_per_unit' => 3,
        'packaging_per_unit' => 150,
        'consumption_per_meter' => 3, 'standard_waste_rate' => 5, 'is_active' => true,
        // Coûts standards unitaires pour la comparaison std/réel
        'std_material_cost' => 5000, 'std_labor_cost' => 200, 'std_machine_cost' => 300,
        'std_energy_cost' => 100, 'std_maintenance_cost' => 50, 'std_packaging_cost' => 150,
        'std_overhead_cost' => 0,
    ]);
    $product = Product::factory()->create(['sale_price' => 8000, 'cout_standard' => 5800]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7020',
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 10,
        'product_id' => $product->id, 'bill_of_material_id' => $bom->id, 'production_line_id' => $line->id,
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-C3', 'initial_weight' => 1000,
        'remaining_weight' => 1000, 'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100); // matière = 50 000

    $cost = app(ProductionCostService::class)->compute($order);

    // Temps machine : 3 min × 10 u = 30 min = 0,5 h
    expect($cost->material_cost)->toBe(50000)
        ->and($cost->labor_cost)->toBe(2000)         // 200 × 10
        ->and($cost->machine_cost)->toBe(3000)       // 0,5 h × 6 000
        ->and($cost->energy_cost)->toBe(1000)        // 0,5 h × 2 000
        ->and($cost->maintenance_cost)->toBe(500)    // 0,5 h × 1 000
        ->and($cost->packaging_cost)->toBe(1500)     // 150 × 10
        ->and($cost->total_cost)->toBe(58000);       // somme sans overhead

    // Comparaison standard/réel : standard = (5000+200+300+100+50+150) × 10 = 58 000
    expect($cost->standard_total)->toBe(58000)
        ->and($cost->variance)->toBe(0);             // réel = standard → écart nul

    // Ventilation analytique : 6 catégories (overhead = 0 exclu)
    $lines = \App\Models\AnalyticLine::where('ref_type', ProductionOrder::class)
        ->where('ref_id', $order->id)->get()->keyBy('category');
    expect($lines)->toHaveCount(6)
        ->and((float) $lines['energie']->amount)->toBe(1000.0)
        ->and((float) $lines['maintenance']->amount)->toBe(500.0)
        ->and((float) $lines['emballage']->amount)->toBe(1500.0);
});

it('records a quality control', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();
    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7002',
        'status' => 'en_cours', 'quantity_requested' => 5,
    ]);

    $this->post(route('production.orders.quality', $order), [
        'thickness_ok' => '1', 'length_ok' => '1', 'color_ok' => '1', 'visual_ok' => '1',
        'status' => 'conforme',
    ])->assertRedirect();

    $qc = $order->qualityControls()->first();
    expect($qc->status)->toBe('conforme');
    expect($qc->thickness_ok)->toBeTrue();
});
