<?php

/**
 * [R2 §3 — vraie surconsommation vs nomenclature] Attendus INDÉPENDANTS.
 *
 * Scénario réel exigé (≠ preuve de stock insuffisant) : besoin théorique 10 kg,
 * stock DISPONIBLE 20 kg, consommation RÉELLE 12 kg → la consommation est
 * ACCEPTÉE et le système calcule un écart de +2 kg, valorisé.
 *
 * Nomenclature : 5 kg/mètre, 0 % rebut ; OF produit 2 mètres → théorique 10 kg.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ConsumptionVarianceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function varSetup(float $coilKg = 20, int $costPerKg = 500): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'VAR'], ['email' => 'var@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-VAR'], ['name' => 'Dépôt VAR', 'is_default' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true]);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    // Nomenclature : 5 kg/mètre de matière mp, 0 % rebut.
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM VAR', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $mp->id, 'quantity_per_meter' => 5, 'waste_rate' => 0, 'sort_order' => 0]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'number' => 'OF-VAR-' . uniqid(),
        'status' => 'en_cours', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id,
        'quantity_requested' => 1, 'quantity_produced' => 1,
    ]);
    // 2 mètres produits → besoin théorique 5 × 2 = 10 kg.
    $of->outputs()->create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'length' => 2, 'quantity' => 1,
        'total_meters' => 2, 'warehouse_id' => $wh->id, 'produced_at' => now(), 'status' => 'validee',
    ]);
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'BV-' . uniqid(),
        'initial_weight' => $coilKg, 'remaining_weight' => $coilKg, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => $costPerKg, 'purchase_price' => (int) ($coilKg * $costPerKg), 'received_at' => now(),
    ]);

    return [$co, $wh, $mp, $pf, $of, $coil];
}

it('surconsommation RÉELLE : stock 20, théorique 10, réel 12 → accepté, écart +2 kg valorisé 1000', function () {
    [, , $mp, , $of, $coil] = varSetup(20, 500);

    // Consommation réelle 12 kg ACCEPTÉE (bobine 20 kg suffit) — pas un refus.
    app(CoilConsumptionService::class)->consume($of, $coil, 12.0, null, null);
    expect((float) $coil->fresh()->remaining_weight)->toBe(8.0); // 20 − 12, la conso est bien passée

    $var  = app(ConsumptionVarianceService::class)->forOrder($of->fresh());
    $line = $var['lines']->firstWhere('product_id', $mp->id);

    // Théorique 10, réel 12, écart +2 kg défavorable ; valeur = 2 × (6000/12=500) = 1000
    expect((float) $line['theoretical_qty'])->toBe(10.0)
        ->and((float) $line['real_qty'])->toBe(12.0)
        ->and((float) $line['ecart_qty'])->toBe(2.0)
        ->and($line['sens'])->toBe('surconsommation')
        ->and((int) $line['ecart_value'])->toBe(1000)
        ->and((int) $var['total_ecart_value'])->toBe(1000);
});

it('sous-consommation : théorique 10, réel 8 → écart −2 kg, valeur −1000 (favorable)', function () {
    [, , $mp, , $of, $coil] = varSetup(20, 500);

    app(CoilConsumptionService::class)->consume($of, $coil, 8.0, null, null);

    $var  = app(ConsumptionVarianceService::class)->forOrder($of->fresh());
    $line = $var['lines']->firstWhere('product_id', $mp->id);

    expect((float) $line['real_qty'])->toBe(8.0)
        ->and((float) $line['ecart_qty'])->toBe(-2.0)
        ->and($line['sens'])->toBe('sous-consommation')
        ->and((int) $line['ecart_value'])->toBe(-1000);
});

it('multi-bobines à coûts différents : écart valorisé au coût réel pondéré', function () {
    [$co, $wh, $mp, , $of] = varSetup(0, 0); // bobine du setup non utilisée (0 kg)

    // Deux bobines de la MÊME matière, coûts différents : 500 et 800 FCFA/kg.
    $b1 = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'M1-' . uniqid(),
        'initial_weight' => 10, 'remaining_weight' => 10, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 5000, 'received_at' => now()]);
    $b2 = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'M2-' . uniqid(),
        'initial_weight' => 10, 'remaining_weight' => 10, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => 800, 'purchase_price' => 8000, 'received_at' => now()]);

    $svc = app(CoilConsumptionService::class);
    $svc->consume($of, $b1, 6.0, null, null); // 6 × 500 = 3000
    $svc->consume($of, $b2, 6.0, null, null); // 6 × 800 = 4800

    $var  = app(ConsumptionVarianceService::class)->forOrder($of->fresh());
    $line = $var['lines']->firstWhere('product_id', $mp->id);

    // Réel 12 kg (coût 7800), théorique 10 → écart +2 kg ; coût pondéré 7800/12 = 650
    // valeur écart = 2 × 650 = 1300 (≠ 1000 au coût d'une seule bobine → pondération prouvée)
    expect((float) $line['real_qty'])->toBe(12.0)
        ->and((float) $line['ecart_qty'])->toBe(2.0)
        ->and((int) $line['ecart_value'])->toBe(1300);
});

it('rebut VALORISÉ : le rebut n\'est pas « aucun mouvement » — registre tracé et chiffré', function () {
    [$co, , , , $of, $coil] = varSetup(20, 500);
    // Consommation réelle 12 kg @ 500 → coût moyen consommé 500 FCFA/kg.
    app(CoilConsumptionService::class)->consume($of, $coil, 12.0, null, null);

    // Déclaration d'un rebut de 3 kg (cause opérateur), avec responsable.
    $operator = \App\Models\Employee::factory()->create(['company_id' => $co->id]);
    $waste = app(\App\Modules\Production\Services\ProductionStockService::class)->recordWaste($of, [
        'weight'      => 3.0,
        'type'        => 'non_reutilisable',
        'reason'      => 'Bavure de coupe',
        'operator_id' => $operator->id,
    ]);

    // Le rebut EXISTE dans le registre, est valorisé au coût réel : 3 × 500 = 1500,
    // rattaché à l'OF, au responsable et à une cause — pas une disparition silencieuse.
    expect((int) $waste->value)->toBe(1500)
        ->and((float) $waste->weight)->toBe(3.0)
        ->and((int) $waste->production_order_id)->toBe($of->id)
        ->and((int) $waste->operator_id)->toBe($operator->id)
        ->and($waste->reason)->toBe('Bavure de coupe')
        ->and($of->wastes()->count())->toBe(1)
        ->and((int) $of->wastes()->sum('value'))->toBe(1500);
});
