<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * [SEC-PHASE2 §3] Permissions dédiées par action à effet financier/stock/juridique.
 * Une permission de création n'implique plus la validation ni l'annulation.
 *
 * Compatibilité : chaque nouvelle permission est attachée aux rôles qui
 * détiennent la permission « source » actuellement porteuse de l'accès de
 * fait — aucun utilisateur ne perd d'accès au déploiement. La séparation
 * stricte (retrait des permissions de validation/annulation aux profils
 * créateurs) se paramètre ensuite écran Rôles, par périmètre.
 */
return new class extends Migration
{
    /** nouvelle permission => permission source pour la compatibilité des rôles */
    private const MAP = [
        'credit_notes.validate'   => 'credit_notes.create',
        'credit_notes.cancel'     => 'sales.cancel',
        'receptions.validate'     => 'receptions.create',
        'receptions.cancel'       => 'receptions.create',
        'purchase_orders.confirm' => 'purchase_orders.create',
        'purchase_orders.cancel'  => 'purchase_orders.create',
        'payments.confirm'        => 'payments.create',
        'payments.cancel'         => 'treasury.write',
        'treasury.cancel'         => 'treasury.write',
        'cash_closures.validate'  => 'treasury.write',
        'cash_closures.reopen'    => 'treasury.validate',
    ];

    public function up(): void
    {
        foreach (self::MAP as $new => $source) {
            $perm = Permission::firstOrCreate(['name' => $new, 'guard_name' => 'web']);
            $src = Permission::where('name', $source)->where('guard_name', 'web')->first();
            if (! $src) {
                continue;
            }
            foreach (Role::whereHas('permissions', fn ($q) => $q->where('id', $src->id))->get() as $role) {
                $role->givePermissionTo($perm);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::MAP))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
