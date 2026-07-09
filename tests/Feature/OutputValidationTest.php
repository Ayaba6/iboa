<?php

/**
 * [CDC §13.3] « Opérateur : déclare production — Chef équipe : valide déclarations ».
 * Le visa chef d'équipe conditionne la clôture de l'OF.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ovCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'OV-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'OV Co'], ['email' => 'ov@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function ovAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => ovCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    return $u;
}

function ovOrder(): ProductionOrder
{
    $co = ovCompany();
    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-OV-' . uniqid(), 'status' => 'en_cours',
        'quantity_requested' => 10, 'controle_qualite_obligatoire' => false, 'product_id' => Product::factory()->create()->id,
    ]);
}

it('crée la déclaration en statut declaree — stock entré immédiatement', function () {
    $this->actingAs(ovAdmin());
    $order = ovOrder();

    $output = app(ProductionStockService::class)->recordOutput($order, ['quantity' => 5, 'length' => 6]);

    expect($output->status)->toBe('declaree')
        ->and($output->isValidated())->toBeFalse();
});

it('bloque la clôture OF tant que des déclarations attendent le visa', function () {
    $this->actingAs(ovAdmin());
    $order = ovOrder();
    app(ProductionStockService::class)->recordOutput($order, ['quantity' => 5, 'length' => 6]);

    expect(fn () => app(ProductionService::class)->finish($order))
        ->toThrow(ValidationException::class);
});

it('permet la clôture une fois toutes les déclarations validées', function () {
    $this->actingAs(ovAdmin());
    $order  = ovOrder();
    // Produit la quantité demandée (10) → pas d'écart, clôture définitive propre.
    $output = app(ProductionStockService::class)->recordOutput($order, ['quantity' => 10, 'length' => 6]);

    $output->update(['status' => 'validee', 'validated_by' => auth()->id(), 'validated_at' => now()]);

    app(ProductionService::class)->finish($order);
    expect($order->fresh()->status)->toBe('termine');
});

it('le chef atelier pose le visa via la route ; l\'opérateur ne peut pas', function () {
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

    $chef = User::factory()->create(['company_id' => ovCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $chef->assignRole('chef_atelier');
    $operateur = User::factory()->create(['company_id' => ovCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $operateur->assignRole('operateur_production');

    $this->actingAs(ovAdmin());
    $order  = ovOrder();
    $output = app(ProductionStockService::class)->recordOutput($order, ['quantity' => 3, 'length' => 6]);

    // Opérateur → 403
    $this->actingAs($operateur)
         ->post(route('production.outputs.validate', $output))
         ->assertForbidden();
    expect($output->fresh()->status)->toBe('declaree');

    // Chef atelier → visa posé
    $this->actingAs($chef)
         ->post(route('production.outputs.validate', $output))
         ->assertRedirect();
    $fresh = $output->fresh();
    expect($fresh->status)->toBe('validee')
        ->and($fresh->validated_by)->toBe($chef->id)
        ->and($fresh->validated_at)->not->toBeNull();
});
