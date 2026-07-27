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

it('GARDE QUANTITATIVE : bobine 10 000 reçue, 6 000 libérée, 2 000 consommée → 5 000 refusé, 4 000 accepté', function () {
    $ctx = coilQualSetup(6000, 4000);
    [$co, $wh, $p, $rec] = $ctx;
    $of = makeOf($ctx);

    // Bobine partiellement libérée : reçu 10 000 = libéré 6 000 + quarantaine 4 000.
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-Q-' . uniqid(), 'initial_weight' => 10000, 'remaining_weight' => 10000,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_PARTIAL_RELEASE,
        'qty_released' => 6000, 'qty_quarantine' => 4000, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 5000000, 'received_at' => now(),
    ]);
    expect($coil->availableReleasedQuantity())->toBe(6000.0);

    // Consommation de 2 000 (≤ 6 000 libéré) → acceptée.
    app(CoilConsumptionService::class)->consume($of, $coil, 2000.0, null, null);
    expect((float) $coil->fresh()->remaining_weight)->toBe(8000.0)
        ->and($coil->fresh()->availableReleasedQuantity())->toBe(4000.0); // 6000 − 2000

    // Demande de 5 000 : le poids restant (8 000) suffirait, mais le solde LIBÉRÉ
    // n'est que de 4 000 → REFUS malgré le statut « libéré partiellement ».
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil->fresh(), 5000.0, null, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect((float) $coil->fresh()->remaining_weight)->toBe(8000.0); // inchangé

    // 4 000 exactement → accepté (solde libéré épuisé).
    app(CoilConsumptionService::class)->consume($of, $coil->fresh(), 4000.0, null, null);
    expect($coil->fresh()->availableReleasedQuantity())->toBe(0.0)
        ->and((float) $coil->fresh()->remaining_weight)->toBe(4000.0); // la quarantaine reste
});

it('PROPAGATION CIBLÉE : une décision ne touche QUE la bobine visée, pas les autres de la réception', function () {
    $ctx = coilQualSetup(0, 60);
    [$co, $wh, $p, $rec, $item] = $ctx;

    $coilA = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-A-' . uniqid(), 'initial_weight' => 30, 'remaining_weight' => 30,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 30, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 15000, 'received_at' => now(),
    ]);
    $coilB = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-B-' . uniqid(), 'initial_weight' => 30, 'remaining_weight' => 30,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 30, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 15000, 'received_at' => now(),
    ]);

    // Libère 30 en ciblant UNIQUEMENT la bobine A.
    $d = app(PurchaseQualityService::class)->release($item, 30.0, [
        'reason'  => 'Bobine A conforme',
        'targets' => [['coil_id' => $coilA->id, 'quantity' => 30]],
    ]);

    expect($coilA->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and((float) $coilA->fresh()->qty_released)->toBe(30.0)
        ->and((float) $coilA->fresh()->qty_quarantine)->toBe(0.0)
        // Bobine B INTACTE : toujours en quarantaine, non consommable.
        ->and($coilB->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and((float) $coilB->fresh()->qty_quarantine)->toBe(30.0)
        ->and($coilB->fresh()->isQualityBlocked())->toBeTrue();

    // Allocation tracée sur la seule bobine A.
    $allocs = \Illuminate\Support\Facades\DB::table('purchase_quality_decision_allocations')
        ->where('quality_decision_id', $d->id)->get();
    expect($allocs)->toHaveCount(1)
        ->and((int) $allocs[0]->coil_id)->toBe($coilA->id)
        ->and((float) $allocs[0]->quantity)->toBe(30.0)
        ->and($allocs[0]->disposition_before)->toBe(Coil::QUALITY_QUARANTINED)
        ->and($allocs[0]->disposition_after)->toBe(Coil::QUALITY_RELEASED);
});

it('allocation supérieure à la quantité décidée : refusée', function () {
    $ctx = coilQualSetup(0, 60);
    [$co, $wh, $p, $rec, $item] = $ctx;
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-X-' . uniqid(), 'initial_weight' => 60, 'remaining_weight' => 60,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 60, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 30000, 'received_at' => now(),
    ]);

    expect(fn () => app(PurchaseQualityService::class)->release($item, 20.0, [
        'reason' => 'x', 'targets' => [['coil_id' => $coil->id, 'quantity' => 50]],
    ]))->toThrow(\RuntimeException::class, 'supérieures à la quantité décidée');

    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED);
});

