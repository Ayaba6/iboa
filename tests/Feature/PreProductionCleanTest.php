<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function cleanSeed(): Order
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CLEAN'], ['email' => 'c@c.io', 'current_fiscal_year_id' => $fy->id]);
    User::factory()->create(['company_id' => $co->id]);

    return Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-CLEAN-1', 'status' => 'confirme',
        'issued_at' => now(), 'total_ttc' => 50000,
    ]);
}

test('dry-run ne modifie rien', function () {
    cleanSeed();

    $this->artisan('erp:pre-production-clean')->assertSuccessful();

    expect(Order::count())->toBe(1);
});

test('execute purge le transactionnel et préserve le paramétrage', function () {
    cleanSeed();
    $product = Product::factory()->create();

    $this->artisan('erp:pre-production-clean', ['--execute' => true])
        ->expectsQuestion('Taper exactement NETTOYER PRODUCTION pour confirmer', 'NETTOYER PRODUCTION')
        ->assertSuccessful();

    expect(Order::count())->toBe(0)
        ->and(Product::count())->toBeGreaterThan(0)
        ->and(Client::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(0)
        ->and(Company::count())->toBeGreaterThan(0);
});

test('execute refuse une mauvaise confirmation', function () {
    cleanSeed();

    $this->artisan('erp:pre-production-clean', ['--execute' => true])
        ->expectsQuestion('Taper exactement NETTOYER PRODUCTION pour confirmer', 'oui')
        ->assertFailed();

    expect(Order::count())->toBe(1);
});
