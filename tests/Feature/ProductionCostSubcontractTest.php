<?php

/**
 * [PRO-10] Coût de revient — composante sous-traitance.
 */

use App\Models\Company;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;

uses(\Tests\Concerns\RefreshDatabase::class);

it('persiste le coût de sous-traitance dans le coût de revient', function () {
    $co = Company::firstOrCreate(['name' => 'COST Co'], ['email' => 'cost@iboa.test']);
    $order = ProductionOrder::factory()->create(['company_id' => $co->id]);

    $cost = ProductionCost::create([
        'company_id' => $co->id, 'production_order_id' => $order->id,
        'material_cost' => 100000, 'labor_cost' => 20000, 'machine_cost' => 15000,
        'energy_cost' => 5000, 'maintenance_cost' => 3000, 'packaging_cost' => 2000,
        'subcontract_cost' => 40000, 'overhead_cost' => 8000,
        'total_cost' => 193000,
    ]);

    $fresh = $cost->fresh();
    expect($fresh->subcontract_cost)->toBe(40000);
    // le coût total inclut bien la sous-traitance
    $direct = $fresh->material_cost + $fresh->labor_cost + $fresh->machine_cost
        + $fresh->energy_cost + $fresh->maintenance_cost + $fresh->packaging_cost + $fresh->subcontract_cost;
    expect($fresh->total_cost)->toBe($direct + $fresh->overhead_cost);
});
