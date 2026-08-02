<?php

/**
 * [D5] L'OF auto-généré en MTO porte la quantité commandée COMPLÈTE.
 *
 * RÈGLE INVERSÉE. Ce fichier affirmait l'inverse sous le titre « BUG-003 » :
 * l'OF ne réclamait que `commandé − stock général`, et aucun OF n'était créé
 * quand le stock couvrait la commande.
 *
 * La déduction supposait, sans jamais le vérifier, que du stock portant le même
 * `product_id` est interchangeable avec la commande. Il ne l'est pas : un même
 * code article couvre des couleurs, épaisseurs, profils, largeurs, longueurs,
 * nuances et revêtements différents ; le stock peut être en quarantaine, affecté
 * à un autre client, ou issu d'un autre OF. Aucune de ces dimensions n'entrait
 * dans le calcul — seul le code article était comparé.
 *
 * En MTO, c'est la commande qui déclenche la production, pas le manque de stock.
 * La réutilisation d'un reliquat passera par une réaffectation explicite et
 * tracée ; tant que ce workflow n'existe pas, l'OF couvre la totalité.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function pomqAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'POMQ'], ['email' => 'pomq@pomq.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WQ'], ['name' => 'WQ', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function pomqOrder(Product $p, int $qty): App\Models\Order
{
    $co = Company::first();
    $o = App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-POMQ' . rand(100, 999),
        'status' => 'brouillon', 'issued_at' => now(),
    ]);
    $o->items()->create(['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => 1000, 'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000]);

    return $o;
}

it('OF de 100 pour une commande de 100, malgré 80 en stock', function () {
    $this->actingAs(pomqAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['production_mode' => 'mto', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $p->id, 'name' => 'BOM POMQ', 'is_active' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 80, 'reserved_quantity' => 0, 'avg_cost' => 500]);

    app(OrderService::class)->confirm(pomqOrder($p, 100));

    $of = ProductionOrder::where('product_id', $p->id)->first();
    expect($of)->not->toBeNull();
    expect((float) $of->quantity_requested)->toBe(100.0);
});

it('crée quand même un OF quand le stock couvre toute la commande', function () {
    $this->actingAs(pomqAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['production_mode' => 'mto', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $p->id, 'name' => 'BOM POMQ2', 'is_active' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 200, 'reserved_quantity' => 0, 'avg_cost' => 500]);

    app(OrderService::class)->confirm(pomqOrder($p, 100));

    // 200 en stock pour 100 commandés : l'ancienne règle ne créait AUCUN OF et
    // servait la commande sur un reliquat dont rien ne prouvait la compatibilité.
    $of = ProductionOrder::where('product_id', $p->id)->first();
    expect($of)->not->toBeNull();
    expect((float) $of->quantity_requested)->toBe(100.0);

    // Et le stock général n'est pas réservé au passage.
    expect(\App\Models\StockReservation::where('order_id', $of->order_id)->count())->toBe(0);
});
