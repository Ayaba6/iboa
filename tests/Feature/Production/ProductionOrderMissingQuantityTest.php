<?php

/**
 * [BUG-003] L'OF auto-généré (MTO) réclame le manquant réel (commandé − stock),
 * pas la quantité totale commandée — malgré la réservation propre de la commande.
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

it('OF réclame uniquement le manquant (80 en stock, commande 100 → OF 20)', function () {
    $this->actingAs(pomqAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['production_mode' => 'mto', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $p->id, 'name' => 'BOM POMQ', 'is_active' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 80, 'reserved_quantity' => 0, 'avg_cost' => 500]);

    app(OrderService::class)->confirm(pomqOrder($p, 100));

    $of = ProductionOrder::where('product_id', $p->id)->first();
    expect($of)->not->toBeNull();
    expect((float) $of->quantity_requested)->toBe(20.0);
});

it('aucun OF si le stock couvre toute la commande', function () {
    $this->actingAs(pomqAdmin());
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['production_mode' => 'mto', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $p->id, 'name' => 'BOM POMQ2', 'is_active' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 200, 'reserved_quantity' => 0, 'avg_cost' => 500]);

    app(OrderService::class)->confirm(pomqOrder($p, 100));

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});
