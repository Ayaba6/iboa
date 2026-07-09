<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Contrôle qualité : Création] Champs complémentaires :
 * lot, atelier, ligne, poste de charge, unité quantité, échantillonnage,
 * norme, type d'inspection — et table quality_inspection_characteristics
 * (caractéristiques contrôlées : spécification min/max, méthode, résultat, conformité).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->string('lot_number', 60)->nullable();                          // LOT-2026-0706-01
            $table->string('atelier', 60)->nullable();                             // Atelier Tôle Bac
            $table->foreignId('production_line_id')->nullable()
                  ->constrained('production_lines')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()
                  ->constrained('work_centers')->nullOnDelete();                   // poste de charge
            $table->string('quantity_unit', 15)->nullable();                       // MTL
            $table->string('sampling_method', 30)->nullable();                     // par_attributs | par_variables | controle_100
            $table->string('norm_reference', 60)->nullable();                      // NF EN 10169:2022
            $table->string('inspection_stage', 20)->nullable()->default('finale'); // initiale | en_cours | finale
        });

        Schema::create('quality_inspection_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_inspection_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->default(1);
            $table->string('name', 100);                                           // Épaisseur
            $table->string('spec_min', 20)->nullable();                            // 0,48 / AZ100 / —
            $table->string('spec_max', 20)->nullable();
            $table->string('unit', 15)->nullable();                                // mm, kg/ml, g/m²
            $table->string('control_method', 60)->nullable();                      // Pied à coulisse
            $table->string('frequency', 30)->nullable()->default('chaque_lot');
            $table->string('result', 30)->nullable();                              // 0,50 / OK / AZ120
            $table->string('conformity', 20)->nullable();                          // conforme | non_conforme | derogation
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspection_characteristics');

        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('work_center_id');
            $table->dropColumn([
                'lot_number', 'atelier', 'quantity_unit', 'sampling_method',
                'norm_reference', 'inspection_stage',
            ]);
        });
    }
};
