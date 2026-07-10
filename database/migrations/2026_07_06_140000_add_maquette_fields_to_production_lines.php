<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Ligne de production : Création] Champs complémentaires :
 * - Général : type de ligne, produit principal, dépôt de production, atelier, localisation,
 *   groupe de lignes, site, capacité nominale, calendrier, mise en service, responsable, statut, notes.
 * - Caractéristiques : capacité/performances (théorique, pratique, TRS cible, temps de cycle),
 *   organisation (équipes, opérateurs, continu), plages de production (horaires + pauses),
 *   contrôle et suivi, identification.
 * - Options : démarrage OF, interligne, rebuts, imputation coûts, blocage indispo, suivi arrêts, priorité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_lines', function (Blueprint $table) {
            // Général
            $table->string('type_ligne', 30)->nullable()->default('production')->after('code');
            $table->foreignId('product_id')->nullable()->after('machine_id')
                  ->constrained('products')->nullOnDelete();                       // produit principal
            $table->foreignId('depot_production_id')->nullable()->after('product_id')
                  ->constrained('warehouses')->nullOnDelete();
            $table->string('atelier', 60)->nullable();                             // Atelier Tôle Bac
            $table->string('location', 100)->nullable();                           // Hall Production - Ligne 1
            $table->string('line_group', 60)->nullable();                          // Lignes Tôle Bac
            $table->string('site', 20)->nullable();                                // SITE01
            $table->decimal('nominal_capacity', 12, 2)->nullable();
            $table->string('capacity_unit', 15)->nullable();                       // ML/heure
            $table->string('work_calendar', 30)->nullable();                       // CAL-STD-08H
            $table->date('commissioned_at')->nullable();                           // date de mise en service
            $table->foreignId('responsible_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('status', 20)->nullable()->default('active');           // active | maintenance | arret
            $table->text('notes')->nullable();

            // Capacité et performances
            $table->decimal('theoretical_capacity', 12, 2)->nullable();
            $table->string('theoretical_capacity_unit', 15)->nullable();
            $table->decimal('practical_capacity', 12, 2)->nullable();
            $table->string('practical_capacity_unit', 15)->nullable();
            $table->decimal('trs_target', 5, 2)->nullable();                       // TRS cible %
            $table->decimal('cycle_time', 10, 3)->nullable();                      // temps de cycle moyen
            $table->string('cycle_time_unit', 15)->nullable();                     // min/ML

            // Organisation
            $table->unsignedSmallInteger('teams_count')->nullable();               // nombre d'équipes
            $table->string('default_team', 30)->nullable();                        // Équipe A
            $table->unsignedSmallInteger('operators_per_team')->nullable();
            $table->boolean('continuous_work')->default(false);                    // travail en continu

            // Plages de production
            $table->time('start_time')->nullable();                                // 06:00
            $table->time('end_time')->nullable();                                  // 18:00
            $table->time('break1_start')->nullable();
            $table->time('break1_end')->nullable();
            $table->time('break2_start')->nullable();
            $table->time('break2_end')->nullable();

            // Contrôle et suivi
            $table->string('quality_control_point', 60)->nullable();               // PC-LIG-TB-01
            $table->string('control_frequency', 30)->nullable();                   // chaque_heure
            $table->boolean('require_production_entry')->default(true);            // saisie production obligatoire

            // Identification
            $table->string('barcode', 60)->nullable();
            $table->string('serial_number', 60)->nullable();

            // Options et paramètres
            $table->boolean('allow_of_start')->default(true);                      // autoriser démarrage OF
            $table->boolean('allow_interline')->default(true);                     // autoriser interligne
            $table->boolean('scrap_management')->default(true);                    // gestion des rebuts
            $table->boolean('auto_cost_allocation')->default(true);                // imputation automatique des coûts
            $table->boolean('block_if_unavailable')->default(false);               // blocage si indisponibilité
            $table->boolean('track_stoppages')->default(true);                     // suivi des arrêts
            $table->string('priorite', 15)->nullable()->default('normale');
        });
    }

    public function down(): void
    {
        Schema::table('production_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('depot_production_id');
            $table->dropConstrainedForeignId('responsible_id');
            $table->dropColumn([
                'type_ligne', 'atelier', 'location', 'line_group', 'site',
                'nominal_capacity', 'capacity_unit', 'work_calendar', 'commissioned_at', 'status', 'notes',
                'theoretical_capacity', 'theoretical_capacity_unit',
                'practical_capacity', 'practical_capacity_unit', 'trs_target', 'cycle_time', 'cycle_time_unit',
                'teams_count', 'default_team', 'operators_per_team', 'continuous_work',
                'start_time', 'end_time', 'break1_start', 'break1_end', 'break2_start', 'break2_end',
                'quality_control_point', 'control_frequency', 'require_production_entry',
                'barcode', 'serial_number',
                'allow_of_start', 'allow_interline', 'scrap_management', 'auto_cost_allocation',
                'block_if_unavailable', 'track_stoppages', 'priorite',
            ]);
        });
    }
};
