<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Machine : Création] Champs complémentaires :
 * - Général : catégorie, ligne, localisation, origine, marque, capacité nominale,
 *   unité principale, responsable, alimentation, coût d'acquisition, poids.
 * - Caractéristiques techniques : dimensions, performances, équipements,
 *   raccordements, conditions d'utilisation.
 * - Disponibilités et affectation : atelier/ligne, calendrier, poste principal,
 *   équipe par défaut, priorité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            // Général
            $table->string('category', 40)->nullable()->after('type');            // Profileuse, Cisaille…
            $table->foreignId('production_line_id')->nullable()->after('category')
                  ->constrained('production_lines')->nullOnDelete();
            $table->string('location', 100)->nullable()->after('atelier');        // Hall Production - Ligne 1
            $table->string('country_origin', 40)->nullable()->after('manufacturer');
            $table->string('brand', 60)->nullable()->after('country_origin');     // BMS
            $table->decimal('nominal_capacity', 10, 2)->nullable()->after('power_kw');
            $table->string('capacity_unit', 15)->nullable()->after('nominal_capacity'); // ML/min
            $table->foreignId('unit_id')->nullable()->after('capacity_unit')
                  ->constrained('units')->nullOnDelete();                          // unité principale
            $table->foreignId('responsible_id')->nullable()->after('unit_id')
                  ->constrained('users')->nullOnDelete();
            $table->string('power_supply', 60)->nullable()->after('responsible_id'); // 380 V - Triphasé - 50 Hz
            $table->unsignedBigInteger('acquisition_cost')->nullable()->after('power_supply'); // FCFA
            $table->decimal('weight_kg', 10, 2)->nullable()->after('acquisition_cost');

            // Dimensions
            $table->decimal('length_mm', 10, 2)->nullable();
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->decimal('height_mm', 10, 2)->nullable();
            $table->decimal('footprint_m3', 8, 2)->nullable();                    // encombrement

            // Performances
            $table->decimal('max_speed', 8, 2)->nullable();
            $table->decimal('nominal_speed', 8, 2)->nullable();
            $table->decimal('useful_width_mm', 8, 2)->nullable();
            $table->decimal('thickness_min', 6, 3)->nullable();
            $table->decimal('thickness_max', 6, 3)->nullable();
            $table->unsignedSmallInteger('waves_count')->nullable();              // nombre d'ondes
            $table->decimal('shaft_diameter_mm', 8, 2)->nullable();               // diamètre arbres

            // Équipements
            $table->string('motor_type', 30)->nullable();                         // Électrique
            $table->string('reducer', 60)->nullable();                            // SEW - R87
            $table->string('cutting_system', 30)->nullable();                     // Hydraulique
            $table->boolean('integrated_decoiler')->default(false);               // dérouleur intégré

            // Raccordements
            $table->decimal('power_kva', 8, 2)->nullable();
            $table->decimal('air_pressure_bar', 6, 2)->nullable();
            $table->decimal('hydraulic_pressure_bar', 8, 2)->nullable();

            // Conditions d'utilisation
            $table->decimal('temp_min', 5, 1)->nullable();
            $table->decimal('temp_max', 5, 1)->nullable();
            $table->decimal('humidity_max', 5, 1)->nullable();
            $table->string('environment', 20)->nullable()->default('interieur');

            // Disponibilités et affectation
            $table->boolean('assigned_to_atelier')->default(true);
            $table->boolean('assigned_to_line')->default(false);
            $table->string('work_calendar', 30)->nullable();                      // CAL-STD-08H
            $table->foreignId('work_center_id')->nullable()
                  ->constrained('work_centers')->nullOnDelete();                  // poste de travail principal
            $table->string('default_team', 30)->nullable();                       // Équipe A
            $table->string('priorite', 15)->nullable()->default('normale');
        });
    }

    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('responsible_id');
            $table->dropConstrainedForeignId('work_center_id');
            $table->dropColumn([
                'category', 'location', 'country_origin', 'brand',
                'nominal_capacity', 'capacity_unit', 'power_supply', 'acquisition_cost', 'weight_kg',
                'length_mm', 'width_mm', 'height_mm', 'footprint_m3',
                'max_speed', 'nominal_speed', 'useful_width_mm', 'thickness_min', 'thickness_max',
                'waves_count', 'shaft_diameter_mm',
                'motor_type', 'reducer', 'cutting_system', 'integrated_decoiler',
                'power_kva', 'air_pressure_bar', 'hydraulic_pressure_bar',
                'temp_min', 'temp_max', 'humidity_max', 'environment',
                'assigned_to_atelier', 'assigned_to_line', 'work_calendar', 'default_team', 'priorite',
            ]);
        });
    }
};
