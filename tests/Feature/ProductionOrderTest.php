<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function poAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'OF Test Co'],
        ['email' => 'of@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    if (! $company->current_fiscal_year_id) {
        $company->update(['current_fiscal_year_id' => $fy->id]);
    }
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeOrder(array $overrides = []): ProductionOrder
{
    test()->post(route('production.orders.store'), array_merge([
        'sheet_type' => 'bac', 'thickness' => 0.40, 'color' => 'Rouge',
        'lines' => [
            ['label' => 'Bac 6m', 'length' => 6, 'quantity' => 10],
            ['label' => 'Bac 4m', 'length' => 4, 'quantity' => 5],
        ],
    ], $overrides));

    return ProductionOrder::latest('id')->first();
}

it('creates an OF with auto number and qty derived from lines', function () {
    $this->actingAs(poAdmin());

    $order = makeOrder();

    expect($order)->not->toBeNull();
    expect($order->number)->toStartWith('OF-');
    expect($order->status)->toBe('brouillon');
    expect((float) $order->quantity_requested)->toEqual(15.0); // 10 + 5
    expect($order->lines)->toHaveCount(2);
    expect((float) $order->lines->firstWhere('label', 'Bac 6m')->total_meters)->toEqual(60.0); // 6*10
});

it('runs the full workflow brouillon -> termine', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();

    $this->post(route('production.orders.launch', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe('lance');
    expect($order->fresh()->launched_at)->not->toBeNull();

    $this->post(route('production.orders.start', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe('en_cours');

    // Production réelle déclarée avant clôture (= quantité demandée → pas d'écart).
    // QC obligatoire couvert par ProductionClosureGuardsTest — hors sujet ici.
    $order->update(['quantity_produced' => $order->quantity_requested, 'controle_qualite_obligatoire' => false]);
    $this->post(route('production.orders.finish', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe('termine');
    expect($order->fresh()->finished_at)->not->toBeNull();
});

it('blocks finishing an OF with zero produced quantity', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();

    $this->post(route('production.orders.launch', $order))->assertRedirect();
    $this->post(route('production.orders.start', $order))->assertRedirect();

    // Aucune production déclarée (quantity_produced = 0) → clôture refusée.
    $this->post(route('production.orders.finish', $order))->assertSessionHasErrors('quantity_produced');
    expect($order->fresh()->status)->toBe('en_cours');
});

it('rejects an invalid transition (finish a draft)', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();

    $this->post(route('production.orders.finish', $order))->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe('brouillon');
});

it('cancels an OF with a reason', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();

    $this->post(route('production.orders.cancel', $order), ['reason' => 'Erreur client'])->assertRedirect();
    expect($order->fresh()->status)->toBe('annule');
    expect($order->fresh()->notes)->toContain('Erreur client');
});

it('forbids editing a launched OF', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();
    $this->post(route('production.orders.launch', $order));      // lance
    $this->post(route('production.orders.start', $order));       // en_cours

    $this->get(route('production.orders.edit', $order))->assertForbidden();
});

it('prefills the OF create form from a sales order', function () {
    $this->actingAs(poAdmin());
    $co      = \App\Models\Company::first();
    $client  = \App\Models\Client::factory()->create();
    $product = \App\Models\Product::factory()->create();

    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-PREFILL-1', 'status' => 'confirme',
        'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'description' => 'Tôle bac', 'quantity' => 25, 'unit_price' => 1000,
        'line_total_ht' => 25000, 'line_tax' => 0, 'line_total_ttc' => 25000,
    ]);

    $this->get(route('production.orders.create', ['order_id' => $order->id]))
        ->assertOk()
        ->assertSee('CMD-PREFILL-1');
});

it('forbids deleting a non-draft OF', function () {
    $this->actingAs(poAdmin());
    $order = makeOrder();
    $this->post(route('production.orders.launch', $order));

    $this->delete(route('production.orders.destroy', $order))->assertRedirect();
    expect(ProductionOrder::find($order->id))->not->toBeNull();
});
