<?php

/**
 * [MRP] Propositions d'ordre de fabrication.
 *
 * Le MRP ne savait proposer que des ACHATS : `MrpService` ne regardait que les
 * bobines, comparait un poids disponible à `stock_min` et sortait une demande
 * d'achat. Les articles FABRIQUÉS pour le stock n'étaient couverts par aucune
 * proposition — alors que `origin = 'mrp'` figurait déjà dans l'énumération des
 * OF sans qu'aucun code puisse la produire. C'est l'écart MTS §3.
 *
 * Le besoin vient de NetRequirementService, partagé avec l'écran de
 * planification MTS : une seule implémentation de la règle, pas deux.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\MrpService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function mrpUser(array $permissions = ['production.view', 'production.create', 'production.update']): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MRPOF-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'MrpOf Co'], [
        'email' => 'mrpof@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WMRPOF'], [
        'name' => 'WMRPOF', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'mrpof_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/** Article MTS avec cible, stock, et — au choix — une nomenclature active. */
function mrpProduct(float $cible = 500, float $stock = 0, bool $withBom = true, string $mode = 'mts'): Product
{
    $co = Company::first();
    $p = Product::factory()->create([
        'production_mode' => $mode, 'is_stockable' => true, 'is_active' => true,
        'is_manufacturable' => true,
        'stock_max' => $cible, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0,
    ]);

    ProductStock::create([
        'product_id' => $p->id, 'warehouse_id' => Warehouse::where('code', 'WMRPOF')->value('id'),
        'quantity' => $stock, 'reserved_quantity' => 0, 'avg_cost' => 500,
    ]);

    if ($withBom) {
        BillOfMaterial::create([
            'company_id' => $co->id, 'product_id' => $p->id,
            'name' => 'BOM '.$p->name, 'is_active' => true,
        ]);
    }

    return $p;
}

// ── Propositions ─────────────────────────────────────────────────────────────

it('propose un OF pour un article MTS sous sa cible', function () {
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 120);

    $proposals = app(MrpService::class)->productionProposals();

    expect($proposals)->toHaveCount(1)
        ->and($proposals[0]['p']->id)->toBe($p->id)
        ->and($proposals[0]['besoin'])->toBe(380.0);
});

it('ne propose rien quand le stock couvre la cible', function () {
    mrpUser();
    mrpProduct(cible: 100, stock: 500);

    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
});

it('écarte un article sans nomenclature active — un besoin ne suffit pas à fabriquer', function () {
    mrpUser();
    mrpProduct(cible: 500, stock: 0, withBom: false);

    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
});

it('écarte un article dont la nomenclature est désactivée', function () {
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 0);
    BillOfMaterial::where('product_id', $p->id)->update(['is_active' => false]);

    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
});

it('ne propose pas un article sans aucun seuil défini', function () {
    // Cible nulle ⇒ besoin nul. Le MRP ne devine pas une cible qu'on ne lui a
    // pas donnée ; l'écran MTS le signale par « Seuil non défini ».
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 0);
    $p->update(['stock_max' => 0, 'stock_min' => 0, 'reorder_point' => 0, 'stock_securite' => 0]);

    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
});

// ── Génération ───────────────────────────────────────────────────────────────

it('génère les OF avec l’origine « mrp » et la nomenclature active', function () {
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 120);

    $result = app(MrpService::class)->generateProductionOrders();

    expect($result['created'])->toHaveCount(1)
        ->and($result['skipped'])->toBeEmpty();

    $of = ProductionOrder::where('product_id', $p->id)->first();
    expect($of)->not->toBeNull()
        ->and($of->origin)->toBe('mrp')
        ->and((float) $of->quantity_requested)->toBe(380.0)
        ->and($of->bill_of_material_id)->toBe(BillOfMaterial::where('product_id', $p->id)->value('id'))
        ->and($of->order_id)->toBeNull();   // MTS : aucune commande client, c'est normal
});

it('ne retient que les articles sélectionnés', function () {
    mrpUser();
    $retenu = mrpProduct(cible: 500, stock: 0);
    $ecarte = mrpProduct(cible: 300, stock: 0);

    app(MrpService::class)->generateProductionOrders([$retenu->id]);

    expect(ProductionOrder::where('product_id', $retenu->id)->exists())->toBeTrue()
        ->and(ProductionOrder::where('product_id', $ecarte->id)->exists())->toBeFalse();
});

