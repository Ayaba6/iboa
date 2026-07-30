<?php

/**
 * [MTO §9] Compatibilité bobine ↔ ordre de fabrication.
 *
 * Avant CoilCompatibilityService, `exists:coils,id` était le seul contrôle :
 * toute bobine de l'entreprise pouvait être engagée sur tout OF. Ces tests
 * couvrent les deux niveaux de la règle — le BLOCAGE d'un écart avéré, et
 * l'AUTORISATION JOURNALISÉE de ce qui n'est pas comparable.
 *
 * Le second point est le plus important à ne pas casser : le parc réel ne porte
 * aujourd'hui aucune couleur ni épaisseur sur ses bobines. Une règle qui
 * refuserait l'inconnu arrêterait l'atelier sans rien démontrer.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilCompatibilityService;
use App\Modules\Production\Services\CoilConsumptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function ccWarehouse(Company $co, string $code = 'DEP-CC'): Warehouse
{
    return Warehouse::firstOrCreate(
        ['company_id' => $co->id, 'code' => $code],
        ['name' => 'Dépôt '.$code, 'is_active' => true]
    );
}

function ccCoil(Company $co, Product $mp, Warehouse $wh, array $overrides = []): Coil
{
    return Coil::create(array_merge([
        'company_id'       => $co->id,
        'product_id'       => $mp->id,
        'warehouse_id'     => $wh->id,
        'reference'        => 'BOB-CC-'.uniqid(),
        'initial_weight'   => 500,
        'remaining_weight' => 500,
        'cost_per_kg'      => 800,
        'status'           => 'disponible',
        'valuation_status' => 'valorisation_definitive',
    ], $overrides));
}

/**
 * OF « en cours » adossé à une nomenclature dont le composant est $mp.
 * Le dépôt matière est aligné sur celui de la bobine : le test qui veut un écart
 * de dépôt le pose explicitement, les autres ne doivent pas en souffrir.
 */
function ccOrder(Company $co, Product $pf, Product $mp, Warehouse $wh, array $overrides = []): ProductionOrder
{
    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id,
        'name' => 'BOM CC '.uniqid(), 'is_active' => true,
    ]);
    DB::table('bom_lines')->insert([
        'bill_of_material_id' => $bom->id, 'product_id' => $mp->id,
        'quantity_per_meter' => 1.5, 'statut' => 'actif',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ProductionOrder::create(array_merge([
        'company_id'          => $co->id,
        'number'              => 'OF-CC-'.uniqid(),
        'product_id'          => $pf->id,
        'bill_of_material_id' => $bom->id,
        'depot_matiere_id'    => $wh->id,
        'quantity_requested'  => 100,
        'status'              => 'en_cours',
    ], $overrides));
}

/** Contexte complet : société, dépôt, matière, produit fini, OF, bobine. */
function ccContext(array $coilOverrides = [], array $orderOverrides = []): array
{
    $co = bfCompany();
    test()->actingAs(bfAdmin());
    $wh = ccWarehouse($co);
    $mp = Product::factory()->create(['name' => 'Bobine prélaquée CC']);
    $pf = Product::factory()->create(['name' => 'Tôle bac CC']);
    $order = ccOrder($co, $pf, $mp, $wh, $orderOverrides);
    $coil  = ccCoil($co, $mp, $wh, $coilOverrides);

    return compact('co', 'wh', 'mp', 'pf', 'order', 'coil');
}

function ccConsume(ProductionOrder $order, Coil $coil, float $weight = 10): mixed
{
    return app(CoilConsumptionService::class)->consume($order, $coil, $weight);
}

// ── 1-2. Article et nomenclature ─────────────────────────────────────────────

it('autorise une bobine dont l’article est un composant de la nomenclature', function () {
    ['order' => $o, 'coil' => $c] = ccContext();

    $conso = ccConsume($o, $c);

    expect($conso->exists)->toBeTrue()
        ->and((float) $c->fresh()->remaining_weight)->toBe(490.0);
});

it('refuse une bobine dont l’article ne figure pas dans la nomenclature', function () {
    ['co' => $co, 'wh' => $wh, 'order' => $o] = ccContext();
    $etranger = Product::factory()->create(['name' => 'Bobine hors nomenclature']);
    $c = ccCoil($co, $etranger, $wh);

    expect(fn () => ccConsume($o, $c))
        ->toThrow(ValidationException::class, 'ne figure pas parmi les composants');

    expect((float) $c->fresh()->remaining_weight)->toBe(500.0); // rien consommé
});

it('accepte un article déclaré comme SUBSTITUT dans la nomenclature', function () {
    // Un substitut déclaré EST un composant autorisé : le refuser obligerait
    // l'atelier à modifier la nomenclature pour utiliser ce qui y est prévu.
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'pf' => $pf] = ccContext();
    $substitut = Product::factory()->create(['name' => 'Bobine substitut']);

    $bom = BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM subst', 'is_active' => true,
    ]);
    DB::table('bom_lines')->insert([
        'bill_of_material_id' => $bom->id, 'product_id' => $mp->id,
        'substitute_product_id' => $substitut->id, 'quantity_per_meter' => 1.5,
        'statut' => 'actif', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $o = ProductionOrder::create([
        'company_id' => $co->id, 'number' => 'OF-SUBST-'.uniqid(), 'product_id' => $pf->id,
        'bill_of_material_id' => $bom->id, 'depot_matiere_id' => $wh->id,
        'quantity_requested' => 100, 'status' => 'en_cours',
    ]);

    expect(ccConsume($o, ccCoil($co, $substitut, $wh))->exists)->toBeTrue();
});

