<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Nouvelle intervention] Champs complémentaires machine_maintenances :
 * code, site, demandeur, priorité, ligne de production, heures prévues, atelier,
 * dépôt pièces, source demande, niveau d'urgence, n° OT, paramètres et sécurité,
 * résumé technique — et table maintenance_operations (opérations planifiées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_maintenances', function (Blueprint $table) {
            // Général
            $table->string('code', 30)->nullable()->after('company_id');          // INT-2026-00018
            $table->string('site', 20)->nullable();                                // SITE01
            $table->foreignId('requester_id')->nullable()
                  ->constrained('users')->nullOnDelete();                          // demandeur
            $table->string('priorite', 15)->nullable()->default('normale');        // haute | normale | basse
            $table->foreignId('production_line_id')->nullable()
                  ->constrained('production_lines')->nullOnDelete();
            $table->time('planned_start_time')->nullable();                        // 08:00
            $table->time('planned_end_time')->nullable();                          // 11:30
            $table->string('atelier', 60)->nullable();                             // Atelier Tôle Bac
            $table->foreignId('depot_pieces_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();                     // DEP-MAINT
            $table->string('request_source', 30)->nullable();                      // panne_machine | plan_preventif | demande
            $table->string('urgency_level', 30)->nullable();                       // arret_production | degrade | normal
            $table->string('ot_reference', 30)->nullable();                        // OT-2026-00112

            // Paramètres et sécurité
            $table->boolean('machine_stop_required')->default(true);               // arrêt machine obligatoire
            $table->boolean('electrical_lockout')->default(false);                 // consignation électrique
            $table->boolean('allow_subcontracting')->default(false);               // autoriser sous-traitance
            $table->boolean('maintenance_validation_required')->default(true);     // validation maintenance requise
            $table->boolean('quality_check_after')->default(false);                // contrôle qualité après intervention

            // Résumé technique
            $table->string('symptom', 150)->nullable();                            // Arrêt intempestif profileuse
            $table->string('probable_cause', 150)->nullable();                     // Défaillance capteur de position
            $table->string('critical_part', 150)->nullable();                      // Capteur fin de course
            $table->string('production_impact', 150)->nullable();                  // Ligne arrêtée
        });

        Schema::create('maintenance_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_maintenance_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->default(1);                    // N° 1..5
            $table->string('code', 20)->nullable();                                // OP-001
            $table->string('name', 150);                                           // Diagnostic panne
            $table->foreignId('technician_id')->nullable()
                  ->constrained('employees')->nullOnDelete();
            $table->decimal('planned_duration_min', 8, 2)->nullable();             // durée prévue (min)
            $table->time('start_time')->nullable();                                // 08:00
            $table->time('end_time')->nullable();                                  // 08:30
            $table->string('status', 20)->default('planifiee');                    // planifiee | en_cours | terminee
            $table->boolean('is_critical')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_operations');

        Schema::table('machine_maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requester_id');
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('depot_pieces_id');
            $table->dropColumn([
                'code', 'site', 'priorite', 'planned_start_time', 'planned_end_time',
                'atelier', 'request_source', 'urgency_level', 'ot_reference',
                'machine_stop_required', 'electrical_lockout', 'allow_subcontracting',
                'maintenance_validation_required', 'quality_check_after',
                'symptom', 'probable_cause', 'critical_part', 'production_impact',
            ]);
        });
    }
};
