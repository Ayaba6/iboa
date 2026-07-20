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

function slCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SL'], ['email' => 'sl@sl.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function slActor(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

function slPayload(Product $product, array $over = []): array
{
    return array_merge([
        'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 1, 'unit_price' => $product->sale_price, 'discount_percent' => 0, 'tax_rate_value' => 0,
    ], $over);
}

it('refuse une longueur unitaire supérieure à la longueur maximale fabricable', function () {
    $co = slCompany();
    slActor($co);
    $tole = Product::factory()->create(['sale_price' => 4000, 'min_sale_price' => 0, 'longueur_min' => 2, 'longueur_max' => 12]);

    expect(fn () => app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [slPayload($tole, ['nb_toles' => 5, 'metrage_par_tole' => 15])], // 15 m > 12 m
    ]))->toThrow(\RuntimeException::class);

    expect(Order::count())->toBe(0);
});

it('refuse une longueur unitaire inférieure à la longueur minimale fabricable', function () {
    $co = slCompany();
    slActor($co);
    $tole = Product::factory()->create(['sale_price' => 4000, 'min_sale_price' => 0, 'longueur_min' => 2, 'longueur_max' => 12]);

    expect(fn () => app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [slPayload($tole, ['nb_toles' => 5, 'metrage_par_tole' => 1])], // 1 m < 2 m
    ]))->toThrow(\RuntimeException::class);

    expect(Order::count())->toBe(0);
});

it('accepte une longueur unitaire dans les bornes fabricables', function () {
    $co = slCompany();
    slActor($co);
    $tole = Product::factory()->create(['sale_price' => 4000, 'min_sale_price' => 0, 'longueur_min' => 2, 'longueur_max' => 12]);

    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [slPayload($tole, ['nb_toles' => 10, 'metrage_par_tole' => 5])], // 5 m ∈ [2;12]
    ]);

    expect((float) $order->items()->first()->quantity)->toBe(50.0); // 10 × 5
});

it('n\'applique aucun contrôle si l\'article n\'a pas de bornes', function () {
    $co = slCompany();
    slActor($co);
    $tole = Product::factory()->create(['sale_price' => 4000, 'min_sale_price' => 0, 'longueur_min' => null, 'longueur_max' => null]);

    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id, 'issued_at' => now(),
        'items' => [slPayload($tole, ['nb_toles' => 5, 'metrage_par_tole' => 99])], // aucune borne → OK
    ]);

    expect((float) $order->items()->first()->quantity)->toBe(495.0);
});
