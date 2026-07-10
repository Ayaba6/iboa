<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Dépôt : Création complète ».
 * Ajoute site de rattachement, adresse détaillée (complément, CP, pays),
 * email de contact et option de stock négatif autorisé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'site')) {
                $table->string('site', 20)->nullable()->after('type');
            }
            if (! Schema::hasColumn('warehouses', 'address_complement')) {
                $table->string('address_complement', 255)->nullable()->after('address');
            }
            if (! Schema::hasColumn('warehouses', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('city');
            }
            if (! Schema::hasColumn('warehouses', 'country')) {
                $table->string('country', 60)->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('warehouses', 'email')) {
                $table->string('email', 120)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('warehouses', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(false)->after('can_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn([
                'site', 'address_complement', 'postal_code', 'country',
                'email', 'allow_negative_stock',
            ]);
        });
    }
};
