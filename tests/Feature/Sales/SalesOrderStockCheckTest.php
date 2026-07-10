<?php

/**
 * Contrôle de disponibilité au moment de la validation d'une commande.
 *
 * Point métier vérifié :
 *  - le contrôle porte sur le dépôt de livraison réel de la commande ;
 *  - stock suffisant sur ce dépôt = rien à produire (commande servable directement) ;
 *  - stock insuffisant = manquant remonté (proposition OF / blocage livraison directe) ;
 *  - article non vendable = exclu de la sélection commerciale (scope sellable).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\SalesProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function soscAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SOSC'], ['email' => 'sosc@sosc.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function soscOrder(Product $p, int $qty, ?int $whId): Order
{
    $co = Company::first();
    $o  = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-SOSC' . rand(100, 999),
        'status' => 'confirme', 'issued_at' => now(), 'delivery_warehouse_id' => $whId,
    ]);
    $o->items()->create(['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => 1000, 'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000]);

    return $o;
}

it('stock suffisant sur le dépôt de livraison → rien à produire (commande servable)', function () {
    $this->actingAs(soscAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'SOK', 'name' => 'Dépôt OK', 'is_active' => true, 'is_default' => true]);
    $p  = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $analysis = app(SalesProductionService::class)->stockAnalysis(soscOrder($p, 40, $wh->id));
    $line     = $analysis['lines']->first();

    expect((float) $line['available'])->toBe(100.0);
    expect((float) $line['to_produce'])->toBe(0.0);
});

it('stock insuffisant sur le dépôt de livraison → manquant remonté (OF / blocage)', function () {
    $this->actingAs(soscAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'SKO', 'name' => 'Dépôt court', 'is_active' => true, 'is_default' => true]);
    $p  = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 15, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $analysis = app(SalesProductionService::class)->stockAnalysis(soscOrder($p, 40, $wh->id));
    $line     = $analysis['lines']->first();

    // 40 commandé − 15 dispo = 25 à produire/approvisionner.
    expect((float) $line['available'])->toBe(15.0);
    expect((float) $line['to_produce'])->toBe(25.0);
});

it('réservation stock : le disponible tient compte du réservé existant', function () {
    $this->actingAs(soscAdmin());
    $co = Company::first();
    $wh = Warehouse::create(['company_id' => $co->id, 'code' => 'SRV', 'name' => 'Dépôt réservé', 'is_active' => true, 'is_default' => true]);
    $p  = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true]);
    // 50 en stock dont 30 déjà réservés par d'autres commandes → 20 réellement disponibles.
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'reserved_quantity' => 30, 'avg_cost' => 1000]);

    $analysis = app(SalesProductionService::class)->stockAnalysis(soscOrder($p, 40, $wh->id));
    $line     = $analysis['lines']->first();

    expect((float) $line['available'])->toBe(20.0);
    expect((float) $line['to_produce'])->toBe(20.0);
});

it('article non vendable : exclu de la sélection commerciale (scope sellable)', function () {
    $this->actingAs(soscAdmin());
    $vendable    = Product::factory()->create(['is_sellable' => true]);
    $nonVendable = Product::factory()->create(['is_sellable' => false]);

    $sellableIds = Product::sellable()->pluck('id');

    expect($sellableIds)->toContain($vendable->id);
    expect($sellableIds)->not->toContain($nonVendable->id);
});