it('décision qualité SANS cible : aucune bobine touchée (pas de mise à jour en bloc)', function () {
    $ctx = coilQualSetup(0, 40);
    [$co, $wh, $p, $rec, $item] = $ctx;
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-N-' . uniqid(), 'initial_weight' => 40, 'remaining_weight' => 40,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 40, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 20000, 'received_at' => now(),
    ]);

    app(PurchaseQualityService::class)->release($item, 40.0, ['reason' => 'Décision niveau ligne']);

    // La bobine n'est PAS modifiée : elle n'était pas ciblée.
    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and((float) $coil->fresh()->qty_quarantine)->toBe(40.0);
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

    // Libération TOTALE des 40 en quarantaine, CIBLANT explicitement bobine + lot.
    $coil->update(['qty_released' => 0, 'qty_quarantine' => 40, 'qty_rejected' => 0]);
    $lot->update(['qty_released' => 0, 'qty_quarantine' => 40, 'qty_rejected' => 0]);
    app(PurchaseQualityService::class)->release($item, 40.0, [
        'reason'  => 'Contrôle conforme',
        'targets' => [['coil_id' => $coil->id, 'stock_lot_id' => $lot->id, 'quantity' => 40]],
    ]);

    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($lot->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($coil->fresh()->isQualityBlocked())->toBeFalse()
        ->and((int) $coil->fresh()->quality_decision_id)->toBeGreaterThan(0);
});

it('RÈGLE A : libération PARTIELLE d\'une bobine indivise REFUSÉE (unité qualité indivisible)', function () {
    $ctx = coilQualSetup(60, 40);
    [, , , , $item] = $ctx;
    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);
    $coil->update(['qty_released' => 0, 'qty_quarantine' => 40, 'qty_rejected' => 0]);

    // 25 sur 40 : fraction d'une bobine physique → interdit sous règle A.
    expect(fn () => app(PurchaseQualityService::class)->release($item, 25.0, [
        'reason'  => 'Partiel',
        'targets' => [['coil_id' => $coil->id, 'quantity' => 25]],
    ]))->toThrow(\RuntimeException::class, 'indivisible');

    // Bobine intacte : toujours entièrement en quarantaine.
    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and((float) $coil->fresh()->qty_quarantine)->toBe(40.0)
        ->and((float) $coil->fresh()->qty_released)->toBe(0.0);

    // La TOTALITÉ (40) est en revanche acceptée.
    app(PurchaseQualityService::class)->release($item, 40.0, [
        'reason'  => 'Bobine entière conforme',
        'targets' => [['coil_id' => $coil->id, 'quantity' => 40]],
    ]);
    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and((float) $coil->fresh()->qty_released)->toBe(40.0);
});

