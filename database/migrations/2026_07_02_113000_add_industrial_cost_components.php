<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §Coût de revient industriel]
 * Décomposition complète par produit : matière, main-d'œuvre, énergie,
 * maintenance, emballage — avec comparaison coût standard / coût réel.
 *
 * production_costs possède déjà energy_cost / maintenance_cost / packaging_cost
 * (migration antérieure) — guards hasColumn pour idempotence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_costs', 'energy_cost')) {
                $table->bigInteger('energy_cost')->default(0)->after('machine_cost');
            }
            if (! Schema::hasColumn('production_costs', 'maintenance_cost')) {
                $table->bigInteger('maintenance_cost')->default(0)->after('energy_cost');
            }
            if (! Schema::hasColumn('production_costs', 'packaging_cost')) {
                $table->bigInteger('packaging_cost')->default(0)->after('maintenance_cost');
            }
        });

        Schema::table('bills_of_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('bills_of_materials', 'packaging_per_unit')) {
                $table->decimal('packaging_per_unit', 10, 2)->default(0)->after('labor_per_unit')->comment('Coût emballage par unité produite');
            }
            if (! Schema::hasColumn('bills_of_materials', 'std_energy_cost')) {
                $table->decimal('std_energy_cost', 12, 2)->default(0)->after('std_machine_cost')->comment('Coût standard énergie par unité');
            }
            if (! Schema::hasColumn('bills_of_materials', 'std_maintenance_cost')) {
                $table->decimal('std_maintenance_cost', 12, 2)->default(0)->after('std_energy_cost')->comment('Coût standard maintenance par unité');
            }
            if (! Schema::hasColumn('bills_of_materials', 'std_packaging_cost')) {
                $table->decimal('std_packaging_cost', 12, 2)->default(0)->after('std_maintenance_cost')->comment('Coût standard emballage par unité');
            }
        });

        Schema::table('production_machines', function (Blueprint $table) {
            if (! Schema::hasColumn('production_machines', 'energy_cost_per_hour')) {
                $table->bigInteger('energy_cost_per_hour')->default(0)->after('hourly_cost')->comment('Coût énergie par heure de fonctionnement');
            }
            if (! Schema::hasColumn('production_machines', 'maintenance_cost_per_hour')) {
                $table->bigInteger('maintenance_cost_per_hour')->default(0)->after('energy_cost_per_hour')->comment('Taux horaire maintenance (amortissement entretien)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->dropColumn(['packaging_per_unit', 'std_energy_cost', 'std_maintenance_cost', 'std_packaging_cost']);
        });
        Schema::table('production_machines', function (Blueprint $table) {
            $table->dropColumn(['energy_cost_per_hour', 'maintenance_cost_per_hour']);
        });
        // energy/maintenance/packaging_cost de production_costs : conservés
        // (préexistants à cette migration).
    }
};
