<?php

/**
 * [MTS §2] « Chaque OF doit indiquer clairement son origine. »
 *
 * Il l'indiquait — et il se trompait. Le listener de confirmation de commande
 * (TriggerMtoProductionOnOrderConfirmed) ne posait AUCUNE origine : l'OF héritait
 * du défaut « manuel » de la colonne alors qu'il naissait d'une commande client.
 * Trois OF sur six l'affichaient à tort en base de développement, rattachés
 * pourtant à CMD-2026-001, 002 et 003.
 *
 * Conséquence : le filtre par origine de la liste des OF rendait un résultat
 * faux, et toute analyse de la part du carnet de commandes dans la production
 * avec lui.
 *
 * Le formulaire n'était PAS en cause : il envoie toujours une valeur et
 * pré-sélectionne déjà « commande client » quand une commande est rattachée.
 * La dérivation ne corrige donc que le SILENCE, jamais un choix explicite.
 *
 * Les trois OF existants ne sont pas réécrits : changer l'origine d'un OF
 * historique reviendrait à réécrire ce qui s'est passé.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function originUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'ORIG-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'Origin Co'], [
        'email' => 'origin@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WORIG'], [
        'name' => 'WORIG', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'origin_prod', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.create', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/**
 * Commande client. Le statut est paramétrable : `confirm()` n'accepte QUE le
 * brouillon, alors que les autres scénarios veulent une commande déjà confirmée
 * à laquelle rattacher un OF.
 */
function originOrder(Product $p, string $status = 'confirme'): App\Models\Order
{
    $co = Company::first();
    $o = App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-ORIG-'.uniqid(), 'status' => $status, 'issued_at' => now(),
    ]);
    $o->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 10,
        'unit_price' => 1000, 'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000,
    ]);

    return $o;
}

// ── Dérivation quand l'origine n'est pas fournie ─────────────────────────────

it('déduit « commande client » d’un OF rattaché à une commande', function () {
    // LE défaut : c'est exactement ce que le listener produisait, et il donnait
    // « manuel » sur un OF né d'une commande.
    originUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id,
        'order_id'   => originOrder($p)->id,
        'quantity_requested' => 10,
    ]);

    expect($of->origin)->toBe('commande_client');
});

it('déduit « mrp » d’un OF né du calcul des besoins', function () {
    originUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $of = app(ProductionService::class)->create(
        ['product_id' => $p->id, 'quantity_requested' => 10], [], 'mrp'
    );

    expect($of->origin)->toBe('mrp');
});

it('retient « manuel » quand rien ne désigne une autre origine', function () {
    originUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $of = app(ProductionService::class)->create(['product_id' => $p->id, 'quantity_requested' => 10]);

    expect($of->origin)->toBe('manuel');
});

// ── Un choix explicite n'est jamais écrasé ───────────────────────────────────

it('respecte une origine explicitement choisie, même contre-intuitive', function () {
    // Le formulaire envoie TOUJOURS une valeur. Écraser ce choix reviendrait à
    // décider à la place de l'utilisateur : on ne corrige que le silence.
    originUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id,
        'order_id'   => originOrder($p)->id,
        'origin'     => 'stock_minimum',
        'quantity_requested' => 10,
    ]);

    expect($of->origin)->toBe('stock_minimum');
});

it('traite une origine vide comme une absence, pas comme un choix', function () {
    originUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id, 'order_id' => originOrder($p)->id,
        'origin' => '', 'quantity_requested' => 10,
    ]);

    expect($of->origin)->toBe('commande_client');
});

// ── Le chemin réel : création automatique à la confirmation ──────────────────

it('pose la bonne origine sur l’OF créé automatiquement à la confirmation', function () {
    // Chemin de bout en bout : c'est celui qui produisait le défaut.
    originUser();
    $co = Company::first();
    $wh = Warehouse::where('code', 'WORIG')->first();

    $p = Product::factory()->create([
        'production_mode' => 'mto', 'is_stockable' => true, 'is_manufacturable' => true,
    ]);
    App\Modules\Production\Models\BillOfMaterial::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'name' => 'BOM ORIG', 'is_active' => true,
    ]);
    App\Models\ProductStock::create([
        'product_id' => $p->id, 'warehouse_id' => $wh->id,
        'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 500,
    ]);

    app(App\Services\OrderService::class)->confirm(originOrder($p, 'brouillon'));

    $of = ProductionOrder::where('product_id', $p->id)->first();

    expect($of)->not->toBeNull()
        ->and($of->order_id)->not->toBeNull()
        ->and($of->origin)->toBe('commande_client');   // et non « manuel »
});

it('n’écrit jamais une origine absente de l’énumération acceptée', function () {
    // Les valeurs dérivées doivent appartenir au référentiel valide par la
    // règle de validation du contrôleur : sinon l'OF passe le service et se fait
    // refuser au formulaire, ou pire, remplit la colonne d'une valeur inconnue.
    originUser();
    $autorisees = ['manuel', 'commande_client', 'stock_minimum', 'mrp'];

    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);
    $svc = app(ProductionService::class);

    $origines = [
        $svc->create(['product_id' => $p->id, 'quantity_requested' => 1])->origin,
        $svc->create(['product_id' => $p->id, 'quantity_requested' => 1], [], 'mrp')->origin,
        $svc->create(['product_id' => $p->id, 'order_id' => originOrder($p)->id, 'quantity_requested' => 1])->origin,
    ];

    foreach ($origines as $o) {
        expect($autorisees)->toContain($o);
    }
});