it('DIVISION PHYSIQUE : bobine mère → filles traçables, poids réconciliés, mère au statut SPLIT', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;

    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-M-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    // Mère LIBÉRÉE avant division (statut historique à préserver).
    $mother->update(['quality_status' => Coil::QUALITY_RELEASED, 'qty_released' => 100, 'qty_quarantine' => 0]);

    // Découpe réelle : 70 + 25 filles + 5 chutes = 100.
    $children = app(\App\Modules\Production\Services\CoilSplitService::class)->split($mother, [
        ['weight' => 70],
        ['weight' => 25, 'quality_status' => Coil::QUALITY_REJECTED], // durcissement autorisé
    ], 5.0, 'Rives abîmées sur une partie du refendage');

    $m = $mother->fresh();
    expect($children)->toHaveCount(2)
        // [#3] Axe TRANSFORMATION = divisée.
        ->and($m->transformation_status)->toBe(Coil::TRANSFO_SPLIT)
        ->and($m->isSplit())->toBeTrue()
        // [#1] HISTORIQUE QUALITÉ PRÉSERVÉ (jamais remis à NULL).
        ->and($m->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($m->quality_status_before_transformation)->toBe(Coil::QUALITY_RELEASED)
        // [#2] POIDS HISTORIQUE PRÉSERVÉ ; seuls les soldes actifs à zéro.
        ->and((float) $m->initial_weight)->toBe(100.0)
        ->and((float) $m->remaining_weight)->toBe(0.0)
        ->and((float) $m->transferred_to_children_qty)->toBe(95.0)
        // [#3] COÛT HISTORIQUE PRÉSERVÉ.
        ->and((float) $m->cost_per_kg)->toBe(500.0)
        ->and((int) $m->purchase_price)->toBe(50000)
        // Mère non consommable / non réservable.
        ->and($m->isQualityBlocked())->toBeTrue()
        // Filles : disposition unique, traçabilité, coût transféré (non recalculé).
        ->and($children[0]->quality_status)->toBe(Coil::QUALITY_RELEASED) // héritage mère libérée
        ->and((float) $children[0]->initial_weight)->toBe(70.0)
        ->and((int) $children[0]->parent_coil_id)->toBe($mother->id)
        ->and((float) $children[0]->cost_per_kg)->toBe(500.0)
        ->and((int) $children[0]->purchase_price)->toBe(35000)      // 70 × 500
        ->and($children[1]->quality_status)->toBe(Coil::QUALITY_REJECTED);

    // [#4] Document de division append-only + réconciliation VALEUR.
    $op = \Illuminate\Support\Facades\DB::table('coil_split_operations')->where('coil_id', $mother->id)->first();
    expect($op)->not->toBeNull()
        ->and((float) $op->mother_qty_before)->toBe(100.0)
        ->and($op->mother_quality_status_before)->toBe(Coil::QUALITY_RELEASED)
        ->and((int) $op->mother_historical_cost)->toBe(50000)
        ->and((float) $op->scrap_qty)->toBe(5.0)
        ->and((int) $op->scrap_value)->toBe(2500)                    // 5 × 500
        ->and((int) $op->rounding_difference)->toBe(0);              // 35000+12500+2500 = 50000
    expect(\Illuminate\Support\Facades\DB::table('coil_split_operation_items')->where('split_operation_id', $op->id)->count())->toBe(2);

    // La fille conforme est consommable ; la fille refusée ne l'est pas.
    $of = makeOf($ctx);
    app(CoilConsumptionService::class)->consume($of, $children[0]->fresh(), 30.0, null, null);
    expect((float) $children[0]->fresh()->remaining_weight)->toBe(40.0);
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $children[1]->fresh(), 5.0, null, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('TRANSVERSE : mère libérée MAIS divisée — exclue des sélecteurs matière et de la valeur active', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-T-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_RELEASED,
        'valuation_status' => 'valorisation_definitive',
        'qty_released' => 100, 'qty_quarantine' => 0, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    // Avant division : utilisable, valeur active 50 000.
    expect(Coil::usableAsMaterial()->pluck('id')->contains($mother->id))->toBeTrue()
        ->and($mother->isConsumable())->toBeTrue()
        ->and($mother->activeInventoryValue())->toBe(50000.0);

    $children = app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 100]], 0.0);
    $m = $mother->fresh();
    $children[0]->update(['valuation_status' => 'valorisation_definitive']);

    // Après division : historiquement LIBÉRÉE, mais opérationnellement inactive.
    expect($m->isQualityReleased())->toBeTrue()          // historique conservé
        ->and($m->isOperationallyActive())->toBeFalse()  // mais inutilisable
        ->and($m->isConsumable())->toBeFalse()
        ->and($m->isReservable())->toBeFalse()
        ->and($m->isTransferable())->toBeFalse()
        ->and($m->availableQuantity())->toBe(0.0)
        // [#3] Pas de double valorisation : valeur ACTIVE nulle, coût HISTORIQUE conservé.
        ->and($m->activeInventoryValue())->toBe(0.0)
        ->and($m->historicalTotalCost())->toBe(50000)
        // Absente des sélecteurs matière ; la fille les remplace.
        ->and(Coil::usableAsMaterial()->pluck('id')->contains($mother->id))->toBeFalse()
        ->and(Coil::usableAsMaterial()->pluck('id')->contains($children[0]->id))->toBeTrue();

    // Valeur active totale = celle des filles seulement (pas mère + filles).
    $valeurActive = Coil::usableAsMaterial()->get()->sum(fn ($c) => $c->activeInventoryValue());
    expect($valeurActive)->toBe(50000.0); // et non 100 000

    // Mère toujours visible en généalogie/traçabilité.
    expect(Coil::find($mother->id))->not->toBeNull()
        ->and(Coil::where('parent_coil_id', $mother->id)->count())->toBe(1);
});

