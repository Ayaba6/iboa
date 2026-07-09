<?php

/**
 * [BUG-007] Cohérence du contrôle de disponibilité commande ↔ BL : le contrôle
 * commande (SalesProductionService::stockAnalysis) se base sur le dépôt de
 * livraison de la commande quand il est fixé — le même dépôt que le BL utilisera.
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

function dscAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DSC'], ['email' => 'dsc@dsc.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function dscOrder(Product $p, int $qty, ?int $whId): Order
{
    $co = Company::first();
    $o = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-DSC' . rand(100, 999),
        'status' => 'confirme', 'issued_at' => now(), 'delivery_warehouse_id' => $whId,
    ]);
    $o->items()->create(['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => 1000, 'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000]);

    return $o;
}

it('dépôt de livraison vide → à produire > 0 même si le stock existe ailleurs (cohérent avec le BL)', function () {
    $this->actingAs(dscAdmin());
    $co = Company::first();
    $wA = Warehouse::create(['company_id' => $co->id, 'code' => 'A', 'name' => 'Dépôt A (vide)', 'is_active' => true, 'is_default' => true]);
    $wB = Warehouse::create(['company_id' => $co->id, 'code' => 'B', 'name' => 'Dépôt B (stock)', 'is_active' => true]);
    $p  = Product::factory()->create(['is_stockable' => true]);
    // Stock uniquement en B ; la commande livre depuis A (vide).
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wB->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $analysis = app(SalesProductionService::class)->stockAnalysis(dscOrder($p, 30, $wA->id));
    $line = $analysis['lines']->first();

    // Dépôt A vide → 0 disponible → 30 à produire/transférer (le BL sur A échouerait aussi).
    expect((float) $line['available'])->toBe(0.0);
    expect((float) $line['to_produce'])->toBe(30.0);
});

it('dépôt de livraison non fixé → disponibilité tous dépôts (le BL résout le dépôt détenant le stock)', function () {
    $this->actingAs(dscAdmin());
    $co = Company::first();
    Warehouse::create(['company_id' => $co->id, 'code' => 'A2', 'name' => 'A2', 'is_active' => true, 'is_default' => true]);
    $wB = Warehouse::create(['company_id' => $co->id, 'code' => 'B2', 'name' => 'B2', 'is_active' => true]);
    $p  = Product::factory()->create(['is_stockable' => true]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wB->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    $analysis = app(SalesProductionService::class)->stockAnalysis(dscOrder($p, 30, null));
    $line = $analysis['lines']->first();

    // Pas de dépôt fixé → dispo tous dépôts (50) → rien à produire.
    expect((float) $line['available'])->toBe(50.0);
    expect((float) $line['to_produce'])->toBe(0.0);
});
