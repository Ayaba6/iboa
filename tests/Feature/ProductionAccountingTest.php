<?php

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionAccountingService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function paCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Acct Co'], ['email' => 'acct@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function paAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => paCompany()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function paOrderInProgress(): ProductionOrder
{
    $co      = paCompany();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $wh      = Warehouse::firstOrCreate(['code' => 'WH-PA'], ['name' => 'PA WH', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-8000',
        'status' => 'en_cours', 'quantity_requested' => 10, 'product_id' => $product->id,
        // Scénarios comptables — le contrôle qualité obligatoire est couvert
        // par ProductionClosureGuardsTest.
        'controle_qualite_obligatoire' => false,
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-PA1', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100); // material 50000

    $out = app(ProductionStockService::class)->recordOutput($order, [
        'warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000, // PF value 30000
    ]);
    // [CDC §13.3] Visa chef d'équipe — requis pour la clôture
    $out->update(['status' => 'validee', 'validated_at' => now()]);

    return $order->fresh();
}

it('is disabled by default — finishing posts no production entries', function () {
    config(['production.accounting.enabled' => false]);
    $this->actingAs(paAdmin());
    $order = paOrderInProgress();

    $this->post(route('production.orders.finish', $order))->assertRedirect();

    expect(JournalEntry::where('reference', 'like', $order->number . '%')->count())->toBe(0);
});

it('posts SYSCOHADA entries when enabled', function () {
    config(['production.accounting.enabled' => true]);
    $this->actingAs(paAdmin());
    $order = paOrderInProgress();

    $this->post(route('production.orders.finish', $order))->assertRedirect();

    $cons = JournalEntry::where('reference', $order->number . '-CONS')->with('lines.account')->first();
    $prod = JournalEntry::where('reference', $order->number . '-PROD')->with('lines.account')->first();

    expect($cons)->not->toBeNull();
    expect($prod)->not->toBeNull();

    // Consommation : DR 6032 = 50000, CR 321 = 50000
    expect($cons->total_debit)->toBe(50000);
    expect($cons->lines->firstWhere('account.code', '6032')->debit)->toBe(50000);
    expect($cons->lines->firstWhere('account.code', '321')->credit)->toBe(50000);

    // Production stockée : DR 361 = 30000, CR 736 = 30000
    expect($prod->total_debit)->toBe(30000);
    expect($prod->lines->firstWhere('account.code', '361')->debit)->toBe(30000);
    expect($prod->lines->firstWhere('account.code', '736')->credit)->toBe(30000);
});

it('is idempotent — re-posting does not duplicate entries', function () {
    config(['production.accounting.enabled' => true]);
    $this->actingAs(paAdmin());
    $order = paOrderInProgress();
    $order->update(['status' => 'termine', 'finished_at' => now()]);

    $svc = app(ProductionAccountingService::class);
    $svc->postForOrder($order);
    $svc->postForOrder($order);

    expect(JournalEntry::where('reference', 'like', $order->number . '%')->count())->toBe(2);
});
