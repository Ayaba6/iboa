<?php

/**
 * [Workflow Vente → Production Fer à béton] Chaîne tréfilage, complémentaire de
 * ToleBacWorkflowTest — valide la généralisation de la correction stock/dépôt sur
 * un autre produit + gamme :
 *   - déclaration PF SANS warehouse_id explicite → doit atterrir dans le dépôt
 *     article (main_warehouse_id) et non le dépôt société par défaut (fix dépôt PF) ;
 *   - livraison dont la réservation pointe un dépôt ≠ dépôt livré → la validation
 *     du BL réaligne et consomme la réservation au lieu de se bloquer sur
 *     « Stock insuffisant » (fix réservation/dépôt).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function fabAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'FAB-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'FAB Co'], ['email' => 'fab@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('parcourt Vente → Production fer à béton : dépôt PF par défaut + réservation dépôt réalignée à la livraison', function () {
    $user    = fabAdmin();
    $company = Company::first();
    $this->actingAs($user);

    $client  = Client::factory()->create(['is_active' => true, 'payment_mode' => 'comptant']);
    $unit    = Unit::firstOrCreate(['name' => 'Barre FAB'], ['abbreviation' => 'bfab']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% FAB'], ['short_name' => 'TVA18F', 'rate' => 18, 'is_active' => true]);

    // Deux dépôts : Central (MP / défaut société) + Produits Finis (PF fer).
    $central = Warehouse::firstOrCreate(['code' => 'WH-FAB-C'], ['name' => 'Dépôt Central FAB', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]);
    $depotPF = Warehouse::firstOrCreate(['code' => 'WH-FAB-PF'], ['name' => 'Dépôt Produits Finis FAB', 'company_id' => $company->id, 'is_active' => true, 'is_default' => false]);

    // Fer à béton fabriqué à la commande ; son dépôt article = Produits Finis (≠ défaut société).
    $fer = Product::factory()->create([
        'name' => 'Fer à béton Ø12 test (12m)', 'is_stockable' => true,
        'is_manufacturable' => true, 'production_mode' => 'mto', 'valuation_method' => 'cmp',
        'main_warehouse_id' => $depotPF->id,
    ]);
    $bobine = Product::factory()->create(['name' => 'Bobine fil machine Ø13 test', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $bobine->id, 'warehouse_id' => $central->id, 'quantity' => 2000, 'reserved_quantity' => 0]);

    $bom = BillOfMaterial::create([
        'company_id' => $company->id, 'product_id' => $fer->id, 'name' => 'BOM FAB',
        'is_active' => true, 'labor_per_unit' => 50, 'machine_time_per_unit' => 1.2,
    ]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $bobine->id, 'quantity_per_meter' => 8.88, 'unit_id' => $unit->id, 'sort_order' => 1]);

    $coil = Coil::create([
        'company_id' => $company->id, 'product_id' => $bobine->id, 'reference' => 'BOB-FAB-T',
        'lot_number' => 'LOT-FAB-T', 'diameter' => 13.0,
        'initial_weight' => 2000, 'remaining_weight' => 2000, 'estimated_length' => 300,
        'purchase_price' => 900_000, 'cost_per_kg' => 450, 'status' => 'disponible',
    ]);

    // ── 1-2. Commande → validation → OF MTO auto ──
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $fer->id, 'description' => $fer->name,
            'quantity' => 100, 'unit_price' => 6_000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    expect($order->fresh()->status)->toBe('confirme');

    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $fer->id)->first();
    expect($of)->not->toBeNull()->and((float) $of->quantity_requested)->toBe(100.0);

    // ── 3. BOM + validations 2 niveaux ──
    $of->update(['bill_of_material_id' => $bom->id, 'thickness' => 12.0, 'poids_par_metre' => 0.888]);
    $svc = app(ProductionService::class);
    $svc->allocateMaterial($of->fresh());
    $svc->submitForValidation($of->fresh());
    $svc->validateByChef($of->fresh());
    $svc->validateByResponsable($of->fresh());
    expect($of->fresh()->status)->toBe('matiere_allouee');

    // ── 4. Gate financière §13.2 : autorisation DAF puis lancement + démarrage ──
    $of->fresh()->update(['financial_authorization' => 'approved', 'financial_authorized_at' => now(), 'financial_authorized_by' => $user->id]);
    $svc->launch($of->fresh());
    $svc->start($of->fresh());
    expect($of->fresh()->status)->toBe('en_cours');

    // ── 5. Consommation bobine ──
    app(CoilConsumptionService::class)->consume($of->fresh(), $coil, 888);
    expect((float) $coil->fresh()->remaining_weight)->toBe(1112.0);

    // ── 6. Déclaration SANS warehouse_id → doit atterrir au dépôt article (fix dépôt PF) ──
    $output = app(ProductionStockService::class)->recordOutput($of->fresh(), [
        'product_id' => $fer->id,
        'quantity' => 100, 'length' => 1, 'unit_cost' => 4_500, 'lot_number' => 'LOT-FAB-PF',
    ]);
    expect((int) $output->warehouse_id)->toBe($depotPF->id);
    expect((float) ProductStock::where('product_id', $fer->id)->where('warehouse_id', $depotPF->id)->value('quantity'))->toBe(100.0);
    expect(ProductStock::where('product_id', $fer->id)->where('warehouse_id', $central->id)->exists())->toBeFalse();

    // ── 7. CQ conforme + clôture → réservation PF auto ──
    ProductionQualityControl::create([
        'company_id' => $company->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);
    $svc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    $resa = StockReservation::where('production_order_id', $of->id)->where('status', 'reserved')->first();
    expect($resa)->not->toBeNull()->and((float) $resa->quantity)->toBe(100.0);

    // ── 8. Désynchro volontaire : réservation repointée sur le dépôt Central (≠ dépôt livré PF) ──
    // Reproduit l'état d'une fusion/transfert de dépôts non répercuté sur stock_reservations.
    $resa->update(['warehouse_id' => $central->id]);

    // ── 9. BL depuis le dépôt PF → la validation doit réaligner et consommer la réservation (fix) ──
    $bp = \App\Models\BonPreparation::where('order_id', $order->id)->latest()->first();
    if ($bp && ! $order->fresh()->isReadyForDelivery()) {
        $bpSvc = app(\App\Services\BonPreparationService::class);
        $bpSvc->startLoading($bp);
        $bpSvc->finishLoading($bp->fresh());
    }

    $this->post(route('ventes.commandes.delivery-note', $order))->assertSessionHas('success');
    $dn = \App\Models\DeliveryNote::where('order_id', $order->id)->latest()->firstOrFail();
    $dn->update(['warehouse_id' => $depotPF->id]);

    app(DeliveryNoteService::class)->validate($dn->fresh());
    expect($dn->fresh()->status)->toBe('valide');

    // PF sorti sans blocage « Stock insuffisant » ; réservation libérée.
    expect((float) ProductStock::where('product_id', $fer->id)->where('warehouse_id', $depotPF->id)->value('quantity'))->toBe(0.0);
    expect(StockReservation::find($resa->id)->status)->toBe('released');
});