it('HASH : empreinte canonique STABLE et recalculable à l\'identique', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-HASH-1', 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    $svc = \App\Modules\Production\Services\CoilSplitService::class;
    $children = [['weight' => 60], ['weight' => 40]];

    $h1 = $svc::canonicalHash($mother, 100.0, $children, 0.0, 0.0, 0.001);
    $h2 = $svc::canonicalHash($mother, 100.0, $children, 0.0, 0.0, 0.001);
    expect($h1)->toBe($h2)->and(strlen($h1))->toBe(64);   // déterministe

    // Les décimales sont NORMALISÉES : 60 et 60.000 donnent la même empreinte.
    expect($svc::canonicalHash($mother, 100.0, [['weight' => 60.000], ['weight' => 40]], 0.0, 0.0, 0.001))->toBe($h1);

    // Toute variation économique change l'empreinte.
    expect($svc::canonicalHash($mother, 100.0, [['weight' => 61], ['weight' => 39]], 0.0, 0.0, 0.001))->not->toBe($h1);
    expect($svc::canonicalHash($mother, 100.0, $children, 5.0, 0.0, 0.001))->not->toBe($h1);

    // L'ordre des enfants est significatif (allocation ordonnée).
    expect($svc::canonicalHash($mother, 100.0, [['weight' => 40], ['weight' => 60]], 0.0, 0.0, 0.001))->not->toBe($h1);

    // Le hash persisté correspond au recalcul.
    app($svc)->split($mother, $children, 0.0);
    $op = \Illuminate\Support\Facades\DB::table('coil_split_operations')->where('coil_id', $mother->id)->first();
    expect($op->calculation_hash)->toBe($h1);
});

it('IDEMPOTENCE : même clé → une seule opération et mêmes enfants ; rejeu sans doublon', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-IDEM-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    $svc = app(\App\Modules\Production\Services\CoilSplitService::class);

    $first = $svc->split($mother, [['weight' => 60], ['weight' => 40]], 0.0, 'Découpe', ['idempotency_key' => 'SPLIT-1']);
    expect($first)->toHaveCount(2);

    // Rejeu même clé → mêmes enfants, aucune opération ni bobine supplémentaire.
    $replay = $svc->split($mother->fresh(), [['weight' => 60], ['weight' => 40]], 0.0, 'Découpe', ['idempotency_key' => 'SPLIT-1']);
    expect($replay)->toHaveCount(2)
        ->and(collect($replay)->pluck('id')->all())->toBe(collect($first)->pluck('id')->all())
        ->and(\Illuminate\Support\Facades\DB::table('coil_split_operations')->where('coil_id', $mother->id)->count())->toBe(1)
        ->and(Coil::where('parent_coil_id', $mother->id)->count())->toBe(2);
});

it('ROLLBACK transactionnel : erreur sur la DERNIÈRE fille → aucune fille ni opération persistée', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-RB-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    // Dernière fille à poids nul → refus ; la validation précède toute création.
    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 60], ['weight' => 40], ['weight' => 0]], 0.0))
        ->toThrow(\RuntimeException::class);

    // Rien n'est persisté : ni filles, ni opération ; mère intacte.
    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0)
        ->and(\Illuminate\Support\Facades\DB::table('coil_split_operations')->where('coil_id', $mother->id)->count())->toBe(0)
        ->and($mother->fresh()->transformation_status)->toBeNull()
        ->and((float) $mother->fresh()->remaining_weight)->toBe(100.0);
});

