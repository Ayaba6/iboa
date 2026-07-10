<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Nomenclature : Création] Champs complémentaires :
 * code, type, dépôt de production, valorisation, priorité, version active
 * et propriétés de gestion (multi-niveaux, sous-nomenclatures, lots, série, verrou).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->after('company_id');            // NOM-2026-00045
            $table->string('type_nomenclature', 30)->nullable()->after('code');     // produit_fabrique | semi_fini | kit
            $table->foreignId('depot_production_id')->nullable()->after('product_id')
                  ->constrained('warehouses')->nullOnDelete();
            $table->string('valuation_method', 20)->nullable()->default('cout_standard')->after('depot_production_id');
            $table->string('priorite', 15)->nullable()->default('normale')->after('valuation_method');
            $table->boolean('version_active')->default(true)->after('version_mineure');

            // Propriétés
            $table->boolean('multi_niveaux')->default(false)->after('controle_qualite');
            $table->boolean('allow_sub_bom')->default(false)->after('multi_niveaux');
            $table->boolean('lot_management')->default(false)->after('allow_sub_bom');
            $table->boolean('serial_tracking')->default(false)->after('lot_management');
            $table->boolean('lock_modification')->default(false)->after('serial_tracking');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_production_id');
            $table->dropColumn([
                'code', 'type_nomenclature', 'valuation_method', 'priorite', 'version_active',
                'multi_niveaux', 'allow_sub_bom', 'lot_management', 'serial_tracking', 'lock_modification',
            ]);
        });
    }
};