// ── 3-5. Caractéristiques physiques ──────────────────────────────────────────

it('refuse une couleur différente de celle attendue par l’OF', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['color' => 'orange'], ['color' => 'beige']);

    expect(fn () => ccConsume($o, $c))
        ->toThrow(ValidationException::class, 'Couleur attendue « beige », bobine « orange »');
});

it('accepte la même couleur écrite différemment — casse et accents neutralisés', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['color' => 'BEIGE  Clair'], ['color' => 'béige clair']);

    expect(ccConsume($o, $c)->exists)->toBeTrue();
});

it('refuse une épaisseur hors tolérance', function () {
    // 0,27 attendu contre 0,40 : 13/100 d'écart, très au-delà du centième toléré.
    ['order' => $o, 'coil' => $c] = ccContext(['thickness' => 0.40], ['thickness' => 0.27]);

    expect(fn () => ccConsume($o, $c))
        ->toThrow(ValidationException::class, 'Épaisseur attendue 0.27 mm, bobine 0.4 mm');
});

it('accepte une épaisseur dans la tolérance — comparaison décimale, pas textuelle', function () {
    // 0,27 contre 0,275 : 5/1000 d'écart. La tolérance par défaut est de 0,01 mm.
    // Une comparaison de chaînes aurait refusé « 0.27 » ≠ « 0.275 ».
    ['order' => $o, 'coil' => $c] = ccContext(['thickness' => 0.275], ['thickness' => 0.27]);

    expect(ccConsume($o, $c)->exists)->toBeTrue();
});

it('respecte la tolérance d’épaisseur propre à l’OF quand elle est renseignée', function () {
    // L'OF impose 0,001 mm : l'écart de 5/1000 devient inacceptable.
    ['order' => $o, 'coil' => $c] = ccContext(
        ['thickness' => 0.275],
        ['thickness' => 0.27, 'tolerance_epaisseur' => 0.001]
    );

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'Épaisseur attendue');
});

it('refuse une largeur hors tolérance', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['width' => 1250], ['largeur_totale' => 1000]);

    expect(fn () => ccConsume($o, $c))
        ->toThrow(ValidationException::class, 'Largeur attendue 1000 mm, bobine 1250 mm');
});

it('accepte une largeur au millimètre près', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['width' => 1000.5], ['largeur_totale' => 1000]);

    expect(ccConsume($o, $c)->exists)->toBeTrue();
});

it('refuse un revêtement différent', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['coating' => 'galvanisé'], ['revetement' => 'prélaqué']);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'Revêtement attendu');
});

// ── 6. Données absentes : autorisé, mais journalisé ──────────────────────────

it('autorise quand une caractéristique manque d’un seul côté, et le journalise', function () {
    // L'OF attend du beige ; la bobine ne dit rien. Deux valeurs dont une absente
    // ne sont pas deux valeurs égales — on autorise sans prétendre avoir vérifié.
    ['order' => $o, 'coil' => $c] = ccContext(['color' => null], ['color' => 'beige']);

    // `Log::spy()` seul rend null sur channel() : il faut lui dire de se renvoyer
    // lui-même pour que la chaîne channel()->notice() reste observable.
    $journal = Log::spy();
    $journal->shouldReceive('channel')->andReturnSelf();

    expect(ccConsume($o, $c)->exists)->toBeTrue();

    $warnings = app(CoilCompatibilityService::class)->warnings($o, $c->fresh());

    expect(array_column($warnings, 'critere'))->toContain('couleur');
    $journal->shouldHaveReceived('notice')->with('production.coil_compatibility.non_verifiee', Mockery::any());
});

