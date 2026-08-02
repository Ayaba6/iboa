<?php

/**
 * [D5] MTO strict : l'OF porte la quantité commandée COMPLÈTE.
 *
 * `TriggerMtoProductionOnOrderConfirmed` calculait :
 *
 *     quantité à produire = quantité commandée − stock général disponible
 *
 * et `ReservationService` réservait le reste sur le stock général. Deux gestes
 * qui traitent la tôle bac comme un article de stock alors qu'elle est
 * fabriquée sur mesure.
 *
 * Ce que la déduction supposait sans jamais le vérifier : que du stock portant
 * le même code article est INTERCHANGEABLE avec la commande. Il ne l'est pas.
 * Un même code peut couvrir des couleurs, épaisseurs, profils, largeurs,
 * longueurs, nuances et revêtements différents ; le stock peut être en
 * quarantaine, affecté à un autre client, ou issu d'un autre OF.
 *
 * Règle appliquée : aucune déduction automatique. Une réutilisation de stock MTO
 * passera par une réaffectation explicite, tracée et contrôlée — workflow qui
 * n'existe pas encore, et dont l'absence ne doit pas se traduire par une
 * déduction silencieuse.
 */

use App\Events\OrderConfirmed;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\Sales\FulfillmentStrategyResolver;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtofSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTOF-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MTOF Co'], ['email' => 'mtof@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function mtofAdmin(): User
{
    $u = User::factory()->create(['company_id' => mtofSociete()->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

/** Dépôt de vente ordinaire. */
function mtofDepot(string $code = 'WMTOF', ?string $type = 'produit_fini'): Warehouse
{
    return Warehouse::firstOrCreate(['code' => $code], [
        'name' => $code, 'company_id' => mtofSociete()->id, 'type' => $type,
        'can_sale' => true, 'can_delivery' => true, 'can_stock' => true,
        'is_active' => true, 'is_default' => $code === 'WMTOF',
    ]);
}

/** Article tôle bac : MTO, fabriqué, avec nomenclature active. */
function mtofArticle(): Product
{
    $cat = ItemCategory::firstOrCreate(['code' => 'PF_TOLE_MTO_T'], ['name' => 'Tôles MTO', 'strategy' => 'mto', 'is_sellable' => true, 'is_stockable' => true, 'is_manufactured' => true]);
    $p = Product::factory()->create([
        'production_mode' => 'mto', 'item_category_id' => $cat->id,
        'is_sellable' => true, 'is_stockable' => true, 'is_manufacturable' => true,
        'is_active' => true, 'sale_price' => 4000,
    ]);
    BillOfMaterial::firstOrCreate(
        ['product_id' => $p->id, 'name' => 'BOM MTOF'],
        ['company_id' => mtofSociete()->id, 'is_active' => true]
    );

    return $p;
}

/** Commande confirmée de `$ml` mètres linéaires sur l'article MTO. */
function mtofCommande(Product $produit, float $ml = 50): Order
{
    $co = mtofSociete();
    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true, 'payment_mode' => Client::PAYMENT_CASH])->id,
        'number' => 'CMD-MTOF-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'total_ttc' => (int) ($ml * 4000), 'invoiced_amount' => 0,
    ]);
    OrderItem::create([
        'order_id' => $commande->id, 'product_id' => $produit->id, 'description' => $produit->name,
        'unit_id' => Unit::firstOrCreate(['name' => 'ML MTOF'], ['abbreviation' => 'mlm'])->id,
        'quantity' => $ml, 'unit_price' => 4000,
        'line_total_ht' => (int) ($ml * 4000), 'line_tax' => 0, 'line_total_ttc' => (int) ($ml * 4000),
    ]);

    return $commande->fresh();
}

function mtofStock(Product $produit, Warehouse $depot, float $qte, float $reserve = 0): ProductStock
{
    return ProductStock::create([
        'product_id' => $produit->id, 'warehouse_id' => $depot->id,
        'quantity' => $qte, 'reserved_quantity' => $reserve,
    ]);
}

// ─── 8-9 · L'OF porte la quantité commandée, quel que soit le stock ──────────

it('8. crée un OF de 50 ML pour une commande de 50 ML malgré 20 ML en stock', function () {
    mtofAdmin();
    $produit = mtofArticle();
    mtofStock($produit, mtofDepot(), 20);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    $of = ProductionOrder::where('order_id', $commande->id)->first();
    expect($of)->not->toBeNull();
    expect((float) $of->quantity_requested)->toBe(50.0);
});

it('9. crée un OF de 50 ML même si le stock général couvre toute la commande', function () {
    mtofAdmin();
    $produit = mtofArticle();
    mtofStock($produit, mtofDepot(), 50);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    // L'ancienne logique concluait « rien à produire » et ne créait aucun OF :
    // la commande partait sur un stock dont rien ne prouve la compatibilité.
    $of = ProductionOrder::where('order_id', $commande->id)->first();
    expect($of)->not->toBeNull();
    expect((float) $of->quantity_requested)->toBe(50.0);
});

// ─── 10-12 · Aucun stock n'est jamais retenu pour une ligne MTO ──────────────

it('10. ignore un stock du même article dans un autre dépôt', function () {
    mtofAdmin();
    $produit = mtofArticle();
    // Même code article, autre dépôt : rien ne dit que la couleur, l'épaisseur
    // ou le profil correspondent à la commande.
    mtofStock($produit, mtofDepot('WMTOF-AUTRE', 'produit_fini'), 50);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    expect((float) ProductionOrder::where('order_id', $commande->id)->value('quantity_requested'))->toBe(50.0);
    expect(StockReservation::where('order_id', $commande->id)->count())->toBe(0);
});

it('11. ignore un stock déjà réservé par une autre commande', function () {
    mtofAdmin();
    $produit = mtofArticle();
    mtofStock($produit, mtofDepot(), 50, reserve: 50);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    expect((float) ProductionOrder::where('order_id', $commande->id)->value('quantity_requested'))->toBe(50.0);
});

it('12. ignore un stock en quarantaine', function () {
    mtofAdmin();
    $produit = mtofArticle();
    mtofStock($produit, mtofDepot('WMTOF-QUAR', 'quarantaine'), 50);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    expect((float) ProductionOrder::where('order_id', $commande->id)->value('quantity_requested'))->toBe(50.0);
    expect(StockReservation::where('order_id', $commande->id)->count())->toBe(0);
});

// ─── 13 · Aucune réservation automatique de stock général en MTO ─────────────

it('13. ne réserve aucun stock général à la confirmation d\'une commande MTO', function () {
    mtofAdmin();
    $produit = mtofArticle();
    $depot = mtofDepot();
    $stock = mtofStock($produit, $depot, 80);
    $commande = mtofCommande($produit, 50);

    event(new OrderConfirmed($commande));

    expect(StockReservation::where('order_id', $commande->id)->count())->toBe(0);
    expect((float) $stock->fresh()->reserved_quantity)->toBe(0.0);
});

it('13bis. annonce explicitement la décision MTO dans l\'analyse de la commande', function () {
    mtofAdmin();
    $produit = mtofArticle();
    mtofStock($produit, mtofDepot(), 30);
    $commande = mtofCommande($produit, 50);

    $analyse = app(\App\Modules\Production\Services\SalesProductionService::class)->stockAnalysis($commande);
    $ligne = $analyse['lines']->first();

    expect($ligne['mode'])->toBe(FulfillmentStrategyResolver::MTO);
    expect((float) $ligne['reservable'])->toBe(0.0);
    expect((float) $ligne['to_produce'])->toBe(50.0);
    expect($ligne['source'])->toBe('commande');
});
