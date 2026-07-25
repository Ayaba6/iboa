<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\ConsumptionVarianceService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Modules\Production\Services\RoutingService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function ofAutoAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'OFA'], ['email' => 'ofa@ofa.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('auto-loads operations from the routing when the OF is launched', function () {
    $this->actingAs(ofAutoAdmin());
    $co = Company::first();
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'name' => 'BOM', 'is_active' => true]);
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'CT1', 'name' => 'Centre 1',
        'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 90, 'is_active' => true]);
    $routing = Routing::create(['company_id' => $co->id, 'bill_of_material_id' => $bom->id, 'code' => 'G1', 'name' => 'Gamme', 'is_active' => true]);
    foreach ([['Découpe', 10], ['Profilage', 20]] as [$name, $seq]) {
        $routing->operations()->create(['work_center_id' => $wc->id, 'sequence' => $seq, 'name' => $name, 'setup_minutes' => 10, 'run_minutes_per_unit' => 1]);
    }
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-AUTO', 'status' => 'brouillon', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 10]);

    app(ProductionService::class)->launch($of);

    expect($of->fresh()->status)->toBe('lance');
    expect($of->operations()->count())->toBe(2);
});

it('blocks OF launch on material shortage (dérogation required)', function () {
    $this->actingAs(ofAutoAdmin());
    $co = Company::first();
    $matiere = Product::factory()->create(['reference' => 'MAT-X', 'allow_negative_stock' => false]);
    $wh = Warehouse::firstOrCreate(['code' => 'WMS'], ['name' => 'WMS', 'company_id' => $co->id, 'is_active' => true]);
    ProductStock::create(['product_id' => $matiere->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'name' => 'BOM-MAT', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $matiere->id, 'label' => 'Matière', 'quantity_per_meter' => 2, 'waste_rate' => 0, 'sort_order' => 1]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SHORT', 'status' => 'brouillon', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 10]); // besoin 20 > 5

    $shortages = app(ProductionService::class)->materialShortages($of);
    expect($shortages)->toHaveCount(1);
    expect($shortages[0]['need'])->toBe(20.0);
    expect($shortages[0]['available'])->toBe(5.0);

    // [CDC §3] Bloquant : le lancement normal échoue en cas de rupture matière.
    expect(fn () => app(ProductionService::class)->launch($of))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');

    // Dérogation explicite → lancement autorisé.
    app(ProductionService::class)->launch($of->fresh(), true);
    expect($of->fresh()->status)->toBe('lance');
});

it('ignores components not tracked in product_stocks (no false shortage)', function () {
    $this->actingAs(ofAutoAdmin());
    $co = Company::first();
    $coilLike = Product::factory()->create(['reference' => 'BOBINE-X']); // aucun product_stocks
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'name' => 'BOM-COIL', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $coilLike->id, 'label' => 'Bobine', 'quantity_per_meter' => 3, 'waste_rate' => 0, 'sort_order' => 1]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-COIL', 'status' => 'brouillon', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 100]);

    expect(app(ProductionService::class)->materialShortages($of))->toBeEmpty();
});

