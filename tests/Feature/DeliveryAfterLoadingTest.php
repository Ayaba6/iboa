<?php

/**
 * [CDC §13.7] Workflow livraison : commande validée → préparation → contrôle
 * chargement → BL. Tant que le bon de préparation n'est pas « chargé »,
 * la création du BL est verrouillée.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function dalCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'DAL-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'DAL Co'], ['email' => 'dal@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function dalAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => dalCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    return $u;
}

function dalOrder(): Order
{
    $co = dalCompany();
    return Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-DAL-' . uniqid(),
        'status'         => 'confirme',
        'issued_at'      => now(),
        'total_ttc'      => 100_000,
    ]);
}

function dalBp(Order $order, string $status): BonPreparation
{
    return BonPreparation::create([
        'company_id'     => $order->company_id,
        'order_id'       => $order->id,
        'fiscal_year_id' => $order->fiscal_year_id,
        'number'         => 'BP-DAL-' . uniqid(),
        'payment_mode'   => 'credit',
        'status'         => $status,
    ]);
}

it('verrouille la création du BL tant que le BP n\'est pas chargé', function () {
    $this->actingAs(dalAdmin());
    $order = dalOrder();
    dalBp($order, 'en_attente');

    expect($order->isReadyForDelivery())->toBeFalse();

    $this->post(route('ventes.commandes.delivery-note', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->deliveryNotes()->count())->toBe(0);
});

it('autorise le BL une fois le BP chargé', function () {
    $this->actingAs(dalAdmin());
    $order = dalOrder();
    dalBp($order, 'charge');

    expect($order->fresh()->isReadyForDelivery())->toBeTrue();
});

it('autorise le BL sans BP (flux direct)', function () {
    $this->actingAs(dalAdmin());
    $order = dalOrder();

    expect($order->isReadyForDelivery())->toBeTrue();
});
