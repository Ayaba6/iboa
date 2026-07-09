<?php

/**
 * Conversion Devis → Commande.
 *
 * Point métier vérifié :
 *  - un devis validé (accepté) se convertit en commande ;
 *  - la commande conserve le client, les lignes, quantités, prix et TVA du devis ;
 *  - un même devis ne peut pas être converti deux fois (garde converted_to_order_id).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qtocAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QTOC'], ['email' => 'qtoc@qtoc.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('convertit un devis accepté en commande en conservant client, lignes, quantités, prix et TVA', function () {
    $this->actingAs(qtocAdmin());
    $client  = Client::factory()->create();
    $product = Product::factory()->create();
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 QTOC'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $svc   = app(QuoteService::class);
    $quote = $svc->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article devis',
            'quantity' => 7, 'unit_price' => 1500, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // Devis : 7 × 1500 = 10 500 HT ; TVA 18% = 1 890 ; TTC = 12 390.
    expect((int) $quote->subtotal_ht)->toBe(10500);
    expect((int) $quote->total_tax)->toBe(1890);

    $svc->accept($quote);              // brouillon → accepte
    $order = $svc->convertToOrder($quote->fresh());

    // Rattachement + conservation des totaux financiers.
    expect((int) $order->client_id)->toBe((int) $client->id);
    expect((int) $order->quote_id)->toBe((int) $quote->id);
    expect((int) $order->subtotal_ht)->toBe(10500);
    expect((int) $order->total_tax)->toBe(1890);
    expect((int) $order->total_ttc)->toBe(12390);

    // Conservation ligne à ligne.
    expect($order->items)->toHaveCount(1);
    $line = $order->items->first();
    expect((int) $line->product_id)->toBe((int) $product->id);
    expect((float) $line->quantity)->toEqual(7.0);
    expect((int) $line->unit_price)->toBe(1500);
    expect((float) $line->tax_rate_value)->toEqual(18.0);
    expect((int) $line->line_tax)->toBe(1890);

    // Devis marqué converti + pointeur vers la commande.
    expect($quote->fresh()->status)->toBe('converti');
    expect((int) $quote->fresh()->converted_to_order_id)->toBe((int) $order->id);
});

it('interdit de convertir deux fois le même devis', function () {
    $this->actingAs(qtocAdmin());
    $svc   = app(QuoteService::class);
    $quote = $svc->create([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'description' => 'Ligne libre', 'quantity' => 1, 'unit_price' => 5000,
            'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]);
    $svc->accept($quote);
    $svc->convertToOrder($quote->fresh());

    // Seconde conversion refusée (déjà converti).
    expect(fn () => $svc->convertToOrder($quote->fresh()))
        ->toThrow(\RuntimeException::class);
});