it('launches without error when the BOM has no routing', function () {
    $this->actingAs(ofAutoAdmin());
    $co = Company::first();
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'name' => 'BOM2', 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-NOGAM', 'status' => 'brouillon', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 5]);

    app(ProductionService::class)->launch($of);

    expect($of->fresh()->status)->toBe('lance');
    expect($of->operations()->count())->toBe(0);
});
it('fige la nomenclature et la gamme au lancement sans effet rétroactif', function () {
    $this->actingAs(ofAutoAdmin());
    $company = Company::first();
    $finished = Product::factory()->create(['reference' => 'PF-SNAPSHOT']);
    $component = Product::factory()->create(['reference' => 'MP-SNAPSHOT']);
    $bom = BillOfMaterial::create([
        'company_id' => $company->id,
        'product_id' => $finished->id,
        'name' => 'BOM snapshot',
        'version_majeure' => '2',
        'version_mineure' => '3',
        'std_material_cost' => 100,
        'labor_per_unit' => 7,
        'packaging_per_unit' => 3,
        'is_active' => true,
    ]);
    $line = $bom->lines()->create([
        'product_id' => $component->id,
        'label' => 'Composant figé',
        'quantity_per_meter' => 2,
        'waste_rate' => 5,
        'sort_order' => 1,
    ]);
    $workCenter = WorkCenter::create([
        'company_id' => $company->id,
        'code' => 'WC-SNAPSHOT',
        'name' => 'Centre snapshot',
        'capacity_hours_per_day' => 8,
        'cost_per_hour' => 5000,
        'efficiency_rate' => 100,
        'is_active' => true,
    ]);
    $routing = Routing::create([
        'company_id' => $company->id,
        'bill_of_material_id' => $bom->id,
        'code' => 'RT-SNAPSHOT',
        'name' => 'Gamme snapshot',
        'version_majeure' => '4',
        'version_mineure' => '1',
        'is_active' => true,
    ]);
    $routingOperation = $routing->operations()->create([
        'work_center_id' => $workCenter->id,
        'sequence' => 10,
        'name' => 'Opération figée',
        'setup_minutes' => 10,
        'run_minutes_per_unit' => 2,
    ]);
    $of = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-SNAPSHOT',
        'status' => 'brouillon',
        'product_id' => $finished->id,
        'bill_of_material_id' => $bom->id,
        'quantity_requested' => 5,
    ]);
    $of->lines()->create(['length' => 2, 'quantity' => 5, 'total_meters' => 10]);

    app(ProductionService::class)->launch($of, force: true);
    $of->refresh();

    expect($of->bom_version)->toBe('2.3')
        ->and($of->routing_version)->toBe('4.1')
        ->and($of->bom_snapshot_sha256)->toHaveLength(64)
        ->and($of->routing_snapshot_sha256)->toHaveLength(64)
        ->and(data_get($of->bom_snapshot, 'lines.0.quantity_per_meter'))->toBe('2.0000')
        ->and(data_get($of->routing_snapshot, 'operations.0.name'))->toBe('Opération figée')
        ->and((float) $of->operations()->first()->planned_minutes)->toBe(20.0);

    $bom->update(['std_material_cost' => 9999, 'labor_per_unit' => 999, 'packaging_per_unit' => 999]);
    $line->update(['quantity_per_meter' => 9, 'waste_rate' => 50]);
    $routingOperation->update(['name' => 'Opération modifiée', 'setup_minutes' => 99, 'run_minutes_per_unit' => 99]);
    $workCenter->update(['cost_per_hour' => 999999]);

    $variance = app(ConsumptionVarianceService::class)->forOrder($of->fresh());
    $cost = app(ProductionCostService::class)->compute($of->fresh());
    $of->refresh();

    expect(data_get($of->bom_snapshot, 'lines.0.quantity_per_meter'))->toBe('2.0000')
        ->and(data_get($of->routing_snapshot, 'operations.0.name'))->toBe('Opération figée')
        ->and($variance['lines']->first()['theoretical_qty'])->toBe(21.0)
        ->and((int) $cost->standard_total)->toBe(500)
        ->and($of->operations()->first()->name)->toBe('Opération figée')
        ->and((float) $of->operations()->first()->planned_minutes)->toBe(20.0);
});
it('utilise le snapshot BOM pour le backflush après modification du référentiel', function () {
    $this->actingAs(ofAutoAdmin());
    $company = Company::first();
    $finished = Product::factory()->create(['reference' => 'PF-BACK-SNAPSHOT']);
    $component = Product::factory()->create([
        'reference' => 'MP-BACK-SNAPSHOT',
        'is_stockable' => true,
        'allow_negative_stock' => false,
    ]);
    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'code' => 'WH-BACK-SNAPSHOT',
        'name' => 'Dépôt snapshot',
        'is_active' => true,
    ]);
    ProductStock::create([
        'product_id' => $component->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
        'avg_cost' => 500,
    ]);
    $bom = BillOfMaterial::create([
        'company_id' => $company->id,
        'product_id' => $finished->id,
        'name' => 'BOM backflush snapshot',
        'is_active' => true,
    ]);
    $line = $bom->lines()->create([
        'product_id' => $component->id,
        'quantity_per_meter' => 2,
        'depot_sortie_id' => $warehouse->id,
        'waste_rate' => 0,
        'sort_order' => 1,
    ]);
    $of = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-BACK-SNAPSHOT',
        'status' => 'brouillon',
        'product_id' => $finished->id,
        'bill_of_material_id' => $bom->id,
        'quantity_requested' => 10,
    ]);

    $production = app(ProductionService::class);
    $production->launch($of, force: true);
    $line->update(['quantity_per_meter' => 9, 'depot_sortie_id' => null]);
    $production->start($of->fresh());

    app(ProductionStockService::class)->recordOutput($of->fresh(), [
        'product_id' => $finished->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'length' => 1,
        'unit_cost' => 1000,
        'lot_number' => 'LOT-BACK-SNAPSHOT',
    ]);

    expect((float) ProductStock::where('product_id', $component->id)
        ->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(98.0);
});
it('annule entièrement le lancement si la génération des opérations échoue', function () {
    $this->actingAs(ofAutoAdmin());
    $company = Company::first();
    $bom = BillOfMaterial::create([
        'company_id' => $company->id,
        'name' => 'BOM transactionnelle',
        'is_active' => true,
    ]);
    $workCenter = WorkCenter::create([
        'company_id' => $company->id,
        'code' => 'WC-ROLLBACK',
        'name' => 'Centre rollback',
        'capacity_hours_per_day' => 8,
        'cost_per_hour' => 5000,
        'efficiency_rate' => 100,
        'is_active' => true,
    ]);
    $routing = Routing::create([
        'company_id' => $company->id,
        'bill_of_material_id' => $bom->id,
        'code' => 'RT-ROLLBACK',
        'name' => 'Gamme rollback',
        'is_active' => true,
    ]);
    $routing->operations()->create([
        'work_center_id' => $workCenter->id,
        'sequence' => 10,
        'name' => 'Opération rollback',
        'setup_minutes' => 5,
        'run_minutes_per_unit' => 1,
    ]);
    $of = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-ROLLBACK-SNAPSHOT',
        'status' => 'brouillon',
        'bill_of_material_id' => $bom->id,
        'quantity_requested' => 1,
    ]);

    $routingFailure = Mockery::mock(RoutingService::class);
    $routingFailure->shouldReceive('generateWorkOrders')->once()->andThrow(new RuntimeException('Échec gamme simulé'));
    app()->instance(RoutingService::class, $routingFailure);

    expect(fn () => app(ProductionService::class)->launch($of, force: true))
        ->toThrow(RuntimeException::class, 'Échec gamme simulé');

    $of->refresh();
    expect($of->status)->toBe('brouillon')
        ->and($of->launched_at)->toBeNull()
        ->and($of->bom_snapshot)->toBeNull()
        ->and($of->routing_snapshot)->toBeNull()
        ->and($of->operations()->count())->toBe(0);
});
