<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Nouvelle gamme opératoire » : en-tête (article parent,
 * site, versions, validité, unité de temps, quantité de base, rendement,
 * contrôle qualité) et opérations enrichies (numéro, temps main d'œuvre,
 * unité d'œuvre, rendement, contrôle qualité, sous-traitance, statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('bill_of_material_id')->constrained('products')->nullOnDelete();
            $table->string('site', 20)->nullable()->after('name');
            $table->string('alternative', 5)->nullable()->after('site');
            $table->string('version_majeure', 5)->nullable()->after('alternative');
            $table->string('version_mineure', 5)->nullable()->after('version_majeure');
            $table->date('date_reference')->nullable()->after('version_mineure');
            $table->string('statut', 20)->default('elaboration')->after('date_reference');
            $table->date('date_debut_validite')->nullable()->after('statut');
            $table->date('date_fin_validite')->nullable()->after('date_debut_validite');
            $table->string('unite_temps', 20)->default('minute')->after('date_fin_validite');
            $table->decimal('quantite_base', 12, 3)->default(1)->after('unite_temps');
            $table->string('uo', 10)->nullable()->after('quantite_base');
            $table->decimal('rendement_standard', 6, 2)->nullable()->after('uo');
            $table->boolean('controle_qualite')->default(false)->after('rendement_standard');
        });

        Schema::table('routing_operations', function (Blueprint $table) {
            $table->unsignedInteger('operation_number')->nullable()->after('sequence');
            $table->decimal('labor_minutes', 8, 2)->nullable()->after('run_minutes_per_unit');
            $table->decimal('quantite_base', 12, 3)->default(1)->after('labor_minutes');
            $table->string('uo', 10)->nullable()->after('quantite_base');
            $table->decimal('rendement', 6, 2)->nullable()->after('uo');
            $table->boolean('controle_qualite')->default(false)->after('rendement');
            $table->boolean('sous_traitance')->default(false)->after('controle_qualite');
            $table->string('statut', 20)->default('elaboration')->after('sous_traitance');
        });
    }

    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'site', 'alternative', 'version_majeure', 'version_mineure', 'date_reference',
                'statut', 'date_debut_validite', 'date_fin_validite', 'unite_temps',
                'quantite_base', 'uo', 'rendement_standard', 'controle_qualite',
            ]);
        });
        Schema::table('routing_operations', function (Blueprint $table) {
            $table->dropColumn([
                'operation_number', 'labor_minutes', 'quantite_base', 'uo',
                'rendement', 'controle_qualite', 'sous_traitance', 'statut',
            ]);
        });
    }
};
