<?php

/**
 * [PRO-08] Valorisation des sous-produits et crédit au coût de revient.
 *
 * DEUX DÉFAUTS LIÉS, dans cet ordre :
 *
 *   1. `enterByproduct()` acceptait un coût unitaire par défaut à ZÉRO, et aucun
 *      appelant n'en passait. Toute chute déclarée en production entrait donc en
 *      stock sans valeur.
 *   2. `ProductionCostService` ne créditait jamais l'OF de cette récupération.
 *      La matière est comptée en totalité, chute comprise ; quand la chute revient
 *      en magasin, la laisser au débit surévalue le coût du produit fini.
 *
 * Corriger le second sans le premier n'aurait rien changé : on aurait crédité zéro.
 *
 * La règle est celle DÉJÀ écrite dans CuttingRemnantService — « PMP courant de la
 * matière, pas de profit fictif SYSCOHADA » — plutôt qu'une seconde convention.
 *
 * PIÈGE CENTRAL, gardé par le test le plus important de ce fichier : les entrées
 * de PRODUIT FINI portent la MÊME référence que les sous-produits
 * (`reference_type = ProductionOrder`, `reference_id` = OF). Un filtre portant sur
 * la seule référence déduirait la production elle-même et ferait tomber le coût à
 * zéro. Seuls les articles déclarés « chute » et « avarié » par la nomenclature
 * sont retenus.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionStockService;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * OF « en cours » ayant consommé 100 kg d'une matière, avec une nomenclature
 * déclarant un article chute et un article avarié.
 *
 * @return array<string,mixed>
 */
function bpContext(float $pmpMatiere = 800, float $coutBobineKg = 800): array
{
    $co = bfCompany();
    test()->actingAs(bfAdmin());
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-BP'],
        ['name' => 'Dépôt BP', 'is_active' => true, 'is_default' => true]);

    $matiere = Product::factory()->create(['name' => 'Bobine BP', 'weighted_avg_cost' => $pmpMatiere]);
    $pf      = Product::factory()->create(['name' => 'Tôle BP']);
    $chute   = Product::factory()->create(['name' => 'Chute BP']);
    $avarie  = Product::factory()->create(['name' => 'Avarié BP']);

    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM BP', 'is_active' => true,
        'scrap_product_id' => $chute->id, 'defect_product_id' => $avarie->id,
    ]);
    \Illuminate\Support\Facades\DB::table('bom_lines')->insert([
        'bill_of_material_id' => $bom->id, 'product_id' => $matiere->id,
        'quantity_per_meter' => 1, 'statut' => 'actif',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'number' => 'OF-BP-'.uniqid(), 'product_id' => $pf->id,
        'bill_of_material_id' => $bom->id, 'depot_matiere_id' => $wh->id,
        'quantity_requested' => 100, 'status' => 'en_cours',
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $matiere->id, 'warehouse_id' => $wh->id,
        'reference' => 'BOB-BP-'.uniqid(), 'initial_weight' => 500, 'remaining_weight' => 500,
        'cost_per_kg' => $coutBobineKg, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive',
    ]);

    app(CoilConsumptionService::class)->consume($order, $coil, 100);

    return compact('co', 'wh', 'matiere', 'pf', 'chute', 'avarie', 'bom', 'order', 'coil');
}

// ── 1. La chute entre en stock AVEC une valeur ───────────────────────────────

