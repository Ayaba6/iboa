<?php

/**
 * [Traçabilité & Rendement] La fiche OF (onglet Traçabilité) affiche de façon
 * consolidée : rendement matière, traçabilité bobine → fournisseur, chutes/rebuts
 * et contrôle qualité — ce que le rapport de fabrication doit montrer.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Models\ProductionWaste;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('affiche le panneau Traçabilité & Rendement consolidé sur la fiche OF', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'TRC'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TRC Co'], ['email' => 'trc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    $this->actingAs($u);

    $supplier = Supplier::create(['code' => 'F-TRC', 'type' => 'entreprise', 'name' => 'Aciers du Sahel', 'is_active' => true, 'balance' => 0]);
    $product  = Product::factory()->create(['is_stockable' => true]);
    $of = ProductionOrder::factory()->create(['company_id' => $co->id, 'product_id' => $product->id, 'status' => 'termine', 'quantity_produced' => 100]);

    // Bobine tracée : lot + fournisseur + caractéristiques
    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-TRC-001', 'lot_number' => 'LOT-2026-045',
        'color' => 'Beige', 'thickness' => 0.27, 'width' => 1000, 'supplier_id' => $supplier->id,
        'supplier_reference' => 'SUP-778', 'initial_weight' => 500, 'remaining_weight' => 300,
        'cost_per_kg' => 400, 'purchase_price' => 200000, 'status' => 'disponible',
    ]);
    ProductionConsumption::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'coil_id' => $coil->id,
        'weight_consumed' => 200, 'length_consumed' => 100, 'cost' => 80000, 'consumed_at' => now(),
    ]);
    ProductionOutput::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'product_id' => $product->id,
        'quantity' => 20, 'length' => 5, 'total_meters' => 100, 'unit_cost' => 1600, 'status' => 'validee', 'produced_at' => now(),
    ]);
    // Chutes : réutilisable + rebut
    ProductionWaste::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'type' => 'reutilisable', 'weight' => 0.5, 'value' => 200, 'cause' => 'Chute fin de bobine']);
    ProductionWaste::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'type' => 'rebut', 'weight' => 0.5, 'value' => 200, 'cause' => 'Réglage machine']);
    // Contrôle qualité conforme
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controller_id' => null, 'controlled_at' => now()]);

    $resp = $this->get(route('production.orders.show', $of));
    $resp->assertOk();

    // Rendement + traçabilité bobine + fournisseur + chaîne + QC tous présents
    $resp->assertSee('Traçabilité matière');
    $resp->assertSee('Rendement matière');
    $resp->assertSee('BOB-TRC-001');
    $resp->assertSee('LOT-2026-045');
    $resp->assertSee('Aciers du Sahel');
    $resp->assertSee('Chaîne de traçabilité');
    $resp->assertSee('Contrôle qualité');
});
