<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité avec la fiche SAGE X3 « Ordres de fabrication : Création complète » :
 * en-tête (sites, numéro optimisation, préparation, référence OF, désignation,
 * mode de lancement, priorité, dates) et paramètres de production
 * (rendement, taux de perte, dépôts produit fini / rebut, contrôle qualité).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            // En-tête
            $table->string('site_planification', 20)->nullable()->after('company_id');
            $table->string('site_production', 20)->nullable()->after('site_planification');
            $table->string('numero_optimisation', 30)->nullable()->after('number');
            $table->string('prepa_fabrication', 60)->nullable()->after('numero_optimisation');
            $table->string('reference_of', 60)->nullable()->after('prepa_fabrication');
            $table->string('designation', 200)->nullable()->after('reference_of');
            $table->string('mode_lancement', 30)->nullable()->after('designation');
            $table->string('priorite', 20)->default('normale')->after('mode_lancement');
            $table->date('date_fabrication_prevue')->nullable()->after('launched_at');
            $table->date('date_lancement')->nullable()->after('date_fabrication_prevue');
            $table->time('heure_lancement')->nullable()->after('date_lancement');
            $table->string('observation', 500)->nullable()->after('heure_lancement');

            // Paramètres de production
            $table->decimal('rendement_standard', 6, 4)->nullable()->after('usable_width');
            $table->decimal('taux_perte', 6, 4)->nullable()->after('rendement_standard');
            $table->foreignId('depot_produit_fini_id')->nullable()->after('taux_perte')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('depot_rebut_id')->nullable()->after('depot_produit_fini_id')->constrained('warehouses')->nullOnDelete();
            $table->boolean('controle_qualite_obligatoire')->default(true)->after('depot_rebut_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_produit_fini_id');
            $table->dropConstrainedForeignId('depot_rebut_id');
            $table->dropColumn([
                'site_planification', 'site_production', 'numero_optimisation', 'prepa_fabrication',
                'reference_of', 'designation', 'mode_lancement', 'priorite',
                'date_fabrication_prevue', 'date_lancement', 'heure_lancement', 'observation',
                'rendement_standard', 'taux_perte', 'controle_qualite_obligatoire',
            ]);
        });
    }
};
