<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function bcdAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'BCD'], ['email' => 'bcd@bcd.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

// ── Phase D : dépôts typés ───────────────────────────────────────────────────
it('stores and reads a warehouse type', function () {
    $this->actingAs(bcdAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'W-MP', 'name' => 'MP',
        'type' => 'matiere_premiere', 'is_active' => true]);

    expect($wh->fresh()->type)->toBe('matiere_premiere');
    expect(Warehouse::TYPES)->toHaveKeys(['achat', 'matiere_premiere', 'production', 'produit_fini', 'vente']);
});

// ── Phase C : mode MTS / MTO ─────────────────────────────────────────────────
it('persists the production mode on a product', function () {
    $this->actingAs(bcdAdmin());
    $mto = Product::factory()->create(['reference' => 'TBAC', 'production_mode' => 'mto']);
    $mts = Product::factory()->create(['reference' => 'TFER', 'production_mode' => 'mts']);

    expect($mto->fresh()->production_mode)->toBe('mto');
    expect($mts->fresh()->production_mode)->toBe('mts');
});

// ── Phase B : chute/avarié liés au BOM + entrée stock au suivi ───────────────
it('links scrap and defect products to a BOM', function () {
    $this->actingAs(bcdAdmin());
    $co = Company::first();
    $pf    = Product::factory()->create(['reference' => 'PF-X']);
    $scrap = Product::factory()->create(['reference' => 'CHUTE-X']);
    $defect = Product::factory()->create(['reference' => 'AVARIE-X']);

    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id,
        'scrap_product_id' => $scrap->id, 'defect_product_id' => $defect->id,
        'name' => 'BOM X', 'is_active' => true,
    ]);

    expect($bom->scrapProduct->reference)->toBe('CHUTE-X');
    expect($bom->defectProduct->reference)->toBe('AVARIE-X');
});

it('enters a by-product (scrap) into stock from the production follow-up', function () {
    $this->actingAs(bcdAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'W-PF', 'name' => 'PF',
        'type' => 'produit_fini', 'is_default' => true, 'is_active' => true]);
    $scrap = Product::factory()->create(['reference' => 'CHUTE-FER', 'is_stockable' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-BCD', 'status' => 'en_cours', 'quantity_requested' => 10,
    ]);

    $move = app(ProductionStockService::class)->enterByproduct($of, $scrap->id, 12.5, $wh->id, 350);

    expect($move)->not->toBeNull();
    expect((float) ProductStock::where('product_id', $scrap->id)->where('warehouse_id', $wh->id)->value('quantity'))
        ->toBe(12.5);
});

it('enters scrap into stock via the follow-up endpoint', function () {
    $this->actingAs(bcdAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'W-PF2', 'name' => 'PF2',
        'type' => 'produit_fini', 'is_default' => true, 'is_active' => true]);
    $pf    = Product::factory()->create(['reference' => 'PF-Y']);
    $scrap = Product::factory()->create(['reference' => 'CHUTE-Y', 'is_stockable' => true]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id,
        'scrap_product_id' => $scrap->id, 'name' => 'BOM Y', 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-EP', 'status' => 'en_cours', 'quantity_requested' => 5,
        'product_id' => $pf->id, 'bill_of_material_id' => $bom->id]);

    $this->post(route('production.orders.byproduct', $of), [
        'scrap_weight' => 8.0, 'scrap_warehouse_id' => $wh->id,
    ])->assertRedirect();

    expect((float) ProductStock::where('product_id', $scrap->id)->where('warehouse_id', $wh->id)->value('quantity'))
        ->toBe(8.0);
});
