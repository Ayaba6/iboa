<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * [MTO §1] Permission de dérogation : créer un OF portant un article MTO sans
 * commande client rattachée.
 *
 * Volontairement attachée à AUCUN rôle. Contrairement aux permissions créées par
 * `add_granular_action_permissions`, celle-ci n'a pas de « permission source »
 * dont elle hériterait la population : c'est une dérogation à une règle de
 * gestion, pas un découpage d'un droit existant. La rattacher automatiquement à
 * un rôle reviendrait à ouvrir la dérogation à des utilisateurs que personne n'a
 * désignés. Elle s'accorde nommément, écran Rôles.
 *
 * Le super administrateur y accède de fait par le court-circuit `Gate::before`
 * en place dans AuthServiceProvider — mais il reste tenu de saisir un motif, et
 * sa dérogation est journalisée comme toute autre.
 */
return new class extends Migration
{
    private const PERMISSION = 'production.create_mto_without_order';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
