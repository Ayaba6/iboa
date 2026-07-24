<?php

/**
 * [R2 §2 — dérogation `force` non forgeable, motivée, autorisée, journalisée]
 *
 * Le lancement en rupture matière (`bypass_material`) n'est PAS pilotable depuis
 * la requête seule : le droit `production.validate` est vérifié serveur. Sans ce
 * droit, le flag est ignoré et le lancement reste bloqué. Avec le droit, un motif
 * est OBLIGATOIRE et la dérogation est journalisée (trace d'audit).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function deroSetup(array $perms): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DERO'], ['email' => 'dero@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $roleName = 'role_' . implode('_', $perms);
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    foreach ($perms as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    // Permissions créées en cours de test → purge du cache Spatie sinon le
    // middleware voit un jeu de permissions périmé (403 au lieu du comportement métier).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-DERO'], ['name' => 'Dépôt', 'is_default' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'allow_negative_stock' => false]);
    $pf = Product::factory()->create(['is_stockable' => true]);
    // Matière SUIVIE en stock avec 0 disponible → rupture garantie (besoin 5, dispo 0).
    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM DERO', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $mp->id, 'quantity_per_meter' => 5, 'waste_rate' => 0, 'sort_order' => 0]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'number' => 'OF-DERO-' . uniqid(),
        'status' => 'matiere_allouee', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id,
        'order_id' => null, 'quantity_requested' => 1, 'quantity_produced' => 0,
    ]);

    return [$co, $u, $of];
}

it('NON FORGEABLE : sans production.validate, bypass_material est ignoré — lancement bloqué', function () {
    // Utilisateur habilité à lancer, MAIS pas valideur.
    [, $u, $of] = deroSetup(['production.view', 'production.launch']);

    $this->actingAs($u)
        ->post(route('production.orders.launch', $of), ['bypass_material' => 1, 'derogation_motif' => 'tentative'])
        ->assertSessionHasErrors(); // rupture matière : blocage maintenu

    expect($of->fresh()->status)->toBe('matiere_allouee') // NON lancé
        ->and(\App\Models\AuditLog::where('action', 'production.derogation.lancement')->exists())->toBeFalse();
});

it('MOTIF OBLIGATOIRE : valideur sans motif → refus, aucune dérogation journalisée', function () {
    [, $u, $of] = deroSetup(['production.view', 'production.launch', 'production.validate']);

    $this->actingAs($u)
        ->post(route('production.orders.launch', $of), ['bypass_material' => 1])
        ->assertSessionHasErrors('derogation_motif');

    expect($of->fresh()->status)->toBe('matiere_allouee')
        ->and(\App\Models\AuditLog::where('action', 'production.derogation.lancement')->exists())->toBeFalse();
});

it('DÉROGATION FORMELLE : valideur + motif → OF lancé et dérogation journalisée', function () {
    [, $u, $of] = deroSetup(['production.view', 'production.launch', 'production.validate']);

    $this->actingAs($u)
        ->post(route('production.orders.launch', $of), [
            'bypass_material'  => 1,
            'derogation_motif' => 'Urgence client, matière en réappro sous 48h',
        ])
        ->assertSessionHasNoErrors();

    expect($of->fresh()->status)->toBe('lance'); // lancé en dérogation

    $log = \App\Models\AuditLog::where('action', 'production.derogation.lancement')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->new_values['motif'] ?? null)->toBe('Urgence client, matière en réappro sous 48h')
        ->and((int) $log->model_id)->toBe($of->id);
});
