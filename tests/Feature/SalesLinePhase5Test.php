<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function p5Company(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'P5'], ['email' => 'p5@p5.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function p5ItemPayload(Product $product, array $over = []): array
{
    return array_merge([
        'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 1, 'unit_price' => $product->sale_price, 'discount_percent' => 0, 'tax_rate_value' => 0,
    ], $over);
}

it('computes MTL quantity from nb_toles × métrage on a tôle line', function () {
    $co = p5Company();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($u);

    $tole = Product::factory()->create(['sale_price' => 4000, 'min_sale_price' => 0]);
    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [p5ItemPayload($tole, ['nb_toles' => 10, 'metrage_par_tole' => 6, 'quantity' => 1])],
    ]);

    $line = $order->items()->first();
    expect((float) $line->quantity)->toBe(60.0);         // 10 × 6 = 60 MTL
    expect((float) $line->nb_toles)->toBe(10.0);
    expect((float) $line->metrage_par_tole)->toBe(6.0);
});

it('blocks a sale below the floor price for an unprivileged user', function () {
    $co = p5Company();
    $u = User::factory()->create(['company_id' => $co->id]); // aucun rôle privilégié
    $this->actingAs($u);

    $product = Product::factory()->create(['sale_price' => 5000, 'min_sale_price' => 5000]);

    expect(fn () => app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [p5ItemPayload($product, ['unit_price' => 3000])],
    ]))->toThrow(\RuntimeException::class);

    expect(Order::count())->toBe(0);
});

it('allows a below-floor sale for a privileged role (special authorization)', function () {
    $co = p5Company();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']));
    $this->actingAs($u);

    $product = Product::factory()->create(['sale_price' => 5000, 'min_sale_price' => 5000]);
    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [p5ItemPayload($product, ['unit_price' => 3000])],
    ]);

    expect($order->items()->first()->unit_price)->toBe(3000);
});
