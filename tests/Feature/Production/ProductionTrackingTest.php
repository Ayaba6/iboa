<?php

/**
 * [Parité SAGE X3 — Suivi de fabrication] Écran de saisie unique :
 *  - suivi complet (opérations + déclaration production + matière) sur un OF
 *    en cours → pointage done, entrée stock PF, consommation bobine, journal ;
 *  - refus sur OF non « en cours » et sans aucun type de suivi coché.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionTracking;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function trkAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TRK'], ['email' => 'trk@trk.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WT'], ['name' => 'WT', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_production' => true, 'can_stock' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('enregistre un suivi complet : opération pointée + production déclarée + bobine consommée', function () {
    $this->actingAs(trkAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-TRK-1', 'status' => 'en_cours', 'product_id' => $pf->id,
        'quantity_requested' => 10, 'quantity_produced' => 0, 'launched_at' => now(),
    ]);
    $op = $of->operations()->create([
        'company_id' => $co->id, 'sequence' => 10, 'name' => 'Profilage', 'planned_minutes' => 45, 'status' => 'pending',
    ]);
    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-TRK', 'initial_weight' => 300,
        'remaining_weight' => 300, 'cost_per_kg' => 500, 'purchase_price' => 150000, 'status' => 'disponible',
    ]);

    $this->post(route('production.trackings.store'), [
        'production_order_id' => $of->id,
        'track_operations' => 1, 'track_production' => 1, 'track_materials' => 1,
        'operations' => [['id' => $op->id, 'real_minutes' => 40]],
        'quantity' => 6, 'length' => 6, 'warehouse_id' => $wh->id, 'unit_cost' => 2000, 'lot_number' => 'LOT-TRK',
        'coil_id' => $coil->id, 'weight_consumed' => 120,
    ])->assertRedirect(route('production.orders.show', $of))
      ->assertSessionHas('success');

    // Opération pointée
    expect($op->fresh()->status)->toBe('done')
        ->and((float) $op->fresh()->real_minutes)->toEqual(40.0);

    // Production déclarée (entrée stock PF)
    expect((float) $of->fresh()->quantity_produced)->toEqual(6.0);
    expect((float) ProductStock::where('product_id', $pf->id)->where('warehouse_id', $wh->id)->value('quantity'))->toEqual(6.0);

    // Bobine consommée
    expect((float) $coil->fresh()->remaining_weight)->toEqual(180.0);
    expect($of->consumptions()->count())->toBe(1);

    // Journal du suivi
    $t = ProductionTracking::first();
    expect($t)->not->toBeNull()
        ->and($t->number)->toStartWith('SUIVI-')
        ->and($t->track_operations)->toBeTrue()
        ->and($t->track_production)->toBeTrue()
        ->and($t->track_materials)->toBeTrue();
});

it('refuse le suivi sur un OF non « en cours » et sans type de suivi coché', function () {
    $this->actingAs(trkAdmin());
    $co = Company::first();

    $ofDraft = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-TRK-2', 'status' => 'brouillon', 'quantity_requested' => 5,
    ]);

    // OF brouillon → refus
    $this->post(route('production.trackings.store'), [
        'production_order_id' => $ofDraft->id, 'track_production' => 1, 'quantity' => 2,
    ])->assertSessionHasErrors('production_order_id');

    // Aucun type de suivi coché → refus
    $ofRun = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-TRK-3', 'status' => 'en_cours', 'quantity_requested' => 5,
    ]);
    $this->post(route('production.trackings.store'), [
        'production_order_id' => $ofRun->id,
    ])->assertSessionHasErrors('track_operations');

    expect(ProductionTracking::count())->toBe(0);
});
