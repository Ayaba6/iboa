<?php

/**
 * [ACHATS Qualité #11/#12] La quarantaine est une DISPOSITION transversale :
 * elle vit sur la ligne de réception, le lot ET la bobine — pas seulement dans
 * le dépôt DEP-QUAR. Une bobine non libérée n'est JAMAIS consommable
 * (QUARANTINED → CONSUMED interdit). Données créées en base de test.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Reception;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Services\PurchaseQualityService;
use App\Services\PurchaseReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function coilQualSetup(float $accepted, float $quarantine): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CQ'], ['email' => 'cq@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $wh   = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-CQ'], ['name' => 'Dépôt', 'is_default' => true, 'is_active' => true, 'can_purchase' => true]);
    Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-QUAR'], ['name' => 'Quarantaine', 'is_active' => true]);
    $sup = Supplier::factory()->create();
    $p   = Product::factory()->create(['is_stockable' => true, 'controle_qualite' => true]);

    $rec = Reception::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'number' => 'RCQ-' . uniqid(),
        'status' => 'brouillon', 'received_at' => now(), 'created_by' => $u->id,
    ]);
    $item = $rec->items()->create([
        'product_id' => $p->id, 'description' => 'Bobine QC',
        'expected_quantity' => $accepted + $quarantine, 'received_quantity' => 0,
        'rejected_quantity' => 0, 'unit_cost' => 1000,
    ]);

    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $item->id => [
            'received_quantity'   => $accepted + $quarantine,
            'accepted_quantity'   => $accepted,
            'quarantine_quantity' => $quarantine,
            'refused_quantity'    => 0,
        ],
    ]);

    return [$co, $wh, $p, $rec->fresh(), $item->fresh(), $u];
}

function makeCoil(array $ctx, ?string $qualityStatus, float $weight = 100): Coil
{
    [$co, $wh, $p, $rec] = $ctx;

    return Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-' . uniqid(), 'initial_weight' => $weight, 'remaining_weight' => $weight,
        'status' => 'disponible', 'quality_status' => $qualityStatus,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => (int) ($weight * 500),
        'received_at' => now(),
    ]);
}

function makeOf(array $ctx): ProductionOrder
{
    [$co, , $p] = $ctx;
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'number' => 'OF-CQ-' . uniqid(),
        'status' => 'en_cours', 'product_id' => $pf->id, 'quantity_requested' => 1, 'quantity_produced' => 0,
    ]);
}

it('bobine EN QUARANTAINE : consommation production REFUSÉE (QUARANTINED → CONSUMED interdit)', function () {
    $ctx  = coilQualSetup(60, 40);
    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);
    $of   = makeOf($ctx);

    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil, 10.0, null, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Bobine intacte : aucune consommation enregistrée.
    expect((float) $coil->fresh()->remaining_weight)->toBe(100.0)
        ->and($of->consumptions()->count())->toBe(0);
});

it('bobine REFUSÉE ou en RETOUR : consommation également refusée', function () {
    $ctx = coilQualSetup(60, 40);
    $of  = makeOf($ctx);

    foreach ([Coil::QUALITY_REJECTED, Coil::QUALITY_RETURN_PENDING, Coil::QUALITY_RECEIVED] as $status) {
        $coil = makeCoil($ctx, $status);
        expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil, 5.0, null, null))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
        expect((float) $coil->fresh()->remaining_weight)->toBe(100.0);
    }
});

it('bobine LIBÉRÉE : consommation autorisée', function () {
    $ctx  = coilQualSetup(100, 0);
    $coil = makeCoil($ctx, Coil::QUALITY_RELEASED);
    $of   = makeOf($ctx);

    app(CoilConsumptionService::class)->consume($of, $coil, 30.0, null, null);

    expect((float) $coil->fresh()->remaining_weight)->toBe(70.0)   // 100 − 30
        ->and($of->consumptions()->count())->toBe(1);
});

it('bobine HISTORIQUE (statut qualité NULL) : consommable mais jamais présentée comme libérée', function () {
    $ctx  = coilQualSetup(100, 0);
    $coil = makeCoil($ctx, null); // héritage : statut inconnu
    $of   = makeOf($ctx);

    app(CoilConsumptionService::class)->consume($of, $coil, 10.0, null, null);

    expect((float) $coil->fresh()->remaining_weight)->toBe(90.0)
        ->and($coil->fresh()->quality_status)->toBeNull()   // reste INCONNU
        ->and($coil->fresh()->isQualityBlocked())->toBeFalse();
});

it('décision qualité : la libération propage le statut au LOT et aux BOBINES de la réception', function () {
    $ctx = coilQualSetup(60, 40);
    [$co, $wh, $p, $rec, $item] = $ctx;

    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);
    $lot  = StockLot::create([
        'product_id' => $p->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-CQ-' . uniqid(),
        'quantity' => 40, 'initial_quantity' => 40, 'reserved_quantity' => 0, 'stock_uom' => 'KG',
        'unit_cost' => 500, 'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'source_type' => Reception::class, 'source_id' => $rec->id,
    ]);

    // Libération TOTALE des 40 en quarantaine.
    app(PurchaseQualityService::class)->release($item, 40.0, ['reason' => 'Contrôle conforme']);

    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($lot->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($coil->fresh()->isQualityBlocked())->toBeFalse()
        ->and((int) $coil->fresh()->quality_decision_id)->toBeGreaterThan(0);
});

it('décision qualité : libération PARTIELLE laisse bobine et lot en libération partielle (encore bloqués)', function () {
    $ctx = coilQualSetup(60, 40);
    [, , , , $item] = $ctx;
    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);

    app(PurchaseQualityService::class)->release($item, 25.0, ['reason' => 'Partiel']);

    // 15 restent en quarantaine → statut « libéré partiellement », non bloquant
    // pour la part libérée mais tracé comme incomplet.
    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_PARTIAL_RELEASE);
});

it('décision qualité : le REFUS marque bobine et lot comme REFUSÉS (non consommables)', function () {
    $ctx = coilQualSetup(60, 40);
    [, , , , $item] = $ctx;
    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);
    $of   = makeOf($ctx);

    app(PurchaseQualityService::class)->rejectAfterControl($item, 40.0, ['reason' => 'Non conforme']);

    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_REJECTED)
        ->and($coil->fresh()->isQualityBlocked())->toBeTrue();
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil->fresh(), 5.0, null, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
