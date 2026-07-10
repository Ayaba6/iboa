<?php

/**
 * [BUG-001] La TVA sélectionnée sur une ligne est calculée ET persistée :
 * total_tax et total_ttc reflètent le taux ligne après enregistrement.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function vatAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'VAT'], ['email' => 'vat@vat.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('applique et persiste la TVA 18% sur les lignes (total_tax / total_ttc corrects)', function () {
    $this->actingAs(vatAdmin());
    $product = Product::factory()->create();
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 VAT'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article taxé',
            'quantity' => 10, 'unit_price' => 1000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // 10 × 1000 = 10 000 HT ; TVA 18% = 1 800 ; TTC = 11 800.
    expect((int) $order->subtotal_ht)->toBe(10000);
    expect((int) $order->total_tax)->toBe(1800);
    expect((int) $order->total_ttc)->toBe(11800);
    // Persistance ligne.
    expect((float) $order->items->first()->tax_rate_value)->toEqual(18.0);
    expect((int) $order->items->first()->line_tax)->toBe(1800);
});

it('TVA forcée à 0 pour un client exonéré (défense serveur)', function () {
    $this->actingAs(vatAdmin());
    $product = Product::factory()->create();
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 VAT'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);
    $client  = Client::factory()->create(['is_tax_exempt' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article',
            'quantity' => 5, 'unit_price' => 2000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    expect((int) $order->total_tax)->toBe(0);
    expect((int) $order->total_ttc)->toBe(10000);
});
