<?php

/**
 * [Qualité] Le responsable qualité doit pouvoir écrire dans son propre module.
 *
 * Quatre contrôleurs qualité gardaient leurs actions d'écriture derrière la seule
 * `production.update`. Or `responsable_qualite` porte `quality.manage` et NON
 * `production.update` : le rôle qui possède le module en était verrouillé — cinq
 * 403 relevés (plan de contrôle, contrôle, non-conformité, CAPA, formulaires de
 * création). `QualityReleaseController` utilisait déjà `quality.manage` ; les
 * quatre autres étaient les exceptions.
 *
 * Le correctif est un OU : `production.update|quality.manage`. Aucun droit
 * existant n'est retiré — ces tests le prouvent dans les deux sens.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qualUser(string $role): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QUAL'], ['email' => 'qual@qual.io', 'current_fiscal_year_id' => $fy->id]);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::where('name', $role)->firstOrFail());

    return $u;
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

it('confirme que responsable_qualite porte quality.manage sans production.update', function () {
    // Sans cet écart, le test suivant ne prouverait rien : il faut que le rôle
    // soit réellement dépourvu de la permission de production.
    $perms = qualUser('responsable_qualite')->getAllPermissions()->pluck('name');

    expect($perms)->toContain('quality.manage');
    expect($perms)->not->toContain('production.update');
});

it('ouvre les formulaires de création qualité au responsable qualité', function (string $route) {
    $this->actingAs(qualUser('responsable_qualite'))
        ->get(route($route))
        ->assertOk();
})->with([
    'qualite.control-plans.create',
    'qualite.inspections.create',
    'qualite.non-conformities.create',
]);

it('n\'oppose plus 403 au responsable qualité sur les écritures qualité', function (string $route) {
    // Charge utile vide : on attend un échec de VALIDATION, jamais un refus
    // d'autorisation. C'est la distinction 422/302 contre 403 qui est testée.
    $this->actingAs(qualUser('responsable_qualite'))
        ->post(route($route), [])
        ->assertStatus(302);
})->with([
    'qualite.control-plans.store',
    'qualite.inspections.store',
    'qualite.non-conformities.store',
]);

it('conserve l\'accès du chef de production, qui n\'a que production.update', function () {
    $perms = qualUser('chef_production')->getAllPermissions()->pluck('name');
    expect($perms)->toContain('production.update');
    expect($perms)->not->toContain('quality.manage');

    // Le OU est additif : le profil qui passait avant passe toujours.
    $this->actingAs(qualUser('chef_production'))
        ->get(route('qualite.control-plans.create'))
        ->assertOk();
});

it('refuse toujours celui qui ne détient ni production.update ni quality.manage', function () {
    $u = qualUser('lecture_seule');
    $perms = $u->getAllPermissions()->pluck('name');
    expect($perms)->not->toContain('production.update');
    expect($perms)->not->toContain('quality.manage');

    $this->actingAs($u)->get(route('qualite.control-plans.create'))->assertForbidden();
    $this->actingAs($u)->post(route('qualite.inspections.store'), [])->assertForbidden();
});
