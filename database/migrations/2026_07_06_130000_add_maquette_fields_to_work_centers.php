<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Poste de charge : Création] Champs complémentaires :
 * - Général : catégorie, localisation, ligne, groupe, responsable, dépôt de production,
 *   priorité, calendrier de travail, poste similaire.
 * - Caractéristiques : capacité (nominale/théorique/taux/TRS), temps (cycle/installation/
 *   réglage/transfert), organisation (opérateurs/équipe/mode/parallèle), contrôle
 *   (point qualité/fréquence/documentation/criticité), identification (code-barres/référence).
 * - Options : capacité chargée, surcharges, rebuts, saisie temps, imputation coûts, obligatoire OF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            // Général
            $table->string('category', 40)->nullable()->after('type');            // Découpage
            $table->string('location', 100)->nullable()->after('atelier');        // Hall Production - Zone Découpage
            $table->foreignId('production_line_id')->nullable()->after('location')
                  ->constrained('production_lines')->nullOnDelete();
            $table->string('poste_group', 60)->nullable()->after('production_line_id'); // groupe de postes
            $table->foreignId('responsible_id')->nullable()->after('poste_group')
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('depot_production_id')->nullable()->after('responsible_id')
                  ->constrained('warehouses')->nullOnDelete();
            $table->string('priorite', 15)->nullable()->default('normale');
            $table->string('work_calendar', 30)->nullable();                      // CAL-STD-08H
            $table->foreignId('similar_work_center_id')->nullable()
                  ->constrained('work_centers')->nullOnDelete();                  // poste similaire

            // Capacité
            $table->decimal('nominal_capacity', 12, 2)->nullable();
            $table->string('capacity_unit', 15)->nullable();                      // ML/heure
            $table->decimal('theoretical_capacity', 12, 2)->nullable();
            $table->string('theoretical_capacity_unit', 15)->nullable();          // ML/8h
            $table->decimal('utilization_rate', 5, 2)->nullable();                // taux d'utilisation standard %
            $table->decimal('trs_standard', 5, 2)->nullable();                    // TRS standard %

            // Temps
            $table->decimal('cycle_time', 10, 3)->nullable();                     // temps de cycle standard
            $table->string('cycle_time_unit', 15)->nullable();                    // min/ML
            $table->decimal('setup_time_min', 8, 2)->nullable();                  // temps d'installation
            $table->decimal('adjustment_time_min', 8, 2)->nullable();             // temps de réglage
            $table->decimal('transfer_time_min', 8, 2)->nullable();               // temps de transfert

            // Organisation
            $table->unsignedSmallInteger('operators_count')->nullable();          // nombre d'opérateurs
            $table->string('default_team', 30)->nullable();                       // Équipe A
            $table->string('operating_mode', 20)->nullable()->default('continu'); // continu | discontinu
            $table->boolean('parallel_work')->default(false);                     // travail en parallèle

            // Contrôle
            $table->string('quality_control_point', 60)->nullable();              // PC02 - Contrôle dimensionnel
            $table->string('control_frequency', 30)->nullable();                  // chaque_lot | echantillon | horaire
            $table->string('documentation_ref', 60)->nullable();                  // PROC-DEC-001
            $table->string('criticality', 15)->nullable()->default('moyenne');    // faible | moyenne | haute

            // Identification
            $table->string('barcode', 60)->nullable();                            // code à barres / QR
            $table->string('serial_number', 60)->nullable();                      // n° de série / référence

            // Options et paramètres
            $table->boolean('include_in_capacity')->default(true);                // prise en compte capacité chargée
            $table->boolean('allow_overload')->default(true);                     // autoriser surcharges
            $table->boolean('scrap_management')->default(true);                   // gestion des rebuts
            $table->boolean('require_time_entry')->default(false);                // saisie obligatoire des temps
            $table->boolean('auto_cost_allocation')->default(true);               // imputation automatique des coûts
            $table->boolean('required_on_of')->default(true);                     // obligatoire sur OF
        });
    }

    public function down(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('responsible_id');
            $table->dropConstrainedForeignId('depot_production_id');
            $table->dropConstrainedForeignId('similar_work_center_id');
            $table->dropColumn([
                'category', 'location', 'poste_group', 'priorite', 'work_calendar',
                'nominal_capacity', 'capacity_unit', 'theoretical_capacity', 'theoretical_capacity_unit',
                'utilization_rate', 'trs_standard',
                'cycle_time', 'cycle_time_unit', 'setup_time_min', 'adjustment_time_min', 'transfer_time_min',
                'operators_count', 'default_team', 'operating_mode', 'parallel_work',
                'quality_control_point', 'control_frequency', 'documentation_ref', 'criticality',
                'barcode', 'serial_number',
                'include_in_capacity', 'allow_overload', 'scrap_management',
                'require_time_entry', 'auto_cost_allocation', 'required_on_of',
            ]);
        });
    }
};
