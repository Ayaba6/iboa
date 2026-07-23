<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * [SEC-PHASE2 §1] Séparation des tâches EFFECTIVE dans les données.
 * L'héritage de compatibilité (migration 2026_07_23_120000) donnait les
 * nouvelles permissions de validation/annulation aux rôles créateurs —
 * annulant le bénéfice de la séparation. Cette migration retire les
 * permissions incompatibles des rôles opérationnels :
 *
 *  - caissier : encaisse, mais n'annule pas ses encaissements et ne valide
 *    pas sa propre clôture de caisse (comptable/DAF le font) ;
 *  - commercial : crée les avoirs, ne les valide pas (responsable
 *    commercial/comptable/DAF) ;
 *  - acheteur : prépare les CF, ne les confirme/annule pas (engagement —
 *    directeur/DAF) et ne valide pas les réceptions (magasinier réceptionne) ;
 *  - magasinier : valide la réception physique qu'il constate, mais ne
 *    l'ANNULE pas (défait le stock — responsable_stock/directeur_usine).
 *
 * Rôles de contrôle conservant ces permissions : directeur, daf, comptable,
 * responsable_commercial, responsable_stock, directeur_usine, super_admin.
 */
return new class extends Migration
{
    private const REVOKE = [
        'caissier'   => ['treasury.cancel', 'cash_closures.validate', 'payments.cancel'],
        'commercial' => ['credit_notes.validate'],
        'acheteur'   => ['purchase_orders.confirm', 'purchase_orders.cancel', 'receptions.validate', 'receptions.cancel'],
        'magasinier' => ['receptions.cancel'],
    ];

    /** daf confirme les CF au quotidien (l'acheteur ne le peut plus). */
    private const GRANT = [
        'daf' => ['purchase_orders.confirm', 'purchase_orders.cancel'],
    ];

    public function up(): void
    {
        foreach (self::REVOKE as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }
            foreach ($perms as $p) {
                if ($role->hasPermissionTo($p)) {
                    $role->revokePermissionTo($p);
                }
            }
        }
        foreach (self::GRANT as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }
            foreach ($perms as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
                $role->givePermissionTo($p);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::REVOKE as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                foreach ($perms as $p) {
                    $role->givePermissionTo($p);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
