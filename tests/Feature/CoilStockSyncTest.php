<?php

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Reception;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\CoilReceptionService;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * Synchronisation coils ↔ stock_lots ↔ stock_movements ↔ product_stocks
 * (CDC 17/07/2026). Unité de tenue de stock des bobines : KILOGRAMME.
 * Une consommation = UN SEUL mouvement de sortie économique.
 */

function cslCompany(): \App\Models\Company
{
    return bfCompany();
}

function cslWarehouse(\App\Models\Company $company, string $code = 'DEP-CSL'): Warehouse
{
    return Warehouse::firstOrCreate(
        ['company_id' => $company->id, 'code' => $code],
        ['name' => 'Dépôt sync ' . $code, 'is_active' => true]
    );
}

function cslProduct(\App\Models\Company $company, array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'name'                => 'BOBINE SYNC TEST',
        'kg_per_linear_meter' => 1.7513,
    ], $overrides));
}

function cslCoil(\App\Models\Company $company, Product $product, Warehouse $wh, array $overrides = []): Coil
{
    return Coil::create(array_merge([
        'company_id'       => $company->id,
        'product_id'       => $product->id,
        'warehouse_id'     => $wh->id,
        'reference'        => 'BOB-SYNC-' . uniqid(),
        'initial_weight'   => 500,
        'remaining_weight' => 500,
        'estimated_length' => 0,
        'cost_per_kg'      => 800,
        'status'           => 'disponible',
    ], $overrides));
}

function cslLot(\App\Models\Company $company, Product $product, Warehouse $wh, float $qty = 1200): StockLot
{
    return StockLot::create([
        'product_id'       => $product->id,
        'warehouse_id'     => $wh->id,
        'lot_number'       => 'LOT-SYNC-' . uniqid(),
        'quantity'         => $qty,
        'initial_quantity' => $qty,
        'stock_uom'        => 'KG',
        'unit_cost'        => 800,
        'status'           => 'disponible',
    ]);
}

function cslStock(Product $product, Warehouse $wh, float $qty = 3000): ProductStock
{
    return ProductStock::create([
        'product_id'   => $product->id,
        'warehouse_id' => $wh->id,
        'quantity'     => $qty,
        'reserved_quantity' => 0,
        'avg_cost'     => 800,
    ]);
}

function cslOrder(\App\Models\Company $company, Product $pf): ProductionOrder
{
    return ProductionOrder::create([
        'company_id'         => $company->id,
        'number'             => 'OF-SYNC-' . uniqid(),
        'product_id'         => $pf->id,
        'quantity_requested' => 100,
        'status'             => 'en_cours',
    ]);
}

describe('Réception bobine → lot + stock', function () {

    it('crée le lot, la bobine, le mouvement d\'entrée KG et incrémente product_stocks', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $this->actingAs(bfAdmin());

        $reception = Reception::create([
            'company_id'   => $company->id,
            'number'       => 'REC-SYNC-001',
            'status'       => 'valide',
            'warehouse_id' => $wh->id,
            'received_at'  => now(),
            'validated_at' => now(),
        ]);
        $reception->items()->create([
            'product_id'        => $mp->id,
            'description'       => 'Bobine test réception',
            'expected_quantity' => 1000,
            'received_quantity' => 1000,
            'unit_cost'         => 800,
            'lot_number'        => 'LOT-FOURN-77',
        ]);

        $coils = app(CoilReceptionService::class)->createFromReception($reception->fresh());

        expect($coils)->toHaveCount(1);
        $coil = $coils[0]->fresh();

        expect((float) $coil->remaining_weight)->toBe(1000.0)
            ->and($coil->stock_lot_id)->not->toBeNull();

        $lot = StockLot::find($coil->stock_lot_id);
        expect((float) $lot->quantity)->toBe(1000.0)
            ->and((float) $lot->initial_quantity)->toBe(1000.0)
            ->and($lot->stock_uom)->toBe('KG');

        $movement = StockMovement::where('coil_id', $coil->id)->where('type', 'entree')->first();
        expect($movement)->not->toBeNull()
            ->and((float) $movement->quantity_in_stock_uom)->toBe(1000.0)
            ->and($movement->stock_uom)->toBe('KG')
            ->and($movement->stock_lot_id)->toBe($lot->id);

        expect((float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('quantity'))
            ->toBe(1000.0);
    });
});

