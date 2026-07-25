<?php

/**
 * [Audit OF — gardes métier avant lancement/clôture]
 *  - lancement bloqué : article fabriqué (is_manufacturable) sans nomenclature ;
 *  - clôture bloquée : nomenclature présente mais AUCUNE matière consommée
 *    (dérogation valideur possible) ;
 *  - clôture bloquée : contrôle qualité obligatoire non réalisé (non dérogeable) ;
 *  - automatismes post-clôture : lot de fabrication, réservation PF pour le
 *    client de la commande liée, coût de revient calculé.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function guardAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'GUARD'], ['email' => 'guard@guard.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WG'], ['name' => 'WG', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_production' => true, 'can_stock' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function guardOf(array $attrs = []): ProductionOrder
{
    $co = Company::first();

    return ProductionOrder::create(array_merge([
        'company_id' => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-GUARD-'.uniqid(),
        'status' => 'en_cours',
        'quantity_requested' => 10,
        'quantity_produced' => 10,
        'launched_at' => now(),
    ], $attrs));
}

it('bloque le lancement d\'un article fabriqué sans nomenclature', function () {
    $this->actingAs(guardAdmin());
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    $of = guardOf(['status' => 'brouillon', 'product_id' => $pf->id, 'quantity_produced' => 0]);

    expect(fn () => app(ProductionService::class)->launch($of))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');

    // Avec nomenclature rattachée → lancement OK.
    $bom = BillOfMaterial::create(['company_id' => $of->company_id, 'product_id' => $pf->id, 'name' => 'BOM G', 'is_active' => true]);
    $of->update(['bill_of_material_id' => $bom->id]);
    app(ProductionService::class)->launch($of->fresh());
    expect($of->fresh()->status)->toBe('lance');
});

it('bloque la clôture si contrôle qualité obligatoire non réalisé, passe une fois contrôlé', function () {
    $this->actingAs(guardAdmin());
    $of = guardOf()->fresh(); // controle_qualite_obligatoire = true (défaut migration, relu depuis la DB)

    expect(fn () => app(ProductionService::class)->finish($of))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');

    ProductionQualityControl::create([
        'company_id' => $of->company_id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);

    app(ProductionService::class)->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');
});

it('bloque la clôture avec nomenclature sans aucune consommation — dérogation possible', function () {
    $this->actingAs(guardAdmin());
    $pf = Product::factory()->create();
    $comp = Product::factory()->create();
    $bom = BillOfMaterial::create(['company_id' => Company::first()->id, 'product_id' => $pf->id, 'name' => 'BOM C', 'is_active' => true]);
    // La garde ne s'applique qu'aux nomenclatures AVEC composants.
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $comp->id, 'quantity_per_meter' => 1, 'sort_order' => 1]);

    $of = guardOf([
        'product_id' => $pf->id, 'bill_of_material_id' => $bom->id,
        'controle_qualite_obligatoire' => false,
    ]);

    // Aucune consommation bobine ni composant → clôture bloquée.
    expect(fn () => app(ProductionService::class)->finish($of))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');

    // Dérogation valideur (écart assumé) → clôture autorisée.
    app(ProductionService::class)->finish($of->fresh(), true);
    expect($of->fresh()->status)->toBe('termine');
});

it('à la clôture : lot auto, réservation PF pour le client et coût de revient calculés', function () {
    $this->actingAs(guardAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-GUARD-'.uniqid(), 'status' => 'confirme',
        'issued_at' => now(), 'total_ttc' => 100000,
    ]);

    $of = guardOf([
        'product_id' => $pf->id, 'order_id' => $order->id,
        'quantity_produced' => 0, 'controle_qualite_obligatoire' => false,
    ]);

    // Déclaration de production réelle (entrée stock PF) + visa chef.
    $output = app(ProductionStockService::class)->recordOutput($of, [
        'warehouse_id' => $wh->id, 'quantity' => 10, 'length' => 6, 'unit_cost' => 2000,
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);

    app(ProductionService::class)->finish($of->fresh());
    $of->refresh();

    expect($of->status)->toBe('termine');

    // 1. Lot de fabrication créé automatiquement (traçabilité).
    expect($of->batches()->count())->toBe(1)
        ->and((float) $of->batches()->first()->quantity)->toEqual(10.0);

    // 2. Produit fini réservé pour le client de la commande liée.
    $res = StockReservation::where('production_order_id', $of->id)->where('status', 'reserved')->first();
    expect($res)->not->toBeNull()
        ->and((float) $res->quantity)->toEqual(10.0)
        ->and($res->order_id)->toBe($order->id);
    expect((float) ProductStock::where('product_id', $pf->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))->toEqual(10.0);

    // 3. Coût de revient calculé automatiquement.
    expect(ProductionCost::where('production_order_id', $of->id)->exists())->toBeTrue();
});

it('bloque la clôture si des opérations de gamme ne sont pas terminées — dérogation possible', function () {
    $this->actingAs(guardAdmin());
    $of = guardOf(['controle_qualite_obligatoire' => false]);

    $of->operations()->create([
        'company_id' => $of->company_id, 'sequence' => 10, 'name' => 'Découpe test',
        'planned_minutes' => 55, 'status' => 'pending',
    ]);

    expect(fn () => app(ProductionService::class)->finish($of->fresh()))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');

    // Opération terminée → clôture OK.
    $of->operations()->update(['status' => 'done']);
    app(ProductionService::class)->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    // Dérogation : opération pending mais force → clôture autorisée.
    $of2 = guardOf(['controle_qualite_obligatoire' => false]);
    $of2->operations()->create([
        'company_id' => $of2->company_id, 'sequence' => 10, 'name' => 'Découpe test 2',
        'planned_minutes' => 30, 'status' => 'pending',
    ]);
    app(ProductionService::class)->finish($of2->fresh(), true);
    expect($of2->fresh()->status)->toBe('termine');
});

it('après clôture avec QC conforme : lot PF « conforme » + réservation client créée', function () {
    $this->actingAs(guardAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-QCLOT-'.uniqid(), 'status' => 'confirme',
        'issued_at' => now(), 'total_ttc' => 50000,
    ]);

    // QC obligatoire (défaut) — relu depuis la DB pour charger le défaut.
    $of = guardOf(['product_id' => $pf->id, 'order_id' => $order->id, 'quantity_produced' => 0])->fresh();

    $output = app(ProductionStockService::class)->recordOutput($of, [
        'warehouse_id' => $wh->id, 'quantity' => 10, 'length' => 6, 'unit_cost' => 1000,
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);

    ProductionQualityControl::create([
        'company_id' => $of->company_id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);

    app(ProductionService::class)->finish($of->fresh());
    $of->refresh();

    // Lot auto passé « conforme » (PF libéré après contrôle qualité conforme).
    expect($of->batches()->count())->toBe(1)
        ->and($of->batches()->first()->status)->toBe('conforme');

    // PF réservé pour la commande client.
    $res = StockReservation::where('production_order_id', $of->id)->where('status', 'reserved')->first();
    expect($res)->not->toBeNull()
        ->and($res->order_id)->toBe($order->id)
        ->and((float) $res->quantity)->toEqual(10.0);
});

it('affiche la consommation composants et le rendement matière sur la fiche OF', function () {
    $this->actingAs(guardAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $pf = Product::factory()->create(['is_stockable' => true]);
    $comp = Product::factory()->create(['name' => 'Composant Visserie Test', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $comp->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 250]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM affichage', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $comp->id, 'quantity_per_meter' => 2, 'sort_order' => 1]);

    $of = guardOf([
        'product_id' => $pf->id, 'bill_of_material_id' => $bom->id,
        'quantity_produced' => 0, 'controle_qualite_obligatoire' => false,
    ]);

    // Déclaration → consommation auto composant (2 × 5 = 10 u @ 250 F = 2 500 F).
    app(ProductionStockService::class)->recordOutput($of, ['warehouse_id' => $wh->id, 'quantity' => 5, 'length' => 6]);

    $resp = $this->get(route('production.orders.show', $of));
    $resp->assertOk()
        ->assertSee('Composant Visserie Test')      // ligne composant dans le bloc conso
        ->assertSee('auto (BOM)')                    // marqueur consommation automatique
        ->assertSee('2 500');                        // coût matière composants

    // Rendement matière : théorique 2×5=10 / réel 10 → 100 %.
    $resp->assertSee('100,0');
});

it('la clôture en dérogation backflush les opérations restantes au temps standard', function () {
    $this->actingAs(guardAdmin());
    $of = guardOf(['controle_qualite_obligatoire' => false]);
    $of->operations()->create([
        'company_id' => $of->company_id, 'sequence' => 10, 'name' => 'Profilage',
        'planned_minutes' => 55, 'status' => 'pending',
    ]);

    app(ProductionService::class)->finish($of->fresh(), true);

    $op = $of->operations()->first();
    expect($of->fresh()->status)->toBe('termine')
        ->and($op->status)->toBe('done')
        ->and((float) $op->real_minutes)->toEqual(55.0)   // réel = prévu (backflush)
        ->and($op->ended_at)->not->toBeNull();
});

it('affiche l\'unité réelle de l\'article dans la consommation composants', function () {
    $this->actingAs(guardAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $kg = Unit::firstOrCreate(['code' => 'KG'], ['name' => 'Kilogramme', 'abbreviation' => 'kg']);
    $pf = Product::factory()->create(['is_stockable' => true]);
    $comp = Product::factory()->create(['name' => 'Peinture Époxy Test', 'is_stockable' => true, 'unit_id' => $kg->id]);
    ProductStock::create(['product_id' => $comp->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 800]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM unité', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $comp->id, 'quantity_per_meter' => 1.5, 'sort_order' => 1]);

    $of = guardOf(['product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_produced' => 0, 'controle_qualite_obligatoire' => false]);
    app(ProductionStockService::class)->recordOutput($of, ['warehouse_id' => $wh->id, 'quantity' => 4, 'length' => 6]);

    // 1,5 × 4 = 6,00 kg — l'unité de l'article remplace le « u » générique.
    $this->get(route('production.orders.show', $of))
        ->assertOk()
        ->assertSee('Peinture Époxy Test')
        ->assertSee('6,00 kg');
});

it('intègre le temps standard de gamme dans le coût MO quand aucun pointage réel n\'existe', function () {
    $this->actingAs(guardAdmin());
    $co = Company::first();
    $wc = WorkCenter::create([
        'company_id' => $co->id, 'code' => 'WC-STD', 'name' => 'Poste Standard',
        'cost_per_hour' => 3000, 'is_active' => true,
    ]);

    $of = guardOf(['controle_qualite_obligatoire' => false]);
    $of->operations()->create([
        'company_id' => $of->company_id, 'work_center_id' => $wc->id,
        'sequence' => 10, 'name' => 'Découpe std', 'planned_minutes' => 60, 'status' => 'done',
    ]);

    // Aucun pointage, aucun override, BOM absente → MO = 60 min × 3 000 F/h = 3 000 F.
    $cost = app(ProductionCostService::class)->compute($of->fresh());
    expect((int) $cost->labor_cost)->toBe(3000);
});

it('ignore un contrôle qualité strictement identique soumis deux fois (doublon)', function () {
    $this->actingAs(guardAdmin());
    $of = guardOf(['controle_qualite_obligatoire' => false]);

    $payload = [
        'thickness_ok' => 1, 'length_ok' => 1, 'color_ok' => 1, 'visual_ok' => 1,
        'status' => 'conforme', 'rejected_quantity' => 0,
    ];

    $this->post(route('production.orders.quality', $of), $payload)->assertSessionHas('success');
    $this->post(route('production.orders.quality', $of), $payload)->assertSessionHas('error');

    expect($of->qualityControls()->count())->toBe(1);

    // Un contrôle DIFFÉRENT (non conforme) reste accepté.
    $this->post(route('production.orders.quality', $of), array_merge($payload, ['status' => 'non_conforme', 'reason' => 'Rayures']))
        ->assertSessionHas('success');
    expect($of->qualityControls()->count())->toBe(2);
});

it('respecte « Autoriser clôture partielle » : Non → Terminer partiellement bloqué', function () {
    $this->actingAs(guardAdmin());

    $of = guardOf(['quantity_produced' => 4, 'autoriser_cloture_partielle' => false, 'controle_qualite_obligatoire' => false]);
    expect(fn () => app(ProductionService::class)->markPartiallyDone($of->fresh()))
        ->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');

    // Oui (défaut) → clôture partielle autorisée.
    $of2 = guardOf(['quantity_produced' => 4, 'controle_qualite_obligatoire' => false]);
    app(ProductionService::class)->markPartiallyDone($of2->fresh());
    expect($of2->fresh()->status)->toBe('termine_partiellement');
});

it('respecte « Autoriser dépassement qté » : Non → déclaration au-delà du demandé bloquée', function () {
    $this->actingAs(guardAdmin());
    $wh = Warehouse::first();
    $pf = Product::factory()->create(['is_stockable' => true]);

    // Défaut (Non) : demandé 10, déjà produit 8 → déclarer 5 dépasse → refus.
    $of = guardOf(['product_id' => $pf->id, 'quantity_requested' => 10, 'quantity_produced' => 8, 'controle_qualite_obligatoire' => false])->fresh();
    expect(fn () => app(ProductionStockService::class)->recordOutput($of, ['warehouse_id' => $wh->id, 'quantity' => 5, 'length' => 6]))
        ->toThrow(ValidationException::class);
    expect((float) $of->fresh()->quantity_produced)->toEqual(8.0);

    // Déclarer exactement le reste (2) → accepté.
    app(ProductionStockService::class)->recordOutput($of->fresh(), ['warehouse_id' => $wh->id, 'quantity' => 2, 'length' => 6]);
    expect((float) $of->fresh()->quantity_produced)->toEqual(10.0);

    // Dépassement autorisé (Oui) → sur-production acceptée.
    $of2 = guardOf(['product_id' => $pf->id, 'quantity_requested' => 10, 'quantity_produced' => 8, 'autoriser_depassement_qte' => true, 'controle_qualite_obligatoire' => false])->fresh();
    app(ProductionStockService::class)->recordOutput($of2, ['warehouse_id' => $wh->id, 'quantity' => 5, 'length' => 6]);
    expect((float) $of2->fresh()->quantity_produced)->toEqual(13.0);
});

it('la déclaration sans longueur reprend la longueur de l\'en-tête OF (métrage non nul)', function () {
    $this->actingAs(guardAdmin());
    $wh = Warehouse::first();
    $pf = Product::factory()->create(['is_stockable' => true]);

    $of = guardOf([
        'product_id' => $pf->id, 'length' => 3.5,
        'quantity_produced' => 0, 'controle_qualite_obligatoire' => false,
    ]);

    $output = app(ProductionStockService::class)->recordOutput($of, [
        'warehouse_id' => $wh->id, 'quantity' => 4, // length absente
    ]);

    expect((float) $output->length)->toEqual(3.5)
        ->and((float) $output->total_meters)->toEqual(14.0);
});

it('bloque la clôture avec consommation physique non valorisée sauf dérogation', function () {
    $this->actingAs(guardAdmin());
    $order = guardOf(['controle_qualite_obligatoire' => false, 'quantity_requested' => 1, 'quantity_produced' => 1]);
    $coil = Coil::factory()->create([
        'company_id' => $order->company_id,
        'initial_weight' => 10,
        'remaining_weight' => 8,
        'cost_per_kg' => 0,
    ]);
    ProductionConsumption::create([
        'company_id' => $order->company_id,
        'production_order_id' => $order->id,
        'coil_id' => $coil->id,
        'weight_consumed' => 2,
        'length_consumed' => 1,
        'cost' => 0,
        'consumed_at' => now(),
    ]);

    expect(fn () => app(ProductionService::class)->finish($order->fresh()))
        ->toThrow(ValidationException::class, 'sans valorisation');
    expect($order->fresh()->status)->toBe('en_cours');

    app(ProductionService::class)->finish($order->fresh(), force: true);
    expect($order->fresh()->status)->toBe('termine');
});