it('APPEND-ONLY : opération et lignes de division immuables, même par Eloquent direct', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-AO-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    app(\App\Modules\Production\Services\CoilSplitService::class)->split($mother, [['weight' => 100]], 0.0);

    $op   = \App\Modules\Production\Models\CoilSplitOperation::where('coil_id', $mother->id)->firstOrFail();
    $item = \App\Modules\Production\Models\CoilSplitOperationItem::where('split_operation_id', $op->id)->firstOrFail();

    // Modification refusée (poids, coût, méthode, hash…).
    expect(fn () => $op->update(['scrap_qty' => 99]))->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    expect(fn () => $op->update(['calculation_hash' => str_repeat('0', 64)]))->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    expect(fn () => $op->update(['allocation_method' => 'autre']))->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    // Suppression refusée.
    expect(fn () => $op->delete())->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    // Lignes : modification et suppression refusées.
    expect(fn () => $item->update(['weight' => 5]))->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    expect(fn () => $item->update(['transferred_cost' => 1]))->toThrow(\RuntimeException::class, 'APPEND-ONLY');
    expect(fn () => $item->delete())->toThrow(\RuntimeException::class, 'APPEND-ONLY');

    // Rien n'a bougé.
    $fresh = $op->fresh();
    expect((float) $fresh->scrap_qty)->toBe(0.0)
        ->and($fresh->allocation_method)->toBe('proportion_poids')
        ->and(\App\Modules\Production\Models\CoilSplitOperationItem::where('split_operation_id', $op->id)->count())->toBe(1)
        ->and((float) $item->fresh()->weight)->toBe(100.0);
});

it('COÛT RÉSIDUEL : bobine 100 kg / 50 000, 20 kg déjà consommés → seuls 40 000 sont répartis', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    // Bobine reçue 100 kg à 500 F/kg = 50 000 ; 20 kg déjà consommés (10 000).
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-RES-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 80,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_RELEASED,
        'qty_released' => 100, 'qty_quarantine' => 0, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    // Division du reliquat : 50 + 30 filles = 80.
    $children = app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 50], ['weight' => 30]], 0.0);

    $op = \App\Modules\Production\Models\CoilSplitOperation::where('coil_id', $mother->id)->firstOrFail();

    expect((float) $op->mother_initial_weight)->toBe(100.0)      // historique
        ->and((float) $op->mother_qty_before)->toBe(80.0)         // reliquat divisible
        ->and((float) $op->consumed_before_split)->toBe(20.0)     // déjà consommé
        ->and((int) $op->mother_historical_cost)->toBe(50000)     // coût historique total
        ->and((int) $op->consumed_cost_before_split)->toBe(10000) // valeur déjà consommée
        ->and((int) $op->residual_cost_before_split)->toBe(40000) // SEULE valeur répartissable
        ->and((int) $op->transferred_cost)->toBe(40000)           // 25 000 + 15 000
        ->and((int) $op->rounding_difference)->toBe(0);           // 40 000 − 40 000

    // Coûts des filles au coût HISTORIQUE de la mère (jamais le CMP courant).
    expect((int) $children[0]->purchase_price)->toBe(25000)       // 50 × 500
        ->and((int) $children[1]->purchase_price)->toBe(15000)    // 30 × 500
        ->and((float) $children[0]->cost_per_kg)->toBe(500.0);

    // La valeur déjà consommée (10 000) n'est PAS redistribuée.
    expect((int) $children[0]->purchase_price + (int) $children[1]->purchase_price)->toBe(40000);
});

