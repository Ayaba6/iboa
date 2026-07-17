<?php

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function p4Company(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Exec Co'], ['email' => 'exec@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function p4Admin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => p4Company()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function p4Order(): ProductionOrder
{
    return ProductionOrder::create([
        'company_id'         => p4Company()->id,
        'fiscal_year_id'     => p4Company()->current_fiscal_year_id,
        'number'             => 'OF-2026-9000',
        'status'             => 'en_cours',
        'quantity_requested' => 100,
        'quantity_produced'  => 0,
        'launched_at'        => now(),
        // Les scénarios P4 testent transitions/écarts — le contrôle qualité
        // obligatoire est couvert par ProductionClosureGuardsTest.
        'controle_qualite_obligatoire' => false,
    ]);
}

function p4Coil(array $o = []): Coil
{
    return Coil::create(array_merge([
        'company_id'       => p4Company()->id,
        'reference'        => 'BOB-EXEC-' . rand(1000, 9999),
        'initial_weight'   => 1000,
        'remaining_weight' => 1000,
        'cost_per_kg'      => 500,
        'purchase_price'   => 500000,
        'status'           => 'disponible',
    ], $o));
}

function p4Warehouse(): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => 'WH-EXEC'],
        [
            'name' => 'Exec WH', 'company_id' => p4Company()->id, 'is_active' => true, 'is_default' => true,
            // CDC dépôts : la sortie d'OF exige un dépôt de production
            'can_production' => true, 'can_sale' => true, 'can_purchase' => true, 'can_stock' => true,
        ]
    );
}

it('consumes a coil: decrements weight, computes cost, sets status', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 200, 'length_consumed' => 50,
    ])->assertRedirect();

    $coil->refresh();
    expect((float) $coil->remaining_weight)->toEqual(800.0);
    expect($coil->status)->toBe('en_production');

    $cons = $order->consumptions()->first();
    expect((float) $cons->cost)->toEqual(100000.0); // 200 * 500
});

it('rejects consuming more than remaining weight', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil(['remaining_weight' => 100]);

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 500,
    ])->assertSessionHasErrors('weight');

    expect((float) $coil->fresh()->remaining_weight)->toEqual(100.0);
});

it('rejects consumption when OF not in progress', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $order->update(['status' => 'lance']);
    $coil = p4Coil();

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 10,
    ])->assertSessionHasErrors('status');
});

it('reverses a consumption and restores coil weight', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();

    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 300]);
    $cons = $order->consumptions()->first();
    expect((float) $coil->fresh()->remaining_weight)->toEqual(700.0);

    $this->delete(route('production.consumptions.destroy', $cons))->assertRedirect();
    expect((float) $coil->fresh()->remaining_weight)->toEqual(1000.0);
    expect($coil->fresh()->status)->toBe('disponible');
    // [Sync coils/lots] Le reverse CONSERVE la consommation (traçabilité) :
    // elle est marquée reversed_at et sort des agrégats via le scope active().
    expect($order->consumptions()->count())->toBe(1)
        ->and($order->consumptions()->first()->reversed_at)->not->toBeNull()
        ->and($order->consumptions()->active()->count())->toBe(0);
});

it('records an output and enters finished goods into stock', function () {
    $this->actingAs(p4Admin());
    $order   = p4Order();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $wh      = p4Warehouse();
    $order->update(['product_id' => $product->id]);

    $this->post(route('production.orders.output', $order), [
        'warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000,
    ])->assertRedirect();

    $out = $order->outputs()->first();
    expect((float) $out->total_meters)->toEqual(60.0);
    expect($out->stock_movement_id)->not->toBeNull();
    expect((float) $order->fresh()->quantity_produced)->toEqual(10.0);

    $stock = \App\Models\ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toEqual(10.0);
});

it('blocks reversing execution records once OF is no longer in progress', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();
    $wh    = p4Warehouse();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $order->update(['product_id' => $product->id]);

    // record while en_cours
    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 100]);
    $this->post(route('production.orders.output', $order), ['warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000]);
    $this->post(route('production.orders.waste', $order), ['type' => 'rebut', 'weight' => 10]);

    $cons = $order->consumptions()->first();
    $out  = $order->outputs()->first();
    $waste = $order->wastes()->first();

    // close the OF
    $order->update(['status' => 'termine', 'finished_at' => now()]);

    $this->delete(route('production.consumptions.destroy', $cons))->assertSessionHasErrors('status');
    $this->delete(route('production.outputs.destroy', $out))->assertSessionHasErrors('status');
    $this->delete(route('production.wastes.destroy', $waste))->assertSessionHasErrors('status');

    // nothing removed, coil weight intact (still consumed)
    expect($order->consumptions()->count())->toBe(1);
    expect($order->outputs()->count())->toBe(1);
    expect($order->wastes()->count())->toBe(1);
    expect((float) $coil->fresh()->remaining_weight)->toEqual(900.0);
});

