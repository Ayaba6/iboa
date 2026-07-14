<?php

/**
 * [FIX A2/A3/A4 — rapport de test MTO]
 *
 *  A2 — l'opérateur (production.declare) peut consommer/déclarer/saisir les chutes ;
 *       les corrections (annulations) restent réservées à production.update.
 *  A3 — le responsable qualité (quality.manage) peut enregistrer le contrôle qualité de l'OF.
 *  A4 — l'allocation matière pose une réservation FERME du composant BOM (bloque le
 *       disponible pour un autre OF) ; le backflush libère la réservation avant la sortie.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function perSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PER'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PER Co'], ['email' => 'per@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    foreach (['production.view', 'production.declare', 'production.update', 'quality.manage'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $mk = function (string $role, array $perms) use ($co) {
        $r = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $r->syncPermissions($perms);
        $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
        $u->assignRole($r);
        return $u;
    };

    return [
        'co'        => $co,
        'operateur' => $mk('per_operateur', ['production.view', 'production.declare']),
        'qualite'   => $mk('per_qualite', ['production.view', 'quality.manage']),
    ];
}

function perOrder(Company $co, bool $withBom = false): array
{
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-PER'], [
        'name' => 'WH PER', 'is_active' => true, 'is_default' => true,
        'can_production' => true, 'can_stock' => true, 'can_sale' => true, 'can_purchase' => true,
    ]);
    $finished  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $component = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::firstOrCreate(['product_id' => $component->id, 'warehouse_id' => $wh->id],
        ['quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 800]);

    $bomId = null;
    if ($withBom) {
        $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $finished->id, 'name' => 'BOM PER', 'is_active' => true]);
        $bom->lines()->create(['product_id' => $component->id, 'quantity_per_meter' => 2, 'waste_rate' => 0, 'sort_order' => 1, 'depot_sortie_id' => $wh->id]);
        $bomId = $bom->id;
    }

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-PER-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => $finished->id, 'bill_of_material_id' => $bomId,
    ]);

    return [$order, $finished, $component, $wh];
}

it('A2 — l’opérateur déclare production et chutes, mais ne peut pas annuler', function () {
    $ctx = perSetup();
    [$order, , , $wh] = perOrder($ctx['co']);
    $order->update(['status' => 'en_cours']);

    $this->actingAs($ctx['operateur']);

    // Déclaration de production : AUTORISÉE
    $this->post(route('production.orders.output', $order), [
        'quantity' => 10, 'length' => 1, 'warehouse_id' => $wh->id,
    ])->assertRedirect();
    expect($order->outputs()->count())->toBe(1);

    // Chute : AUTORISÉE
    $this->post(route('production.orders.waste', $order), [
        'type' => 'rebut', 'weight' => 1.5, 'value' => 500, 'reason' => 'Réglage',
    ])->assertRedirect();
    expect($order->wastes()->count())->toBe(1);

    // Annulation de la déclaration : REFUSÉE (correction = encadrement)
    $output = $order->outputs()->first();
    $this->delete(route('production.outputs.destroy', $output))->assertForbidden();
});

it('A3 — le responsable qualité enregistre le contrôle qualité de l’OF', function () {
    $ctx = perSetup();
    [$order] = perOrder($ctx['co']);
    $order->update(['status' => 'en_cours']);

    $this->actingAs($ctx['qualite']);

    $this->post(route('production.orders.quality', $order), [
        'status' => 'conforme', 'thickness_ok' => 1, 'length_ok' => 1, 'color_ok' => 1, 'visual_ok' => 1,
    ])->assertRedirect();

    expect($order->qualityControls()->count())->toBe(1)
        ->and($order->qualityControls()->first()->status)->toBe('conforme');
});

it('A4 — l’allocation réserve fermement la matière et le backflush la libère', function () {
    $ctx = perSetup();
    [$order, , $component, $wh] = perOrder($ctx['co'], withBom: true);
    $this->actingAs($ctx['operateur']);

    // Allocation → réservation ferme : besoin = 10 × 2 = 20
    app(ProductionService::class)->allocateMaterial($order);

    $stock = ProductStock::where('product_id', $component->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->reserved_quantity)->toBe(20.0);
    $resa = StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->first();
    expect($resa)->not->toBeNull()
        ->and((float) $resa->quantity)->toBe(20.0);

    // Un AUTRE OF ne voit plus que 80 de disponible (100 − 20 réservés)
    expect((float) $stock->quantity - (float) $stock->reserved_quantity)->toBe(80.0);

    // Déclaration (backflush 20) : la réservation est libérée AVANT la sortie
    $order->update(['status' => 'en_cours']);
    app(ProductionStockService::class)->recordOutput($order->fresh(), [
        'quantity' => 10, 'length' => 1, 'warehouse_id' => $wh->id,
    ]);

    $stock->refresh();
    expect((float) $stock->reserved_quantity)->toBe(0.0)   // réservation libérée
        ->and((float) $stock->quantity)->toBe(80.0);       // 100 − 20 backflushés
    expect(StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->count())->toBe(0);
});

it('A4 — l’annulation d’un OF alloué libère la réservation matière', function () {
    $ctx = perSetup();
    [$order, , $component, $wh] = perOrder($ctx['co'], withBom: true);
    $this->actingAs($ctx['operateur']);

    app(ProductionService::class)->allocateMaterial($order);
    expect((float) ProductStock::where('product_id', $component->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))->toBe(20.0);

    app(ProductionService::class)->cancel($order->fresh(), 'Test annulation');

    expect((float) ProductStock::where('product_id', $component->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))->toBe(0.0);
});