describe('Consommation bobine synchronisée', function () {

    it('décrémente bobine + lot + product_stocks avec UN SEUL mouvement de sortie', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $pf      = cslProduct($company, ['name' => 'PF SYNC', 'kg_per_linear_meter' => null]);
        $this->actingAs(bfAdmin());

        $lot   = cslLot($company, $mp, $wh, 1200);
        $coil  = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id]);
        cslStock($mp, $wh, 3000);
        $order = cslOrder($company, $pf);

        $consumption = app(CoilConsumptionService::class)->consume($order, $coil, 120);

        expect((float) $coil->fresh()->remaining_weight)->toBe(380.0)
            ->and((float) $lot->fresh()->quantity)->toBe(1080.0)
            // [Analyse règle] 'partiellement_consomme' était HORS ENUM : SQLite le tolérait,
            // MySQL production le tronquait (Data truncated) — la consommation bobine cassait.
            // Règle corrigée : le reliquat d'un lot entamé reste 'disponible'.
            ->and($lot->fresh()->status)->toBe('disponible')
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2880.0);

        $movements = StockMovement::where('production_consumption_id', $consumption->id)->get();
        expect($movements)->toHaveCount(1)
            ->and($movements->first()->type)->toBe('sortie')
            ->and((float) $movements->first()->quantity_in_stock_uom)->toBe(120.0)
            ->and($movements->first()->stock_uom)->toBe('KG')
            ->and($movements->first()->stock_lot_id)->toBe($lot->id)
            ->and($movements->first()->coil_id)->toBe($coil->id)
            ->and($consumption->fresh()->stock_movement_id)->toBe($movements->first()->id);
    });

    it('convertit une saisie en mètres linéaires via le facteur kg/ML', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company); // facteur produit 1,7513
        $pf      = cslProduct($company, ['name' => 'PF ML', 'kg_per_linear_meter' => null]);
        $this->actingAs(bfAdmin());

        $lot  = cslLot($company, $mp, $wh, 1200);
        $coil = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id]);
        cslStock($mp, $wh, 3000);
        $order = cslOrder($company, $pf);

        // Saisie 100 ML, aucun poids : conversion attendue 175,13 KG.
        $consumption = app(CoilConsumptionService::class)->consume($order, $coil, 0, 100);

        expect((float) $consumption->weight_consumed)->toBe(175.13)
            ->and((float) $coil->fresh()->remaining_weight)->toBe(324.87)
            ->and((float) $lot->fresh()->quantity)->toBe(1024.87)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2824.87);

        $movement = $consumption->fresh()->stockMovement;
        expect((float) $movement->quantity)->toBe(100.0)
            ->and($movement->uom)->toBe('ML')
            ->and((float) $movement->conversion_factor)->toBe(1.7513)
            ->and((float) $movement->quantity_in_stock_uom)->toBe(175.13);
    });

    it('refuse une consommation supérieure au restant sans rien modifier', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $pf      = cslProduct($company, ['name' => 'PF OVER', 'kg_per_linear_meter' => null]);
        $this->actingAs(bfAdmin());

        $lot  = cslLot($company, $mp, $wh, 1200);
        $coil = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id]);
        cslStock($mp, $wh, 3000);
        $order = cslOrder($company, $pf);

        expect(fn () => app(CoilConsumptionService::class)->consume($order, $coil, 600))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        expect((float) $coil->fresh()->remaining_weight)->toBe(500.0)
            ->and((float) $lot->fresh()->quantity)->toBe(1200.0)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(3000.0)
            ->and(StockMovement::where('coil_id', $coil->id)->count())->toBe(0);
    });

    it('est idempotente : rejouer le même mouvement ne crée rien de plus', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $this->actingAs(bfAdmin());
        cslStock($mp, $wh, 1000);

        $svc = app(\App\Services\StockService::class);
        $payload = [
            'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'type' => 'sortie',
            'quantity' => 50, 'quantity_in_stock_uom' => 50, 'stock_uom' => 'KG',
            'idempotency_key' => 'test-idempotence-1',
        ];
        $m1 = $svc->recordMovement($payload);
        $m2 = $svc->recordMovement($payload);

        expect($m1->id)->toBe($m2->id)
            ->and(StockMovement::where('idempotency_key', 'test-idempotence-1')->count())->toBe(1)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(950.0);
    });
});

