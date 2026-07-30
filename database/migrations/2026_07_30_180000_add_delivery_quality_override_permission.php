<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * [MTO §15] Permission de dérogation : livrer malgré un défaut ou une
 * insuffisance de conformité qualité.
 *
 * Attachée à AUCUN rôle, comme `production.create_mto_without_order`. Accepter
 * de livrer un produit non contrôlé engage l'entreprise vis-à-vis du client :
 * c'est un droit qui se donne nommément, à une personne identifiée, pas un
 * corollaire d'un profil d'administration.
 *
 * ProductionDeliveryGuard la vérifie via `hasPermissionTo()` et NON `can()` :
 * `Gate::before` accorde tout au super administrateur, et le cahier des charges
 * exige explicitement que la permission générale d'administration ne suffise pas
 * implicitement.
 */
return new class extends Migration
{
    private const PERMISSION = 'production.override_delivery_quality';

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