it('autorise quand la caractéristique manque des deux côtés, sans la déclarer conforme', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['color' => null], ['color' => null]);

    expect(ccConsume($o, $c)->exists)->toBeTrue();

    $warnings = app(CoilCompatibilityService::class)->warnings($o, $c->fresh());
    expect(array_column($warnings, 'critere'))->toContain('couleur');
});

it('signale l’absence de nomenclature comme non vérifiable plutôt que conforme', function () {
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'pf' => $pf] = ccContext();
    $o = ProductionOrder::create([
        'company_id' => $co->id, 'number' => 'OF-SANSBOM-'.uniqid(), 'product_id' => $pf->id,
        'depot_matiere_id' => $wh->id, 'quantity_requested' => 10, 'status' => 'en_cours',
    ]);
    $c = ccCoil($co, $mp, $wh);

    expect(ccConsume($o, $c)->exists)->toBeTrue()
        ->and(array_column(app(CoilCompatibilityService::class)->warnings($o, $c->fresh()), 'critere'))
        ->toContain('nomenclature');
});

// ── 7-10. État, société, lot, dépôt, réservation ─────────────────────────────

it('refuse une bobine épuisée', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['remaining_weight' => 0, 'status' => 'epuisee']);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'épuisée');
});

it('refuse une bobine bloquée par la qualité', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['quality_status' => Coil::QUALITY_QUARANTINED]);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'statut qualité');
});

it('refuse une bobine divisée — sa matière appartient à ses filles', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['transformation_status' => Coil::TRANSFO_SPLIT]);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'divisée ou transformée');
});

it('refuse une bobine d’une autre société', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $o] = ccContext();
    $autre = Company::create(['name' => 'Autre SA', 'email' => 'autre@oa-metal.test']);
    $c = ccCoil($autre, $mp, $wh);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'autre société');
});

it('refuse une bobine dont le lot porte un autre article', function () {
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'order' => $o] = ccContext();
    $autreArticle = Product::factory()->create(['name' => 'Article du lot']);
    $lot = StockLot::create([
        'product_id' => $autreArticle->id, 'warehouse_id' => $wh->id,
        'lot_number' => 'LOT-INCOH-'.uniqid(), 'quantity' => 100, 'initial_quantity' => 100,
        'stock_uom' => 'KG', 'unit_cost' => 800, 'status' => 'disponible',
    ]);
    $c = ccCoil($co, $mp, $wh, ['stock_lot_id' => $lot->id]);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'lot de stock porte l’article');
});

it('refuse une bobine stockée dans un dépôt que l’OF ne consomme pas', function () {
    ['co' => $co, 'mp' => $mp, 'order' => $o] = ccContext();
    $ailleurs = ccWarehouse($co, 'DEP-AILLEURS');
    $c = ccCoil($co, $mp, $ailleurs);

    expect(fn () => ccConsume($o, $c))->toThrow(ValidationException::class, 'dépôt');
});

it('refuse une bobine dont la matière est intégralement réservée à un autre OF', function () {
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'pf' => $pf, 'order' => $o, 'coil' => $c] = ccContext();

    ProductStock::create([
        'product_id' => $mp->id, 'warehouse_id' => $wh->id,
        'quantity' => 500, 'reserved_quantity' => 500, 'avg_cost' => 800,
    ]);
    $autreOf = ccOrder($co, $pf, $mp, $wh);
    StockReservation::create([
        'company_id' => $co->id, 'production_order_id' => $autreOf->id,
        'product_id' => $mp->id, 'warehouse_id' => $wh->id,
        'quantity' => 500, 'status' => 'reserved', 'reserved_at' => now(),
    ]);

    expect(fn () => ccConsume($o, $c))
        ->toThrow(ValidationException::class, 'réservée à un autre ordre de fabrication');
});

