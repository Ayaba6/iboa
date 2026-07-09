<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\BatchService;
use App\Modules\Production\Services\BomExplosionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function sfAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SF'], ['email' => 'sf@sf.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('explodes a multi-level BOM via semi-finished components', function () {
    $this->actingAs(sfAdmin());
    $co = Company::first();

    // Produit semi-fini "Ferme" avec sa propre nomenclature (2 composants matière)
    $ferme = Product::factory()->create(['name' => 'Ferme métallique', 'is_semi_finished' => true]);
    $bomFerme = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $ferme->id, 'name' => 'BOM Ferme', 'is_active' => true]);
    $bomFerme->lines()->create(['label' => 'Cornière', 'quantity_per_meter' => 4]);
    $bomFerme->lines()->create(['label' => 'Tube', 'quantity_per_meter' => 2]);

    // Hangar (PF) composé de 3 fermes (semi-fini) + boulons
    $bomHangar = BillOfMaterial::create(['company_id' => $co->id, 'name' => 'BOM Hangar', 'is_active' => true]);
    $bomHangar->lines()->create(['product_id' => $ferme->id, 'label' => 'Ferme', 'quantity_per_meter' => 3]);
    $bomHangar->lines()->create(['label' => 'Boulonnerie', 'quantity_per_meter' => 50]);

    $rows = app(BomExplosionService::class)->explode($bomHangar, 1);

    // Ferme (depth 0) + ses 2 sous-composants (depth 1) + Boulonnerie (depth 0) = 4
    expect($rows)->toHaveCount(4);
    $ferme0 = collect($rows)->firstWhere('label', 'Ferme');
    expect($ferme0['is_semi_finished'])->toBeTrue();
    expect($ferme0['has_sub_bom'])->toBeTrue();
    // Cornière = 4 par ferme × 3 fermes = 12
    $corniere = collect($rows)->firstWhere('label', 'Cornière');
    expect($corniere['depth'])->toBe(1);
    expect($corniere['quantity'])->toEqual(12.0);
});

it('creates a fabrication batch with auto number', function () {
    $this->actingAs(sfAdmin());
    $co = Company::first();
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-SF', 'status' => 'en_cours', 'quantity_requested' => 20, 'quantity_produced' => 18]);

    $b1 = app(BatchService::class)->createForOrder($of);
    expect($b1->batch_number)->toBe('LOT-OF-SF-01');
    expect((float) $b1->quantity)->toEqual(18.0); // quantity_produced
    $b2 = app(BatchService::class)->createForOrder($of, 5);
    expect($b2->batch_number)->toBe('LOT-OF-SF-02');
    expect((float) $b2->quantity)->toEqual(5.0);
});

it('never creates a zero-qty batch before production (falls back to requested)', function () {
    $this->actingAs(sfAdmin());
    $co = Company::first();
    // OF lancé mais rien produit encore : quantity_produced casté "0.00" (truthy).
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-SF0', 'status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 0]);

    // Formulaire sans quantité (null) → doit retomber sur la demande (100), pas 0.
    $b = app(BatchService::class)->createForOrder($of, null);
    expect((float) $b->quantity)->toEqual(100.0);
});

it('reconciles a zero-qty batch to produced quantity on close', function () {
    $this->actingAs(sfAdmin());
    $co = Company::first();
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-SF0B', 'status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 0]);

    // Lot resté à 0 (donnée héritée avant correctif).
    $batch = ProductionBatch::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'batch_number' => 'LOT-OLD-00',
        'quantity' => 0, 'status' => 'en_cours', 'produced_at' => now(),
    ]);

    // Production déclarée entre-temps, puis clôture du lot → réconcilié à 25.
    $of->update(['quantity_produced' => 25]);
    app(BatchService::class)->close($batch);

    expect($batch->fresh()->status)->toBe('cloture');
    expect((float) $batch->fresh()->quantity)->toEqual(25.0);
});

it('creates batch via route and closes it', function () {
    $this->actingAs(sfAdmin());
    $co = Company::first();
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-SF2', 'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 10]);

    $this->post(route('production.orders.batches', $of))->assertRedirect();
    $batch = ProductionBatch::where('production_order_id', $of->id)->first();
    expect($batch->status)->toBe('en_cours');

    $this->post(route('production.batches.close', $batch))->assertRedirect();
    expect($batch->fresh()->status)->toBe('cloture');
});
