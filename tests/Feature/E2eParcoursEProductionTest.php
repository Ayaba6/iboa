<?php

/**
 * [Ultimatum — Parcours E : production avec anomalie] Attendus INDÉPENDANTS.
 * Bobines : B1 10 kg à 500 F/kg, B2 8 kg à 500 F/kg.
 *  - sur-consommation (12 kg sur B1 de 10) → refus, bobine intacte ;
 *  - multi-bobines : 6 kg B1 + 4 kg B2 → restants 4 et 4, conso totale 10 kg
 *    valorisée 10 × 500 = 5 000 ;
 *  - production partielle 3/5 + clôture SANS force → refus « Terminer
 *    partiellement » ; clôture AVEC écart assumé (force) → termine ;
 *  - QC non conforme → livraison BLOQUÉE même avec du stock PF ;
 *  - clôture sans visa chef d'équipe → refus.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function prodSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PRD'], ['email' => 'prd@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-PRD'], ['name' => 'Dépôt PRD', 'is_default' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true]);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'number' => 'OF-E-' . uniqid(),
        'status' => 'en_cours', 'product_id' => $pf->id,
        'quantity_requested' => 5, 'quantity_produced' => 0,
    ]);
    $b1 = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'B1-' . uniqid(),
        'initial_weight' => 10, 'remaining_weight' => 10, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 5000, 'received_at' => now(),
    ]);
    $b2 = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'B2-' . uniqid(),
        'initial_weight' => 8, 'remaining_weight' => 8, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 5000, 'received_at' => now(),
    ]);

    return [$co, $wh, $mp, $pf, $of, $b1, $b2];
}

it('E-sur-consommation : 12 kg sur une bobine de 10 → refus, bobine intacte', function () {
    [, , , , $of, $b1] = prodSetup();

    try {
        app(CoilConsumptionService::class)->consume($of, $b1, 12.0, null, null);
        $this->fail('La sur-consommation aurait dû être refusée.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect(implode(' ', array_map(fn ($m) => implode(' ', $m), $e->errors())))->toContain('supérieur');
    }
    expect((float) $b1->fresh()->remaining_weight)->toBe(10.0)
        ->and($of->consumptions()->count())->toBe(0);
});

it('E-multi-bobines : 6 kg B1 + 4 kg B2, restants exacts, conso totale 10 kg', function () {
    [, , , , $of, $b1, $b2] = prodSetup();
    $svc = app(CoilConsumptionService::class);

    $svc->consume($of, $b1, 6.0, null, null);
    $svc->consume($of, $b2, 4.0, null, null);

    expect((float) $b1->fresh()->remaining_weight)->toBe(4.0)   // 10 − 6
        ->and((float) $b2->fresh()->remaining_weight)->toBe(4.0) // 8 − 4
        ->and($b1->fresh()->status)->toBe('en_production')
        ->and($of->consumptions()->count())->toBe(2)
        ->and((float) $of->consumptions()->sum('weight_consumed'))->toBe(10.0);
});

it('E-clôture avec écart : 3/5 produites — refus sans dérogation, accepté avec force', function () {
    [$co, $wh, , $pf, $of] = prodSetup();

    // Déclaration partielle 3/5, visée
    $out = $of->outputs()->create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'length' => 6, 'quantity' => 3,
        'total_meters' => 18, 'warehouse_id' => $wh->id, 'produced_at' => now(), 'status' => 'validee',
    ]);
    $of->update(['quantity_produced' => 3]);

    // Sans force : refus explicite avec guidage
    try {
        app(ProductionService::class)->finish($of->fresh());
        $this->fail('La clôture avec écart sans dérogation aurait dû être refusée.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect(implode(' ', array_map(fn ($m) => implode(' ', $m), $e->errors())))->toContain('inférieure');
    }
    expect($of->fresh()->status)->toBe('en_cours');

    // Avec écart assumé (force) : clôturé
    app(ProductionService::class)->finish($of->fresh(), force: true);
    expect($of->fresh()->status)->toBe('termine');
});

it('E-clôture sans visa : déclaration non visée → refus', function () {
    [$co, $wh, , $pf, $of] = prodSetup();
    $of->outputs()->create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'length' => 6, 'quantity' => 5,
        'total_meters' => 30, 'warehouse_id' => $wh->id, 'produced_at' => now(), 'status' => 'declaree',
    ]);
    $of->update(['quantity_produced' => 5]);

    try {
        app(ProductionService::class)->finish($of->fresh());
        $this->fail('La clôture sans visa aurait dû être refusée.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect(implode(' ', array_map(fn ($m) => implode(' ', $m), $e->errors())))->toContain('visa');
    }
});

it('E-QC non conforme : livraison BLOQUÉE même avec du stock PF en face', function () {
    [$co, $wh, , $pf, $of] = prodSetup();
    // OF terminé avec 5 produites ET stock PF réel — mais QC NON CONFORME
    $of->outputs()->create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'length' => 6, 'quantity' => 5,
        'total_meters' => 30, 'warehouse_id' => $wh->id, 'produced_at' => now(), 'status' => 'validee',
    ]);
    $of->update(['quantity_produced' => 5, 'status' => 'termine', 'finished_at' => now()]);
    $of->qualityControls()->create([
        'company_id' => $co->id, 'status' => 'non_conforme', 'reason' => 'Épaisseur hors tolérance',
        'thickness_ok' => false, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'controlled_at' => now(),
    ]);
    ProductStock::create(['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $client = Client::factory()->create();
    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-NC-' . uniqid(), 'status' => 'confirme', 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $pf->id, 'description' => 'PF', 'quantity' => 5, 'unit_price' => 2000,
        'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000,
    ]);
    $of->update(['order_id' => $order->id]);

    $dn = \App\Models\DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-NC-' . uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create(['product_id' => $pf->id, 'description' => 'PF', 'quantity' => 5, 'unit_price' => 2000]);

    try {
        app(\App\Services\DeliveryNoteService::class)->validate($dn);
        $this->fail('La livraison de production NON CONFORME aurait dû être bloquée.');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('non conforme');
    }
    // Stock intact : la non-conformité n'est JAMAIS vendable sans décision qualité
    expect((float) ProductStock::where('product_id', $pf->id)->value('quantity'))->toBe(5.0)
        ->and($dn->fresh()->status)->toBe('brouillon');
});
