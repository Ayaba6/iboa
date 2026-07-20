<?php

/**
 * [CDC §31.33] Séparation des tâches : le déclarant d'une production ne peut pas
 * valider CONFORME sa propre production (auto-libération interdite). L'auto-signalement
 * d'un défaut reste permis. Dérogation réservée à super_admin.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionStockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function sodCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SOD-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SOD Co'], ['email' => 'sod@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function sodUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => sodCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
    // Le contrôle qualité de l'OF exige production.update ; on l'accorde au rôle non-admin
    // (super_admin outrepasse déjà toutes les permissions via Gate::before).
    if ($role !== 'super_admin' && \Spatie\Permission\Models\Permission::where('name', 'production.update')->where('guard_name', 'web')->exists()) {
        $u->givePermissionTo('production.update');
    }

    return $u;
}

function sodOrderWithOutput(User $declarer): ProductionOrder
{
    $co = sodCompany();
    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SOD-' . uniqid(), 'status' => 'en_cours',
        'quantity_requested' => 10, 'controle_qualite_obligatoire' => true,
        'product_id' => Product::factory()->create()->id,
    ]);
    // La déclaration porte created_by = déclarant (posé par recordOutput via Auth::id()).
    test()->actingAs($declarer);
    app(ProductionStockService::class)->recordOutput($order, ['quantity' => 5, 'length' => 6]);

    return $order->fresh();
}

function sodQcPayload(string $status = 'conforme'): array
{
    return [
        'status' => $status, 'thickness_ok' => true, 'length_ok' => true,
        'color_ok' => true, 'visual_ok' => true, 'rejected_quantity' => 0,
    ];
}

it('interdit au déclarant de valider CONFORME sa propre production', function () {
    $declarer = sodUser('chef_atelier'); // a production.update
    $order = sodOrderWithOutput($declarer);

    $this->actingAs($declarer)
        ->post(route('production.orders.quality', $order), sodQcPayload('conforme'))
        ->assertSessionHas('error');

    expect($order->qualityControls()->where('status', 'conforme')->count())->toBe(0);
});

it('autorise un AUTRE contrôleur à valider la conformité', function () {
    $declarer = sodUser('chef_atelier');
    $order = sodOrderWithOutput($declarer);
    $controller = sodUser('chef_atelier'); // utilisateur différent

    $this->actingAs($controller)
        ->post(route('production.orders.quality', $order), sodQcPayload('conforme'))
        ->assertSessionHas('success');

    expect($order->qualityControls()->where('status', 'conforme')->count())->toBe(1);
});

it('autorise le déclarant à signaler lui-même une non-conformité', function () {
    $declarer = sodUser('chef_atelier');
    $order = sodOrderWithOutput($declarer);

    $this->actingAs($declarer)
        ->post(route('production.orders.quality', $order), sodQcPayload('non_conforme'))
        ->assertSessionHas('success');

    expect($order->qualityControls()->where('status', 'non_conforme')->count())->toBe(1);
});

it('autorise super_admin à déroger (valider sa propre production)', function () {
    $admin = sodUser('super_admin');
    $order = sodOrderWithOutput($admin);

    $this->actingAs($admin)
        ->post(route('production.orders.quality', $order), sodQcPayload('conforme'))
        ->assertSessionHas('success');

    expect($order->qualityControls()->where('status', 'conforme')->count())->toBe(1);
});
