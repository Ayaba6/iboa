<?php

/**
 * [Audit final stabilisation] Gardes de la chaîne MTO/MTS :
 * double OF refusé, OF complémentaire limité au reliquat, révocation d'approbation,
 * permission d'approbation, sur-consommation bobine, marchandise sans OF.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function agCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'AG-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'AG Co'], ['email' => 'ag@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function agAdmin(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

/** Commande confirmée avec une ligne MTO de 50 (10 tôles × 5 m). */
function agOrderWithLine(Company $co, Product $product, float $qty = 50): Order
{
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-AG-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 200000,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'description' => $product->name, 'quantity' => $qty,
        'unit_price' => 4000, 'line_total_ht' => (int) ($qty * 4000), 'line_tax' => 0,
        'line_total_ttc' => (int) ($qty * 4000), 'sort_order' => 0,
    ]);

    return $order;
}

it('refuse un second OF quand le premier couvre déjà toute la quantité commandée', function () {
    $co = agCompany();
    agAdmin($co);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 50);

    app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 50,
    ]);

    expect(fn () => app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 50,
    ]))->toThrow(ValidationException::class);

    expect(ProductionOrder::where('order_id', $order->id)->count())->toBe(1);
});

it('autorise un OF complémentaire égal au reliquat mais refuse au-delà', function () {
    $co = agCompany();
    agAdmin($co);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 100);

    app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 60,
    ]);

    // Complémentaire > reliquat (40) → refusé
    expect(fn () => app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 45,
    ]))->toThrow(ValidationException::class);

    // Complémentaire = reliquat → accepté
    $of2 = app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 40,
    ]);
    expect((float) $of2->quantity_requested)->toBe(40.0)
        ->and(ProductionOrder::where('order_id', $order->id)->count())->toBe(2);
});

it('un OF annulé libère le reliquat pour un nouvel OF', function () {
    $co = agCompany();
    agAdmin($co);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 50);

    $of = app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 50,
    ]);
    $of->update(['status' => 'annule']);

    $of2 = app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $tole->id, 'quantity_requested' => 50,
    ]);
    expect($of2)->not->toBeNull();
});

it('révoque une approbation de production (et la commande redevient non éligible)', function () {
    $co = agCompany();
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $admin = agAdmin($co);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 50);
    $order->update(['production_approved' => true, 'production_approved_at' => now(), 'production_approved_by' => $admin->id]);

    expect(Order::eligibleForProduction()->count())->toBe(1);

    $this->post(route('ventes.commandes.revoke-production', $order))->assertSessionHas('success');

    expect($order->fresh()->production_approved)->toBeFalse()
        ->and(Order::eligibleForProduction()->count())->toBe(0);
});

it('refuse la révocation quand un OF actif existe', function () {
    $co = agCompany();
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $admin = agAdmin($co);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 50);
    $order->update(['production_approved' => true, 'production_approved_at' => now(), 'production_approved_by' => $admin->id]);
    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-AG-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 50, 'product_id' => $tole->id, 'order_id' => $order->id,
    ]);

    $this->post(route('ventes.commandes.revoke-production', $order))->assertSessionHas('error');
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('refuse l\'approbation à un utilisateur sans la permission production.approve_financial', function () {
    $co = agCompany();
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $tole = Product::factory()->create(['production_mode' => 'mto']);
    $order = agOrderWithLine($co, $tole, 50);

    $sansPermission = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now(), 'is_active' => true]);
    $this->actingAs($sansPermission)
        ->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Tentative non autorisée'])
        ->assertForbidden();

    expect($order->fresh()->production_approved)->toBeFalse();
});

it('refuse une consommation bobine supérieure au restant', function () {
    $co = agCompany();
    agAdmin($co);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-AG-' . uniqid(), 'status' => 'en_cours',
        'quantity_requested' => 10, 'product_id' => Product::factory()->create()->id,
    ]);
    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-AG-' . uniqid(), 'initial_weight' => 100,
        'remaining_weight' => 100, 'cost_per_kg' => 400, 'purchase_price' => 40000, 'status' => 'disponible',
    ]);

    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil, 150))
        ->toThrow(ValidationException::class);

    expect((float) $coil->fresh()->remaining_weight)->toBe(100.0);
});

it('une marchandise achetée-revendue ne déclenche jamais d\'OF ni n\'est éligible', function () {
    $co = agCompany();
    agAdmin($co);
    // production_mode null = marchandise achetée-revendue (ni mto ni mts)
    $marchandise = Product::factory()->create(['production_mode' => null]);
    $order = agOrderWithLine($co, $marchandise, 20);
    $order->update(['production_approved' => true]);

    // Pas éligible au tableau production (aucun article MTO)
    expect(Order::eligibleForProduction()->count())->toBe(0)
        // Et aucun OF auto n'existe
        ->and(ProductionOrder::where('order_id', $order->id)->count())->toBe(0);
});

// [Règle formelle — matrice annulations] Un OF ayant consommé de la matière ou
// déclaré de la production ne s'annule pas en un clic.
it('refuse d\'annuler un OF avec consommation ou production vivante', function () {
    $co = agCompany();
    $u = agAdmin($co);
    test()->actingAs($u);
    $p = Product::factory()->create(['production_mode' => 'mto', 'is_manufacturable' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-CANC-' . uniqid(), 'status' => 'en_cours',
        'product_id' => $p->id, 'quantity_requested' => 10,
    ]);
    $coil = \App\Modules\Production\Models\Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-CANC', 'initial_weight' => 100,
        'remaining_weight' => 80, 'cost_per_kg' => 500, 'purchase_price' => 50000, 'status' => 'disponible',
    ]);
    \App\Modules\Production\Models\ProductionConsumption::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'coil_id' => $coil->id,
        'weight_consumed' => 20, 'consumed_at' => now(), 'consumption_source' => 'manuelle',
    ]);

    expect(fn () => app(\App\Modules\Production\Services\ProductionService::class)->cancel($of->fresh(), 'test'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($of->fresh()->status)->toBe('en_cours');

    // Consommation extournée → l'annulation redevient possible
    \App\Modules\Production\Models\ProductionConsumption::where('production_order_id', $of->id)
        ->update(['reversed_at' => now()]);
    app(\App\Modules\Production\Services\ProductionService::class)->cancel($of->fresh(), 'annulation après extourne');
    expect($of->fresh()->status)->toBe('annule');
});
