<?php

/**
 * [Flux tôle bac §3] Éligibilité d'une commande à la production :
 * réglée (bon de préparation actif = paiement caisse) OU approuvée gérant,
 * ET sans OF actif. + action gérant d'approbation.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function elCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'EL-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'EL Co'], ['email' => 'el@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function elOrder(Company $co, array $over = [], string $productionMode = 'mto'): Order
{
    $order = Order::create(array_merge([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-EL-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100000,
    ], $over));
    // L'éligibilité exige au moins un article MTO sur la commande.
    $p = Product::factory()->create(['production_mode' => $productionMode, 'sale_price' => 4000]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 10,
        'unit_price' => 4000, 'line_total_ht' => 40000, 'line_tax' => 0, 'line_total_ttc' => 40000, 'sort_order' => 0,
    ]);

    return $order;
}

it('exclut une commande confirmée ni réglée ni approuvée', function () {
    $co = elCompany();
    elOrder($co);
    expect(Order::eligibleForProduction()->count())->toBe(0);
});

it('inclut une commande approuvée par le gérant', function () {
    $co = elCompany();
    elOrder($co, ['production_approved' => true]);
    expect(Order::eligibleForProduction()->count())->toBe(1);
});

it('inclut une commande réglée (bon de préparation actif)', function () {
    $co = elCompany();
    $order = elOrder($co);
    BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id,
        'number' => 'BP-EL-' . uniqid(), 'status' => 'en_attente',
    ]);
    expect(Order::eligibleForProduction()->count())->toBe(1);
});

it('exclut une commande éligible qui a déjà un OF actif', function () {
    $co = elCompany();
    $order = elOrder($co, ['production_approved' => true]);
    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-EL-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 5, 'product_id' => Product::factory()->create()->id, 'order_id' => $order->id,
    ]);
    expect(Order::eligibleForProduction()->count())->toBe(0);
});

it('permet au gérant d\'approuver avec motif — trace motif, montant non réglé et validité', function () {
    $co = elCompany();
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $gerant = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now(), 'is_active' => true]);
    $gerant->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $order = elOrder($co);

    $this->actingAs($gerant)
        ->post(route('ventes.commandes.approve-production', $order), [
            'motif' => 'Client historique, règlement promis sous 8 jours', 'valide_jours' => 15,
        ])
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->production_approved)->toBeTrue()
        ->and($order->production_approved_by)->toBe($gerant->id)
        ->and($order->production_approval_reason)->toContain('Client historique')
        ->and((int) $order->production_approval_unpaid)->toBe(100000) // rien d'encaissé
        ->and($order->production_approval_expires_at->toDateString())->toBe(today()->addDays(15)->toDateString());
});

it('refuse une approbation sans motif', function () {
    $co = elCompany();
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $gerant = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now(), 'is_active' => true]);
    $gerant->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $order = elOrder($co);

    $this->actingAs($gerant)
        ->post(route('ventes.commandes.approve-production', $order))
        ->assertSessionHasErrors('motif');

    expect($order->fresh()->production_approved)->toBeFalse();
});

it('exclut une approbation expirée de l\'éligibilité', function () {
    $co = elCompany();
    elOrder($co, [
        'production_approved' => true,
        'production_approval_expires_at' => today()->subDay(),
    ]);
    expect(Order::eligibleForProduction()->count())->toBe(0);
});

it('exclut une commande ne portant que des articles MTS', function () {
    $co = elCompany();
    elOrder($co, ['production_approved' => true], 'mts');
    expect(Order::eligibleForProduction()->count())->toBe(0);
});