it('HÉRITAGE : mère en quarantaine → filles en quarantaine, jamais libérées automatiquement', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-H1-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    $svc = app(\App\Modules\Production\Services\CoilSplitService::class);

    // Demander « libéré » pour une fille d'une mère en quarantaine → refus.
    expect(fn () => $svc->split($mother, [['weight' => 100, 'quality_status' => Coil::QUALITY_RELEASED]], 0.0))
        ->toThrow(\RuntimeException::class, 'moins restrictive');

    // Sans demande : la politique impose la quarantaine.
    $children = $svc->split($mother->fresh(), [['weight' => 60], ['weight' => 40]], 0.0);
    expect($children[0]->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and($children[1]->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        // Statut historique de la mère conservé.
        ->and($mother->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED);
});

it('HÉRITAGE : contrôle post-division requis → filles en quarantaine même si mère libérée', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-H2-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_RELEASED,
        'qty_released' => 100, 'qty_quarantine' => 0, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    $children = app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 100]], 0.0, 'Refendage', ['requires_post_split_qc' => true]);

    expect($children[0]->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and($children[0]->isQualityBlocked())->toBeTrue();
    $op = \Illuminate\Support\Facades\DB::table('coil_split_operations')->where('coil_id', $mother->id)->first();
    expect((bool) $op->requires_post_split_quality_control)->toBeTrue();
});

it('DIVISION : bobine REFUSÉE non divisible sans contre-décision', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-R-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_REJECTED,
        'qty_released' => 0, 'qty_quarantine' => 0, 'qty_rejected' => 100,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 100]], 0.0))
        ->toThrow(\RuntimeException::class, 'contre-décision');
});

it('DIVISION après consommation partielle : seul le SOLDE restant est divisible', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-P-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 60, // 40 déjà consommés
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_RELEASED,
        'qty_released' => 100, 'qty_quarantine' => 0, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    $svc = app(\App\Modules\Production\Services\CoilSplitService::class);

    // Diviser 100 (quantité historique) → refus : seul le solde 60 est divisible.
    expect(fn () => $svc->split($mother, [['weight' => 100]], 0.0))
        ->toThrow(\RuntimeException::class, 'divisible');

    // 60 = solde restant → accepté ; poids historique inchangé.
    $children = $svc->split($mother->fresh(), [['weight' => 60]], 0.0);
    expect((float) $children[0]->initial_weight)->toBe(60.0)
        ->and((float) $mother->fresh()->initial_weight)->toBe(100.0)   // historique intact
        ->and((float) $mother->fresh()->transferred_to_children_qty)->toBe(60.0);
});

it('DIVISION : seconde division de la même mère REFUSÉE (opération incompatible)', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-M3-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);
    $svc = app(\App\Modules\Production\Services\CoilSplitService::class);

    // Mère en quarantaine → filles en quarantaine (politique d'héritage).
    $svc->split($mother, [['weight' => 100]], 0.0);
    expect($mother->fresh()->transformation_status)->toBe(Coil::TRANSFO_SPLIT);

    // Seconde division : refusée (déjà divisée + filles existantes).
    expect(fn () => $svc->split($mother->fresh(), [['weight' => 50]], 50.0))
        ->toThrow(\RuntimeException::class);
    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(1);
});

it('DIVISION : poids non réconciliés → refus (Σ filles + chutes ≠ poids mère)', function () {
    $ctx = coilQualSetup(0, 100);
    [$co, $wh, $p, $rec] = $ctx;
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-M2-' . uniqid(), 'initial_weight' => 100, 'remaining_weight' => 100,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 100, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'received_at' => now(),
    ]);

    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)->split($mother, [
        ['weight' => 70],
    ], 5.0))->toThrow(\RuntimeException::class, 'tolérance de pesée');

    expect($mother->fresh()->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and((float) $mother->fresh()->remaining_weight)->toBe(100.0);
});

it('décision qualité : le REFUS marque bobine et lot comme REFUSÉS (non consommables)', function () {
    $ctx = coilQualSetup(60, 40);
    [, , , , $item] = $ctx;
    $coil = makeCoil($ctx, Coil::QUALITY_QUARANTINED);
    $coil->update(['qty_released' => 0, 'qty_quarantine' => 40, 'qty_rejected' => 0]);
    $of = makeOf($ctx);

    app(PurchaseQualityService::class)->rejectAfterControl($item, 40.0, [
        'reason'  => 'Non conforme',
        'targets' => [['coil_id' => $coil->id, 'quantity' => 40]],
    ]);

    expect($coil->fresh()->quality_status)->toBe(Coil::QUALITY_REJECTED)
        ->and($coil->fresh()->isQualityBlocked())->toBeTrue();
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil->fresh(), 5.0, null, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