it('valorise le sous-produit au PMP de la matière consommée, plus à zéro', function () {
    ['order' => $o, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    $mvt = app(ProductionStockService::class)->enterByproduct($o, $chute->id, 10, $wh->id);

    expect($mvt)->not->toBeNull()
        ->and((float) $mvt->unit_cost)->toBe(800.0)
        ->and((float) $mvt->total_cost)->toBe(8000.0);
});

it('se rabat sur le coût au kilo de la bobine quand l’article n’a pas de PMP', function () {
    // Première réception non encore valorisée : le PMP article est nul, mais la
    // bobine consommée porte bien un coût.
    ['order' => $o, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 0, coutBobineKg: 650);

    $mvt = app(ProductionStockService::class)->enterByproduct($o, $chute->id, 10, $wh->id);

    expect((float) $mvt->unit_cost)->toBe(650.0);
});

it('respecte un coût explicitement fourni par l’appelant', function () {
    ['order' => $o, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    $mvt = app(ProductionStockService::class)->enterByproduct($o, $chute->id, 10, $wh->id, unitCost: 250);

    expect((float) $mvt->unit_cost)->toBe(250.0);
});

// ── 2. Le coût de revient est crédité ────────────────────────────────────────

it('déduit la chute récupérée du coût matière', function () {
    ['order' => $o, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    $avant = app(ProductionCostService::class)->compute($o->fresh());
    expect((int) $avant->material_cost)->toBe(80000);   // 100 kg × 800

    app(ProductionStockService::class)->enterByproduct($o, $chute->id, 10, $wh->id); // 10 × 800

    $apres = app(ProductionCostService::class)->compute($o->fresh());
    expect((int) $apres->material_cost)->toBe(72000)
        ->and((int) $apres->total_cost)->toBeLessThan((int) $avant->total_cost);
});

it('déduit aussi l’article « avarié » de la nomenclature', function () {
    ['order' => $o, 'avarie' => $avarie, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    app(ProductionStockService::class)->enterByproduct($o, $avarie->id, 5, $wh->id);

    expect((int) app(ProductionCostService::class)->compute($o->fresh())->material_cost)->toBe(76000);
});

// ── 3. Le piège : ne jamais déduire le produit fini ──────────────────────────

it('ne déduit JAMAIS l’entrée de produit fini, qui porte la même référence', function () {
    // Test central. L'entrée de PF a `reference_type = ProductionOrder` et
    // `reference_id` = l'OF, exactement comme un sous-produit. Un filtre portant
    // sur la seule référence aurait soustrait la production et vidé le coût.
    ['order' => $o, 'pf' => $pf, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    app(ProductionStockService::class)->recordOutput($o->fresh(), [
        'product_id' => $pf->id, 'warehouse_id' => $wh->id,
        'quantity' => 50, 'length' => 1, 'unit_cost' => 1500,
    ]);

    // L'entrée de PF existe bien, avec la même référence qu'un sous-produit.
    $entreePf = StockMovement::where('type', 'entree')
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $o->id)
        ->where('product_id', $pf->id)->first();
    expect($entreePf)->not->toBeNull()
        ->and((float) $entreePf->total_cost)->toBeGreaterThan(0);

    // …et le coût matière reste intact.
    expect((int) app(ProductionCostService::class)->compute($o->fresh())->material_cost)->toBe(80000);
});

it('ne déduit rien quand la nomenclature ne déclare aucun article de sous-produit', function () {
    ['order' => $o, 'bom' => $bom, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 800);
    $bom->update(['scrap_product_id' => null, 'defect_product_id' => null]);

    // Une entrée existe, mais aucun article n'est déclaré comme sous-produit :
    // rien ne permet de l'identifier comme tel, donc rien n'est déduit.
    app(ProductionStockService::class)->enterByproduct($o, $chute->id, 10, $wh->id);

    expect((int) app(ProductionCostService::class)->compute($o->fresh())->material_cost)->toBe(80000);
});

it('ne fait jamais descendre la matière sous zéro', function () {
    // Récupération absurde (200 kg pour 100 consommés) : le coût matière plafonne
    // à zéro plutôt que de devenir négatif et de fausser la marge.
    ['order' => $o, 'chute' => $chute, 'wh' => $wh] = bpContext(pmpMatiere: 800);

    app(ProductionStockService::class)->enterByproduct($o, $chute->id, 200, $wh->id);

    expect((int) app(ProductionCostService::class)->compute($o->fresh())->material_cost)->toBe(0);
});
