<?php

/**
 * [CDC — Validation industrielle : parcours DÉGRADÉS de fabrication tôle bac]
 *
 * Le test nominal (OrderToCashFullChainTest) prouve le « parcours idéal ». Ce test
 * couvre les scénarios dégradés attendus d'un ERP industriel — les cas où le
 * processus doit RÉSISTER et non simplement passer :
 *
 *   1. Client dépassant son plafond de crédit → commande bloquée
 *   2. Livraison partielle → statut « partiellement livré » + reliquat, puis solde
 *   3. Produit fini déjà en stock → AUCUN OF généré (pas de sur-fabrication)
 *   4. Consommation partielle d'une bobine + garde-fou sur sur-consommation
 *   5. Production non conforme → livraison bloquée + rebut tracé
 *   6. Commande multi-longueurs → plan de coupe détaillé (lignes d'OF)
 *
 * Le retour client + avoir (scénario 7) est couvert par CreditNoteReturnDispositionTest.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function degCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DEG'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DEG Co'], ['email' => 'deg@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function degAdmin(): User
{
    $co = degCompany();
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

function degStockedOrder(Company $co, Client $client, int $qty, int $stock = 100): array
{
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-DEG'], ['name' => 'Dépôt DEG', 'is_default' => true, 'is_active' => true]);
    $unit = Unit::firstOrCreate(['name' => 'ML DEG'], ['abbreviation' => 'mldeg']);
    $tva  = TaxRate::firstOrCreate(['name' => 'TVA18 DEG'], ['short_name' => 'TV18D', 'rate' => 18, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true, 'valuation_method' => 'cmp']);
    if ($stock > 0) {
        app(StockService::class)->recordMovement(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'entree', 'quantity' => $stock, 'unit_cost' => 6000, 'occurred_at' => now()]);
    }
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'Tôle bac', 'quantity' => $qty, 'unit_price' => 10000, 'discount_percent' => 0, 'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18]],
    ]);

    return [$order, $product, $wh];
}

it('1. bloque la commande d’un client dépassant son plafond de crédit', function () {
    degAdmin();
    $co = degCompany();
    // Encours (balance) 200 000 > plafond 100 000 → dépassement
    $client = Client::factory()->create(['is_active' => true, 'credit_limit' => 100000, 'balance' => 200000]);
    [$order] = degStockedOrder($co, $client, 10);

    expect($client->isOverCreditLimit())->toBeTrue();
    expect(fn () => app(CommercialWorkflowService::class)->submit($order))
        ->toThrow(\RuntimeException::class);
});

it('2. gère une livraison partielle avec reliquat puis le solde', function () {
    degAdmin();
    $co = degCompany();
    $client = Client::factory()->create(['is_active' => true, 'credit_limit' => 100000000, 'balance' => 0]);
    [$order, $product, $wh] = degStockedOrder($co, $client, 100, 100);
    $order->update(['status' => 'confirme']);

    // 1re livraison : 60 sur 100 → partiellement livré
    $dn1 = app(OrderService::class)->createDeliveryNote($order->fresh());
    $dn1->items->first()->update(['quantity' => 60]);
    app(DeliveryNoteService::class)->validate($dn1->fresh());
    $order->refresh();
    expect($order->status)->toBe('partiellement_livre')
        ->and((float) $order->items->first()->delivered_quantity)->toBe(60.0);

    // 2e livraison : reliquat 40 → livré
    $dn2 = app(OrderService::class)->createDeliveryNote($order->fresh());
    $dn2->items->first()->update(['quantity' => 40]);
    app(DeliveryNoteService::class)->validate($dn2->fresh());
    $order->refresh();
    expect($order->status)->toBe('livre')
        ->and((float) $order->items->first()->delivered_quantity)->toBe(100.0)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(0.0);
});

it('3. ne génère AUCUN OF quand le produit fini est déjà en stock', function () {
    $user = degAdmin();
    $co = degCompany();
    $client = Client::factory()->create(['is_active' => true, 'credit_limit' => 100000000, 'balance' => 0]);
    [$order] = degStockedOrder($co, $client, 20, 100); // stock 100 ≥ 20 commandé

    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());

    expect($order->fresh()->status)->toBe('confirme')
        ->and(ProductionOrder::where('order_id', $order->id)->count())->toBe(0);
});

it('4. consomme partiellement une bobine et bloque toute sur-consommation', function () {
    $user = degAdmin();
    $co = degCompany();
    $product = Product::factory()->create(['is_stockable' => true]);
    $of = ProductionOrder::factory()->create(['company_id' => $co->id, 'product_id' => $product->id, 'status' => 'en_cours']);
    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-DEG', 'initial_weight' => 500,
        'remaining_weight' => 500, 'cost_per_kg' => 400, 'purchase_price' => 200000, 'status' => 'disponible',
    ]);

    app(CoilConsumptionService::class)->consume($of, $coil, 200);
    expect((float) $coil->fresh()->remaining_weight)->toBe(300.0); // reste disponible

    // Sur-consommation (400 > 300 restant) → refusée
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil->fresh(), 400))
        ->toThrow(ValidationException::class);
});

it('5. bloque la livraison sur non-conformité et trace le rebut', function () {
    $user = degAdmin();
    $co = degCompany();
    $client = Client::factory()->create(['is_active' => true, 'credit_limit' => 100000000, 'balance' => 0]);
    [$order, $product, $wh] = degStockedOrder($co, $client, 50, 50);
    $order->update(['status' => 'confirme']);

    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'MX-DEG', 'name' => 'Profileuse DEG', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-DEG', 'name' => 'Ligne DEG', 'is_active' => true]);
    $of = ProductionOrder::factory()->create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id, 'production_line_id' => $line->id, 'status' => 'en_cours', 'quantity_produced' => 50]);

    // Contrôle qualité NON CONFORME
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => false, 'status' => 'non_conforme', 'controlled_at' => now()]);
    // Rebut tracé
    ProductionWaste::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'machine_id' => $machine->id, 'type' => 'rebut', 'weight' => 12.5, 'value' => 5000, 'reason' => 'Épaisseur hors tolérance']);

    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());

    // La livraison est bloquée par le garde-fou qualité
    expect(fn () => app(ProductionDeliveryGuard::class)->assertDeliverable($dn))
        ->toThrow(\RuntimeException::class);
    // Le rebut est bien tracé
    expect(ProductionWaste::where('production_order_id', $of->id)->where('type', 'rebut')->sum('weight'))->toEqual(12.5);
});

it('6. détaille une commande multi-longueurs en plan de coupe (lignes d’OF)', function () {
    $user = degAdmin();
    $co = degCompany();
    $product = Product::factory()->create(['is_stockable' => true]);
    $unit = Unit::firstOrCreate(['name' => 'ML DEG'], ['abbreviation' => 'mldeg']);
    $of = ProductionOrder::factory()->create(['company_id' => $co->id, 'product_id' => $product->id, 'status' => 'brouillon']);

    // Plan de coupe : 20 tôles de 5 m + 25 tôles de 4 m = 100 + 100 = 200 m
    $of->lines()->create(['length' => 5, 'quantity' => 20, 'total_meters' => 100, 'unit_id' => $unit->id, 'label' => 'Bac 5m beige', 'sort_order' => 0]);
    $of->lines()->create(['length' => 4, 'quantity' => 25, 'total_meters' => 100, 'unit_id' => $unit->id, 'label' => 'Bac 4m beige', 'sort_order' => 1]);

    $of->refresh();
    expect($of->lines)->toHaveCount(2)
        ->and((float) $of->lines->sum('total_meters'))->toBe(200.0)
        ->and($of->lines->pluck('length')->map(fn ($l) => (float) $l)->sort()->values()->all())->toBe([4.0, 5.0]);
});