it('est naturellement idempotent : un second passage ne propose plus rien', function () {
    // Le besoin net déduit les OF déjà planifiés. Une fois l'OF créé, il couvre
    // le besoin et la proposition disparaît d'elle-même — sans verrou ni marqueur.
    mrpUser();
    mrpProduct(cible: 500, stock: 0);

    expect(app(MrpService::class)->generateProductionOrders()['created'])->toHaveCount(1);
    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
    expect(app(MrpService::class)->generateProductionOrders()['created'])->toBeEmpty();
    expect(ProductionOrder::count())->toBe(1);
});

it('ne propose jamais un article MTO — il relève des commandes à produire', function () {
    // Le MRP ne couvre que le MTS. Un article fabriqué à la commande passe par
    // l'écran « Commandes à produire », adossé à une commande client : proposer
    // sa fabrication sans commande serait refusé par la garde R1 de toute façon.
    mrpUser();
    $mto = mrpProduct(cible: 500, stock: 0, mode: 'mto');

    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
    expect(app(MrpService::class)->generateProductionOrders()['created'])->toBeEmpty();
    expect(ProductionOrder::where('product_id', $mto->id)->exists())->toBeFalse();
});

it('rend le motif du refus au lieu de l’avaler, et poursuit les autres articles', function () {
    // Refus réellement atteignable : la catégorie de l'article n'est pas
    // fabriquée, garde levée par ProductionService::create(). La génération ne
    // doit ni s'interrompre ni masquer la raison.
    mrpUser();
    $ok      = mrpProduct(cible: 500, stock: 0);
    $refuse  = mrpProduct(cible: 500, stock: 0);

    $categorie = \App\Models\ItemCategory::create([
        'code' => 'NONFAB-'.uniqid(), 'name' => 'Marchandise revendue',
        'nature' => 'marchandise', 'is_manufactured' => false,
    ]);
    $refuse->forceFill(['item_category_id' => $categorie->id])->save();

    $result = app(MrpService::class)->generateProductionOrders();

    expect($result['created'])->toHaveCount(1)
        ->and($result['created'][0]->product_id)->toBe($ok->id)
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['produit'])->toBe($refuse->name)
        ->and($result['skipped'][0]['raison'])->toContain('non fabriquée');

    expect(ProductionOrder::where('product_id', $refuse->id)->exists())->toBeFalse();
});

it('tient compte de la demande client ferme dans la quantité proposée', function () {
    mrpUser();
    $co = Company::first();
    $p  = mrpProduct(cible: 500, stock: 0);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-MRPOF-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 60,
        'delivered_quantity' => 0, 'unit_price' => 1000,
        'line_total_ht' => 60000, 'line_tax' => 0, 'line_total_ttc' => 60000,
    ]);

    expect(app(MrpService::class)->productionProposals()[0]['besoin'])->toBe(560.0);
});

// ── Écran ────────────────────────────────────────────────────────────────────

it('affiche l’écran des propositions', function () {
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 120);

    $this->get(route('production.mrp.of'))->assertOk()
        ->assertSee($p->name)
        ->assertSee('Générer les OF retenus');
});

it('génère les OF depuis l’écran et redirige vers la liste', function () {
    mrpUser();
    $p = mrpProduct(cible: 500, stock: 0);

    $this->post(route('production.mrp.of.generate'), ['product_ids' => [$p->id]])
        ->assertRedirect(route('production.orders.index'));

    expect(ProductionOrder::where('product_id', $p->id)->where('origin', 'mrp')->exists())->toBeTrue();
});

it('refuse la génération à qui n’a pas le droit de créer une production', function () {
    // Consulter le MRP ne donne pas le droit d'émettre des ordres de fabrication.
    mrpUser(['production.view', 'production.update']);
    $p = mrpProduct(cible: 500, stock: 0);

    $this->post(route('production.mrp.of.generate'), ['product_ids' => [$p->id]])->assertForbidden();

    expect(ProductionOrder::count())->toBe(0);
});

it('laisse l’écran consultable sans le droit de création', function () {
    // Tout le groupe de routes MRP exige déjà `production.update` — pilotage de
    // production, pas simple consultation. Ce qu'on vérifie ici, c'est qu'un
    // pilote SANS droit de création voit l'analyse mais pas le bouton.
    mrpUser(['production.view', 'production.update']);
    mrpProduct(cible: 500, stock: 0);

    $this->get(route('production.mrp.of'))->assertOk()
        ->assertDontSee('Générer les OF retenus');
});

it('refuse l’écran à qui n’a pas le droit de piloter la production', function () {
    mrpUser(['production.view']);

    $this->get(route('production.mrp.of'))->assertForbidden();
});
