<?php

/**
 * [Paramétrage Vente X3] Résolution de prix, remises paramétrées,
 * client bloqué, seuil de validation.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\SalesDiscount;
use App\Models\SalesSetting;
use App\Models\User;
use App\Services\QuoteService;
use App\Services\Sales\SalesPricingService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function scfgAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SC Co'], ['email' => 'sc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('résout le prix : tarif client prioritaire sur le prix de base', function () {
    $this->actingAs(scfgAdmin());
    $client  = Client::create(['code' => 'C-SC1', 'type' => 'entreprise', 'name' => 'Client Tarif', 'is_active' => true]);
    $product = Product::factory()->create(['sale_price' => 5000]);

    ProductPriceTier::create([
        'product_id' => $product->id, 'client_id' => $client->id,
        'label' => 'Tarif négocié', 'price' => 4200, 'is_active' => true,
    ]);

    $r = app(SalesPricingService::class)->resolve($client, $product);
    expect($r['price'])->toBe(4200.0)->and($r['source'])->toBe('tarif client');

    // Sans client → prix de base
    $r2 = app(SalesPricingService::class)->resolve(null, $product);
    expect($r2['price'])->toBe(5000.0)->and($r2['source'])->toBe('prix de base');
});

it('applique la remise paramétrée la plus favorable et signale le seuil de validation', function () {
    $this->actingAs(scfgAdmin());
    SalesSetting::current()->update(['discount_validation_threshold' => 10]);
    $client  = Client::create(['code' => 'C-SC2', 'type' => 'entreprise', 'name' => 'Client Remise', 'groupe_client' => 'GROSSISTES', 'is_active' => true]);
    $product = Product::factory()->create(['sale_price' => 10000]);

    SalesDiscount::create([
        'company_id' => currentCompany()->id, 'name' => 'Remise grossistes',
        'discount_type' => 'groupe_client', 'client_group' => 'GROSSISTES', // colonne sales_discounts
        'rate_percent' => 15, 'is_active' => true,
    ]);

    $r = app(SalesPricingService::class)->resolve($client, $product);
    expect($r['discount_percent'])->toBe(15.0)
        ->and($r['price'])->toBe(8500.0)
        ->and($r['discount_name'])->toBe('Remise grossistes')
        ->and($r['requires_validation'])->toBeTrue(); // 15 % > seuil 10 %
});

it('signale la vente sous prix plancher', function () {
    $this->actingAs(scfgAdmin());
    $product = Product::factory()->create(['sale_price' => 5000, 'min_sale_price' => 4800]);
    $client  = Client::create(['code' => 'C-SC3', 'type' => 'entreprise', 'name' => 'Client Plancher', 'default_discount' => 10, 'is_active' => true]);

    $r = app(SalesPricingService::class)->resolve($client, $product);
    // 5000 − 10 % = 4500 < plancher 4800
    expect($r['below_floor'])->toBeTrue()->and($r['requires_validation'])->toBeTrue();
});

it('refuse tout devis pour un client bloqué', function () {
    $this->actingAs(scfgAdmin());
    $client = Client::create([
        'code' => 'C-SC4', 'type' => 'entreprise', 'name' => 'Client Bloqué',
        'is_active' => true, 'is_blocked' => true, 'blocked_reason' => 'Contentieux en cours',
    ]);

    expect(fn () => app(QuoteService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [],
    ]))->toThrow(RuntimeException::class, 'bloque');
});

it('affiche le hub Paramétrage Vente et enregistre les paramètres généraux', function () {
    $this->actingAs(scfgAdmin());

    $this->get(route('settings.sales.hub'))->assertOk()->assertSee('Paramétrage Vente');

    $this->put(route('settings.sales.settings.update'), [
        'discount_validation_threshold' => 12.5,
        'quote_validity_days'           => 45,
        'enforce_price_floor'           => 1,
        'reserve_stock_on_quote'        => 0,
        'allow_direct_invoicing'        => 1,
        'block_sales_on_overdue'        => 0,
        'require_order_for_delivery'    => 1,
    ])->assertRedirect();

    $s = SalesSetting::current();
    expect((float) $s->discount_validation_threshold)->toBe(12.5)
        ->and($s->quote_validity_days)->toBe(45);
});