describe('Reverse de consommation', function () {

    it('restaure bobine + lot + product_stocks via un mouvement inverse traçable', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $pf      = cslProduct($company, ['name' => 'PF REV', 'kg_per_linear_meter' => null]);
        $this->actingAs(bfAdmin());

        $lot  = cslLot($company, $mp, $wh, 1200);
        $coil = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id]);
        cslStock($mp, $wh, 3000);
        $order = cslOrder($company, $pf);

        $svc = app(CoilConsumptionService::class);
        $consumption = $svc->consume($order, $coil, 200);
        $originalMovementId = $consumption->stock_movement_id;

        $svc->reverse($consumption->fresh(), 'test reverse');

        expect((float) $coil->fresh()->remaining_weight)->toBe(500.0)
            ->and((float) $lot->fresh()->quantity)->toBe(1200.0)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(3000.0)
            ->and($consumption->fresh()->reversed_at)->not->toBeNull();

        // Le mouvement initial reste consultable ; l'inverse pointe vers lui.
        expect(StockMovement::find($originalMovementId))->not->toBeNull();
        $reversal = StockMovement::where('reversal_of_movement_id', $originalMovementId)->first();
        expect($reversal)->not->toBeNull()
            ->and($reversal->type)->toBe('entree')
            ->and((float) $reversal->quantity_in_stock_uom)->toBe(200.0);

        // Double reverse refusé.
        expect(fn () => $svc->reverse($consumption->fresh()))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('Backflush au reliquat', function () {

    function cslBomSetup(): array
    {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $pf      = cslProduct($company, ['name' => 'PF BOM', 'kg_per_linear_meter' => null]);
        test()->actingAs(bfAdmin());

        cslStock($mp, $wh, 3000);
        $order = cslOrder($company, $pf);

        $bom = \App\Modules\Production\Models\BillOfMaterial::create([
            'company_id' => $company->id,
            'product_id' => $pf->id,
            'code'       => 'BOM-SYNC-' . uniqid(),
            'name'       => 'Nomenclature sync test',
            'version'    => 'V1',
            'is_active'  => true,
        ]);
        $bom->lines()->create([
            'product_id'         => $mp->id,
            'quantity_per_meter' => 5, // 5 kg de MP par unité produite
            'warehouse_id'       => $wh->id,
        ]);
        $order->update(['bill_of_material_id' => $bom->id]);

        return [$company, $wh, $mp, $pf, $order->fresh()];
    }

    it('backflushe la totalité du besoin sans consommation réelle', function () {
        [, $wh, $mp, , $order] = cslBomSetup();

        app(\App\Modules\Production\Services\ProductionStockService::class)
            ->recordOutput($order, ['quantity' => 100, 'warehouse_id' => $wh->id]);

        // Besoin = 5 × 100 = 500 kg backflushés.
        expect((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2500.0);
    });

    it('ne backflushe que le reliquat quand une consommation réelle partielle existe', function () {
        [$company, $wh, $mp, , $order] = cslBomSetup();

        $lot  = cslLot($company, $mp, $wh, 1200);
        $coil = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id]);

        // Consommation réelle : 420 kg (sort déjà du stock).
        app(CoilConsumptionService::class)->consume($order, $coil, 420);
        expect((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2580.0);

        // Déclaration 100 unités → besoin 500 kg ; reliquat backflush = 80 kg.
        app(\App\Modules\Production\Services\ProductionStockService::class)
            ->recordOutput($order->fresh(), ['quantity' => 100, 'warehouse_id' => $wh->id]);

        // Total sorti = 420 (réel) + 80 (reliquat) = 500 kg exactement.
        expect((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2500.0);

        $backflush = StockMovement::where('product_id', $mp->id)
            ->where('type', 'sortie')->whereNull('production_consumption_id')->get();
        expect($backflush)->toHaveCount(1)
            ->and((float) $backflush->first()->quantity_in_stock_uom)->toBe(80.0);
    });

    it('ne crée aucune double sortie quand la consommation réelle couvre tout le besoin', function () {
        [$company, $wh, $mp, , $order] = cslBomSetup();

        $lot  = cslLot($company, $mp, $wh, 1200);
        $coil = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id, 'initial_weight' => 600, 'remaining_weight' => 600]);

        // Consommation réelle 500 kg = besoin complet des 100 unités.
        app(CoilConsumptionService::class)->consume($order, $coil, 500);

        app(\App\Modules\Production\Services\ProductionStockService::class)
            ->recordOutput($order->fresh(), ['quantity' => 100, 'warehouse_id' => $wh->id]);

        // Une seule sortie MP (la réelle) — pas de backflush.
        expect(StockMovement::where('product_id', $mp->id)->where('type', 'sortie')->count())->toBe(1)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(2500.0);
    });
});

describe('Cohérence multi-bobines / multi-lots', function () {

    it('consomme sur une bobine d\'un lot multi-bobines sans toucher les autres', function () {
        $company = cslCompany();
        $wh      = cslWarehouse($company);
        $mp      = cslProduct($company);
        $pf      = cslProduct($company, ['name' => 'PF MULTI', 'kg_per_linear_meter' => null]);
        $this->actingAs(bfAdmin());

        $lot   = cslLot($company, $mp, $wh, 1500);
        $coilA = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id, 'initial_weight' => 1000, 'remaining_weight' => 1000]);
        $coilB = cslCoil($company, $mp, $wh, ['stock_lot_id' => $lot->id, 'initial_weight' => 500, 'remaining_weight' => 500]);
        cslStock($mp, $wh, 1500);
        $order = cslOrder($company, $pf);

        app(CoilConsumptionService::class)->consume($order, $coilA, 200);

        expect((float) $coilA->fresh()->remaining_weight)->toBe(800.0)
            ->and((float) $coilB->fresh()->remaining_weight)->toBe(500.0)
            ->and((float) $lot->fresh()->quantity)->toBe(1300.0)
            ->and((float) ProductStock::where('product_id', $mp->id)->value('quantity'))->toBe(1300.0);
    });
});
