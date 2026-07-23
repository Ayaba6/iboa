<?php

/**
 * [Phase 2.1 — E2E API] Cycle complet API Sanctum RÉEL (la table
 * personal_access_tokens n'avait jamais été migrée : l'API n'avait jamais
 * fonctionné — ces tests garantissent qu'elle ne re-cassera pas) :
 * émission de token, appel authentifié avec permission, 403 sans permission,
 * 401 sans token, révocation, désactivation du compte.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function apiUser(array $perms = []): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'API'], ['email' => 'api@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $role = Role::firstOrCreate(['name' => 'api-role-' . md5(implode(',', $perms)), 'guard_name' => 'web']);
    foreach ($perms as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now(), 'password' => bcrypt('Secret#2026')]);
    $u->assignRole($role);

    return [$u, $co];
}

it('cycle API complet : token, appel autorisé, 403 sans permission, révocation, désactivation', function () {
    [$u] = apiUser(['products.view']);

    // 1. Sans token → 401 (avant toute session)
    $this->getJson('/api/products')->assertUnauthorized();

    // 2. Émission de token par credentials
    $res = $this->postJson('/api/auth/token', [
        'email' => $u->email, 'password' => 'Secret#2026', 'device_name' => 'e2e-test',
    ])->assertOk();
    $token = $res->json('token');
    expect($token)->not->toBeNull();

    // 3. Appel authentifié avec permission → 200
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/products')->assertOk();

    // 4. Avec token mais SANS la permission clients.view → 403
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/clients')->assertForbidden();

    // 5. Mauvais identifiants → pas de token
    $this->postJson('/api/auth/token', [
        'email' => $u->email, 'password' => 'faux', 'device_name' => 'e2e-test',
    ])->assertUnprocessable();

    // 6. Désactivation du compte → token révoqué + accès refusé
    // (401 = token supprimé ; 403 = middleware is_active — les deux couches refusent)
    $u->update(['is_active' => false]);
    $status = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/products')->getStatusCode();
    expect($status)->toBeIn([401, 403])
        ->and($u->tokens()->count())->toBe(0);
});