it('laisse consommer quand la réservation d’un autre OF ne couvre pas tout le stock', function () {
    // Un stock abondant ne devient pas inconsommable parce qu'un autre OF en a
    // réservé une part : la garde ne s'oppose qu'à une matière entièrement promise.
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'pf' => $pf, 'order' => $o, 'coil' => $c] = ccContext();

    ProductStock::create([
        'product_id' => $mp->id, 'warehouse_id' => $wh->id,
        'quantity' => 5000, 'reserved_quantity' => 500, 'avg_cost' => 800,
    ]);
    $autreOf = ccOrder($co, $pf, $mp, $wh);
    StockReservation::create([
        'company_id' => $co->id, 'production_order_id' => $autreOf->id,
        'product_id' => $mp->id, 'warehouse_id' => $wh->id,
        'quantity' => 500, 'status' => 'reserved', 'reserved_at' => now(),
    ]);

    expect(ccConsume($o, $c)->exists)->toBeTrue();
});

// ── 11-13. Quantité, concurrence, verrou ─────────────────────────────────────

it('refuse une quantité supérieure au restant de la bobine', function () {
    ['order' => $o, 'coil' => $c] = ccContext(['remaining_weight' => 40]);

    expect(fn () => ccConsume($o, $c, 60))
        ->toThrow(ValidationException::class, 'supérieur au restant');
});

it('deux consommations concurrentes ne peuvent pas dépasser le restant', function () {
    // Séquentiel et déterministe : la seconde lit l'état commité par la première,
    // exactement ce que produit le verrou en situation réelle. La concurrence
    // parallèle vraie est couverte par ConcurrencyGuardsTest.
    ['order' => $o, 'coil' => $c] = ccContext(['remaining_weight' => 100]);

    expect(ccConsume($o, $c, 70)->exists)->toBeTrue();
    expect(fn () => ccConsume($o, $c->fresh(), 70))
        ->toThrow(ValidationException::class, 'supérieur au restant');

    expect((float) $c->fresh()->remaining_weight)->toBe(30.0);
});

it('rejoue le contrôle de compatibilité SOUS VERROU, pas seulement à l’entrée', function () {
    // Entre la vérification d'entrée et l'écriture, la bobine peut être divisée,
    // déplacée ou bloquée. Le contrôle doit donc s'exécuter deux fois par
    // consommation : une fois hors transaction, une fois le verrou tenu.
    ['order' => $o, 'coil' => $c] = ccContext();

    $partiel = Mockery::mock(CoilCompatibilityService::class)->makePartial();
    $partiel->shouldReceive('assertCompatible')->twice()->passthru();
    app()->instance(CoilCompatibilityService::class, $partiel);

    expect(app(CoilConsumptionService::class)->consume($o, $c, 10)->exists)->toBeTrue();
});

// ── 14. Sélecteurs ───────────────────────────────────────────────────────────

it('ne propose pas les bobines incompatibles dans le sélecteur de l’OF', function () {
    ['co' => $co, 'wh' => $wh, 'order' => $o, 'coil' => $compatible] = ccContext();

    $horsNomenclature = ccCoil($co, Product::factory()->create(), $wh);
    $autreDepot       = ccCoil($co, $o->billOfMaterial->lines->first()?->product_id
        ? Product::find(DB::table('bom_lines')->where('bill_of_material_id', $o->bill_of_material_id)->value('product_id'))
        : Product::factory()->create(), ccWarehouse($co, 'DEP-AUTRE'));
    $enQuarantaine    = ccCoil($co, Product::find(DB::table('bom_lines')
        ->where('bill_of_material_id', $o->bill_of_material_id)->value('product_id')), $wh,
        ['quality_status' => Coil::QUALITY_QUARANTINED]);

    $proposees = app(CoilCompatibilityService::class)->compatibleCoilsQuery($o)->pluck('id')->all();

    expect($proposees)->toContain($compatible->id)
        ->and($proposees)->not->toContain($horsNomenclature->id)
        ->and($proposees)->not->toContain($autreDepot->id)
        ->and($proposees)->not->toContain($enQuarantaine->id);
});

it('l’écran filtré ne dispense pas du contrôle serveur', function () {
    // Une requête forgée présentant une bobine absente du sélecteur doit être
    // refusée par le service : le filtrage d'écran est un confort, pas une garde.
    ['co' => $co, 'wh' => $wh, 'order' => $o] = ccContext();
    $horsNomenclature = ccCoil($co, Product::factory()->create(), $wh);

    expect(app(CoilCompatibilityService::class)->compatibleCoilsQuery($o)->pluck('id')->all())
        ->not->toContain($horsNomenclature->id);

    expect(fn () => ccConsume($o, $horsNomenclature))->toThrow(ValidationException::class);
});
