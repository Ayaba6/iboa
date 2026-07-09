<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\ProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

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
    $matiere = \App\Models\Product::factory()->create(['reference' => 'MAT-X', 'allow_negative_stock' => false]);
    $wh = \App\Models\Warehouse::firstOrCreate(['code' => 'WMS'], ['name' => 'WMS', 'company_id' => $co->id, 'is_active' => true]);
    \App\Models\ProductStock::create(['product_id' => $matiere->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 100]);

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
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');

    // Dérogation explicite → lancement autorisé.
    app(ProductionService::class)->launch($of->fresh(), true);
    expect($of->fresh()->status)->toBe('lance');
});

it('ignores components not tracked in product_stocks (no false shortage)', function () {
    $this->actingAs(ofAutoAdmin());
    $co = Company::first();
    $coilLike = \App\Models\Product::factory()->create(['reference' => 'BOBINE-X']); // aucun product_stocks
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
