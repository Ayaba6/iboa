<?php

/**
 * [Workflow Vente → Production Tôles Bac] Chaîne spécifique tôle bac,
 * complémentaire d'OrderToCashFullChainTest — couvre en plus :
 *   - la gate financière §13.2 RÉELLE (lancement refusé client crédit,
 *     puis autorisation DAF — l'autre test la court-circuite) ;
 *   - la gate BP §13.7 (chargement du bon de préparation avant BL) si BP généré ;
 *   - la réservation PF automatique après clôture (commande liée + CQ conforme) ;
 *   - les caractéristiques tôle bac (profil, épaisseur, poids/m, bobine composant).
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
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function tbcAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TBC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TBC Co'], ['email' => 'tbc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('parcourt Vente → Production tôle bac : gate financière réelle, bobine, réservation PF auto, BP, livraison', function () {
    $user    = tbcAdmin();
    $company = Company::first();
    $this->actingAs($user);

    $client  = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit']);
    $unit    = Unit::firstOrCreate(['name' => 'Pièce TBC'], ['abbreviation' => 'ptbc']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% TBC'], ['short_name' => 'TVA18T', 'rate' => 18, 'is_active' => true]);
    $wh      = Warehouse::firstOrCreate(['code' => 'WH-TBC'], ['name' => 'Dépôt TBC', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]);

    // Article tôle bac fabriqué à la commande, bobine composante (2,5 kg/m).
    $tole = Product::factory()->create([
        'name' => 'Tôle bac test beige 30/100', 'is_stockable' => true,
        'is_manufacturable' => true, 'production_mode' => 'mto', 'valuation_method' => 'cmp',
        'thickness' => 0.30,
    ]);
    $bobine = Product::factory()->create(['name' => 'Bobine test beige 30/100', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $bobine->id, 'warehouse_id' => $wh->id, 'quantity' => 500, 'reserved_quantity' => 0]);

    $bom = BillOfMaterial::create([
        'company_id' => $company->id, 'product_id' => $tole->id, 'name' => 'BOM TBC',
        'is_active' => true, 'labor_per_unit' => 150, 'machine_time_per_unit' => 1.5,
    ]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $bobine->id, 'quantity_per_meter' => 2.5, 'unit_id' => $unit->id, 'sort_order' => 1]);

    $coil = Coil::create([
        'company_id' => $company->id, 'product_id' => $bobine->id, 'reference' => 'BOB-TBC-T',
        'lot_number' => 'LOT-TBC-T', 'color' => 'BEIGE', 'thickness' => 0.30, 'width' => 1000,
        'initial_weight' => 500, 'remaining_weight' => 500, 'estimated_length' => 200,
        'purchase_price' => 250_000, 'cost_per_kg' => 500, 'status' => 'disponible',
    ]);

    // ── 1-2. Commande → validation → OF MTO auto ──
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $tole->id, 'description' => $tole->name,
            'quantity' => 30, 'unit_price' => 5_500, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    expect($order->fresh()->status)->toBe('confirme');

    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $tole->id)->first();
    expect($of)->not->toBeNull()->and((float) $of->quantity_requested)->toBe(30.0);

    // ── 3. BOM + caractéristiques tôle + validations 2 niveaux ──
    $of->update(['bill_of_material_id' => $bom->id, 'profil' => '4_ondes', 'couleur_ral' => 'BEIGE', 'thickness' => 0.30, 'poids_par_metre' => 2.5]);
    $svc = app(ProductionService::class);
    $svc->allocateMaterial($of->fresh());
    $svc->submitForValidation($of->fresh());
    $svc->validateByChef($of->fresh());
    $svc->validateByResponsable($of->fresh());
    expect($of->fresh()->status)->toBe('matiere_allouee');

    // ── 4. Gate financière §13.2 : client crédit → lancement refusé ──
    expect(fn () => $svc->launch($of->fresh()))->toThrow(ValidationException::class);

    // ── 5. Autorisation DAF → lancement + démarrage ──
    $of->fresh()->update(['financial_authorization' => 'approved', 'financial_authorized_at' => now(), 'financial_authorized_by' => $user->id]);
    $svc->launch($of->fresh());
    $svc->start($of->fresh());
    expect($of->fresh()->status)->toBe('en_cours');

    // ── 6. Consommation bobine 75 kg (30 m × 2,5 kg/m) ──
    app(CoilConsumptionService::class)->consume($of->fresh(), $coil, 75);
    expect((float) $coil->fresh()->remaining_weight)->toBe(425.0);

    // ── 7. Déclaration + CQ conforme + visa + clôture → réservation PF auto ──
    $output = app(ProductionStockService::class)->recordOutput($of->fresh(), [
        'product_id' => $tole->id, 'warehouse_id' => $wh->id,
        'quantity' => 30, 'length' => 1, 'unit_cost' => 3_200, 'lot_number' => 'LOT-TBC-PF',
    ]);
    ProductionQualityControl::create([
        'company_id' => $company->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    // [MTO §15 — adaptation documentée du 30/07/2026] Libération qualité exigée en
    // plus du visa avant toute livraison. Même motif que FerABetonWorkflowTest :
    // le parcours contrôlait la production sans jamais la libérer, et le garde
    // ne lisait pas `quality_released_at`.
    $output->update([
        'status'              => 'validee',
        'validated_at'        => now(),
        'quality_released_at' => now(),
    ]);
    $svc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    $resa = StockReservation::where('production_order_id', $of->id)->where('status', 'reserved')->first();
    expect($resa)->not->toBeNull()
        ->and((float) $resa->quantity)->toBe(30.0);

    // ── 8. BL via la route réelle : gate BP §13.7 puis validation → sortie stock ──
    $bp = \App\Models\BonPreparation::where('order_id', $order->id)->latest()->first();
    if ($bp && ! $order->fresh()->isReadyForDelivery()) {
        // Chargement non terminé → le contrôleur refuse la création du BL (§13.7).
        $this->post(route('ventes.commandes.delivery-note', $order))
            ->assertSessionHas('error');
        expect(\App\Models\DeliveryNote::where('order_id', $order->id)->exists())->toBeFalse();

        $bpSvc = app(\App\Services\BonPreparationService::class);
        $bpSvc->startLoading($bp);
        $bpSvc->finishLoading($bp->fresh());
    }

    $this->post(route('ventes.commandes.delivery-note', $order))->assertSessionHas('success');
    $dn = \App\Models\DeliveryNote::where('order_id', $order->id)->latest()->firstOrFail();

    app(DeliveryNoteService::class)->validate($dn->fresh());
    expect($dn->fresh()->status)->toBe('valide');
    expect((float) ProductStock::where('product_id', $tole->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(0.0);
});
