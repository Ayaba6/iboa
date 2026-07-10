<?php

/**
 * [BUG-005] Déclaration de production réelle avant clôture d'OF :
 *  - la déclaration (recordOutput) entre le PF en stock + trace lot & observation ;
 *  - la clôture est impossible tant qu'aucune quantité n'est déclarée.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function declAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DECL'], ['email' => 'decl@decl.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WD'], ['name' => 'WD', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_production' => true, 'can_stock' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('déclare la production réelle : entrée stock PF + lot + observation tracés', function () {
    $this->actingAs(declAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-DECL-1', 'status' => 'en_cours', 'quantity_requested' => 30, 'quantity_produced' => 0,
        'product_id' => $pf->id, 'launched_at' => now(),
    ]);

    $output = app(ProductionStockService::class)->recordOutput($of, [
        'warehouse_id' => $wh->id, 'quantity' => 25, 'length' => 6, 'unit_cost' => 3000,
        'lot_number' => 'LOT-PF-TEST', 'notes' => 'Bord légèrement irrégulier',
    ]);

    // Stock PF alimenté, lot & observation persistés.
    expect((float) ProductStock::where('product_id', $pf->id)->where('warehouse_id', $wh->id)->value('quantity'))->toEqual(25.0);
    expect($output->lot_number)->toBe('LOT-PF-TEST');
    expect($output->notes)->toBe('Bord légèrement irrégulier');
    expect((float) $of->fresh()->quantity_produced)->toEqual(25.0);
});

it('interdit la clôture d’un OF sans production déclarée', function () {
    $this->actingAs(declAdmin());
    $co = Company::first();
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-DECL-2', 'status' => 'en_cours', 'quantity_requested' => 30, 'quantity_produced' => 0,
    ]);

    expect(fn () => app(ProductionService::class)->finish($of))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');
});
