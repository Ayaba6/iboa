<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Nouvelle nomenclature » : en-tête article composé
 * (site, alternative, versions, unité de gestion, quantité de base, statut,
 * validité, rendement, contrôle qualité) et lignes composant enrichies
 * (séquence, groupe, type, coefficient, dépôt de sortie, lot obligatoire, statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->string('site', 20)->nullable()->after('product_id');
            $table->string('alternative', 5)->nullable()->after('site');
            $table->date('date_reference')->nullable()->after('alternative');
            $table->string('version_majeure', 5)->nullable()->after('date_reference');
            $table->string('version_mineure', 5)->nullable()->after('version_majeure');
            $table->foreignId('unite_gestion_id')->nullable()->after('version_mineure')->constrained('units')->nullOnDelete();
            $table->decimal('quantite_base', 12, 3)->default(1)->after('unite_gestion_id');
            $table->string('statut', 20)->default('exploitation')->after('quantite_base');
            $table->date('date_debut_validite')->nullable()->after('statut');
            $table->date('date_fin_validite')->nullable()->after('date_debut_validite');
            $table->decimal('rendement_standard', 6, 2)->nullable()->after('date_fin_validite');
            $table->boolean('controle_qualite')->default(false)->after('rendement_standard');
        });

        Schema::table('bom_lines', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable()->after('bill_of_material_id');
            $table->string('groupe', 20)->nullable()->after('sequence');
            $table->string('type_composant', 20)->nullable()->after('groupe');
            $table->decimal('coef', 14, 6)->default(1)->after('waste_rate');
            $table->foreignId('depot_sortie_id')->nullable()->after('coef')->constrained('warehouses')->nullOnDelete();
            $table->boolean('lot_obligatoire')->default(false)->after('depot_sortie_id');
            $table->string('statut', 20)->default('actif')->after('lot_obligatoire');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unite_gestion_id');
            $table->dropColumn([
                'site', 'alternative', 'date_reference', 'version_majeure', 'version_mineure',
                'quantite_base', 'statut', 'date_debut_validite', 'date_fin_validite',
                'rendement_standard', 'controle_qualite',
            ]);
        });
        Schema::table('bom_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_sortie_id');
            $table->dropColumn(['sequence', 'groupe', 'type_composant', 'coef', 'lot_obligatoire', 'statut']);
        });
    }
};