it('auto-consumes BOM components on production declaration (Bug #19)', function () {
    $this->actingAs(p4Admin());
    $wh = p4Warehouse();

    // Produit fini + composant suivi en product_stocks (stock 64).
    $finished  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $component = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    \App\Models\ProductStock::create([
        'product_id' => $component->id, 'warehouse_id' => $wh->id,
        'quantity' => 64, 'reserved_quantity' => 0, 'avg_cost' => 3048,
    ]);

    // Nomenclature : 2 composants par unité produite.
    $bom = \App\Modules\Production\Models\BillOfMaterial::create([
        'company_id' => p4Company()->id, 'product_id' => $finished->id,
        'name' => 'BOM Test #19', 'is_active' => true,
    ]);
    \App\Modules\Production\Models\BomLine::create([
        'bill_of_material_id' => $bom->id, 'product_id' => $component->id,
        'quantity_per_meter' => 2, 'sort_order' => 1,
    ]);

    $order = p4Order();
    $order->update(['product_id' => $finished->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 5]);

    // Déclaration de production de 5 unités.
    $this->post(route('production.orders.output', $order), [
        'warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 5, 'unit_cost' => 3000,
    ])->assertRedirect();

    // Produit fini : +5 ; composant : -10 (2 × 5) → 54.
    $pf = \App\Models\ProductStock::where('product_id', $finished->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $pf->quantity)->toEqual(5.0);

    $comp = \App\Models\ProductStock::where('product_id', $component->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $comp->quantity)->toEqual(54.0);

    // Annulation de la déclaration → composant restauré à 64, PF à 0.
    $out = $order->outputs()->first();
    $this->delete(route('production.outputs.destroy', $out))->assertRedirect();
    expect((float) $comp->fresh()->quantity)->toEqual(64.0);
    expect((float) $pf->fresh()->quantity)->toEqual(0.0);
});

it('blocks OF launch on material shortage, allows launch with derogation (Bug #20)', function () {
    $this->actingAs(p4Admin());
    $wh = p4Warehouse();

    $finished  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $component = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'allow_negative_stock' => false]);
    \App\Models\ProductStock::create([
        'product_id' => $component->id, 'warehouse_id' => $wh->id,
        'quantity' => 64, 'reserved_quantity' => 0, 'avg_cost' => 3048,
    ]);

    $bom = \App\Modules\Production\Models\BillOfMaterial::create([
        'company_id' => p4Company()->id, 'product_id' => $finished->id, 'name' => 'BOM #20', 'is_active' => true,
    ]);
    \App\Modules\Production\Models\BomLine::create([
        'bill_of_material_id' => $bom->id, 'product_id' => $component->id, 'quantity_per_meter' => 2, 'sort_order' => 1,
    ]);

    // besoin = 2 × 100 = 200 > dispo 64 → rupture.
    $order = p4Order();
    $order->update(['status' => 'matiere_allouee', 'product_id' => $finished->id, 'bill_of_material_id' => $bom->id]);

    $svc = app(\App\Modules\Production\Services\ProductionService::class);

    // 1. Lancement normal bloqué.
    expect(fn () => $svc->launch($order->fresh()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($order->fresh()->status)->toBe('matiere_allouee');

    // 2. Dérogation → lancement autorisé.
    $svc->launch($order->fresh(), true);
    expect($order->fresh()->status)->toBe('lance');
});

it('launches normally when material is sufficient', function () {
    $this->actingAs(p4Admin());
    $wh = p4Warehouse();

    $finished  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $component = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    \App\Models\ProductStock::create([
        'product_id' => $component->id, 'warehouse_id' => $wh->id,
        'quantity' => 500, 'reserved_quantity' => 0, 'avg_cost' => 3048,
    ]);

    $bom = \App\Modules\Production\Models\BillOfMaterial::create([
        'company_id' => p4Company()->id, 'product_id' => $finished->id, 'name' => 'BOM OK', 'is_active' => true,
    ]);
    \App\Modules\Production\Models\BomLine::create([
        'bill_of_material_id' => $bom->id, 'product_id' => $component->id, 'quantity_per_meter' => 2, 'sort_order' => 1,
    ]);

    $order = p4Order();
    $order->update(['status' => 'matiere_allouee', 'product_id' => $finished->id, 'bill_of_material_id' => $bom->id]);

    app(\App\Modules\Production\Services\ProductionService::class)->launch($order->fresh());
    expect($order->fresh()->status)->toBe('lance');
});

it('blocks full OF closure when produced < requested, allows with confirmation (Bug #21)', function () {
    $this->actingAs(p4Admin());
    $svc = app(\App\Modules\Production\Services\ProductionService::class);

    // Produit 25 sur 100 demandés, aucune déclaration en attente de visa.
    $order = p4Order();
    $order->update(['status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 25]);

    // 1. Clôture normale bloquée (écart).
    expect(fn () => $svc->finish($order->fresh()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($order->fresh()->status)->toBe('en_cours');

    // 2. Dérogation (écart confirmé) → clôture autorisée.
    $svc->finish($order->fresh(), true);
    expect($order->fresh()->status)->toBe('termine');
});

it('closes OF normally when produced meets requested', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $order->update(['status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 100]);

    app(\App\Modules\Production\Services\ProductionService::class)->finish($order->fresh());
    expect($order->fresh()->status)->toBe('termine');
});

it('records a waste and values it from consumed cost', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();
    // consume first so average cost/kg = 500
    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 100]);

    $this->post(route('production.orders.waste', $order), [
        'type' => 'rebut', 'weight' => 20, 'reason' => 'Bord abîmé',
    ])->assertRedirect();

    $waste = $order->wastes()->first();
    expect((float) $waste->value)->toEqual(10000.0); // 20 * 500
    expect($waste->type)->toBe('rebut');
});
