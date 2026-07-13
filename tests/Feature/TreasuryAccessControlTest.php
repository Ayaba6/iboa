<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function userWithRole(string $role): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::where('name', $role)->firstOrFail());
    return $u;
}

it('interdit au commercial l\'accès au module Trésorerie', function () {
    $this->actingAs(userWithRole('commercial'))
        ->get(route('tresorerie.dashboard'))
        ->assertForbidden();
});

it('autorise le comptable et le caissier sur le module Trésorerie', function () {
    $this->actingAs(userWithRole('comptable'))->get(route('tresorerie.dashboard'))->assertOk();
    $this->actingAs(userWithRole('caissier'))->get(route('tresorerie.dashboard'))->assertOk();
});

it('conserve la visibilité des règlements ventes pour le commercial (payments.view)', function () {
    expect(userWithRole('commercial')->can('payments.view'))->toBeTrue()
        ->and(userWithRole('commercial')->can('treasury.view'))->toBeFalse();
});
