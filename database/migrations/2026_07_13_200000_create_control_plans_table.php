<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [QUA-01] Plans de contrôle qualité : caractéristiques, méthodes, fréquences,
 * échantillonnage, tolérances et responsables — par article/famille et étape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_family_id')->nullable()->constrained('product_families')->nullOnDelete();
            $table->string('stage')->default('production'); // reception, production, final
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('control_plan_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_plan_id')->constrained('control_plans')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');                              // caractéristique contrôlée
            $table->string('method')->nullable();                // méthode / instrument
            $table->string('unit')->nullable();                  // unité de mesure
            $table->string('frequency')->nullable();             // fréquence (chaque lot, horaire…)
            $table->string('sampling')->nullable();              // plan d'échantillonnage
            $table->decimal('target_value', 15, 4)->nullable();  // valeur cible
            $table->decimal('tolerance_min', 15, 4)->nullable();
            $table->decimal('tolerance_max', 15, 4)->nullable();
            $table->boolean('is_critical')->default(false);
            $table->string('responsible')->nullable();           // responsable / poste
            $table->timestamps();
            $table->index('control_plan_id');
        });

        // Lien optionnel inspection ↔ plan de contrôle (non bloquant).
        Schema::table('quality_inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('quality_inspections', 'control_plan_id')) {
                $table->foreignId('control_plan_id')->nullable()->after('company_id')
                    ->constrained('control_plans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quality_inspections', function (Blueprint $table) {
            if (Schema::hasColumn('quality_inspections', 'control_plan_id')) {
                $table->dropConstrainedForeignId('control_plan_id');
            }
        });
        Schema::dropIfExists('control_plan_characteristics');
        Schema::dropIfExists('control_plans');
    }
};
