<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Gamme opératoire : Création] Champs complémentaires :
 * - routings : type de gamme, unité de gestion, dépôt de production, méthode de suivi,
 *   version active et propriétés d'exécution (dépassement temps, tolérance rendement,
 *   gestion rebuts, transfert auto, blocage point de contrôle KO).
 * - routing_operations : code opération, type, temps d'attente, point de contrôle, critique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            $table->string('type_gamme', 30)->nullable()->default('fabrication')->after('code'); // fabrication | sous_traitance | controle
            $table->foreignId('unite_gestion_id')->nullable()->after('product_id')
                  ->constrained('units')->nullOnDelete();
            $table->foreignId('depot_production_id')->nullable()->after('unite_gestion_id')
                  ->constrained('warehouses')->nullOnDelete();
            $table->string('methode_suivi', 30)->nullable()->default('par_operation')->after('type_gamme'); // par_operation | globale | par_lot
            $table->boolean('version_active')->default(true)->after('version_mineure');

            // Propriétés d'exécution
            $table->boolean('allow_time_overrun')->default(false)->after('controle_qualite');
            $table->decimal('tolerance_rendement', 5, 2)->nullable()->after('allow_time_overrun');
            $table->boolean('gestion_rebuts')->default(true)->after('tolerance_rendement');
            $table->boolean('auto_transfer')->default(false)->after('gestion_rebuts');
            $table->boolean('block_on_control_fail')->default(false)->after('auto_transfer');
        });

        Schema::table('routing_operations', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('operation_number');          // OP10
            $table->string('type_operation', 30)->nullable()->after('code');            // fabrication | manutention | controle
            $table->decimal('waiting_minutes', 8, 2)->default(0)->after('labor_minutes');
            $table->string('point_controle', 20)->nullable()->after('controle_qualite'); // PC01
            $table->boolean('is_critical')->default(false)->after('point_controle');
        });
    }

    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unite_gestion_id');
            $table->dropConstrainedForeignId('depot_production_id');
            $table->dropColumn([
                'type_gamme', 'methode_suivi', 'version_active',
                'allow_time_overrun', 'tolerance_rendement', 'gestion_rebuts', 'auto_transfer', 'block_on_control_fail',
            ]);
        });

        Schema::table('routing_operations', function (Blueprint $table) {
            $table->dropColumn(['code', 'type_operation', 'waiting_minutes', 'point_controle', 'is_critical']);
        });
    }
};
