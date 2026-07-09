<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs par défaut de la catégorie (fiche « Catégories : Création complète »
 * de SAGE X3) : préfixe de code, lot obligatoire, contrôle qualité
 * entrée/sortie, et unités par défaut (UP, coefficients, densité, poids,
 * épaisseur, métrage) dont l'article hérite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            // Règles métier
            $table->string('code_prefix', 10)->nullable()->after('libelle_court')->comment('Préfixe code article');
            $table->boolean('lot_obligatoire')->default(false)->after('gestion_lot');
            $table->boolean('cq_entree')->default(false)->after('controle_qualite')->comment('Contrôle qualité à réception');
            $table->boolean('cq_sortie')->default(false)->after('cq_entree')->comment('Contrôle qualité à expédition');

            // Unités par défaut héritées par l'article
            $table->foreignId('unite_poids_id')->nullable()->after('unite_vente_id')->constrained('units')->nullOnDelete();
            $table->decimal('coef_ua_us', 14, 6)->nullable()->after('unite_poids_id');
            $table->decimal('coef_uv_us', 14, 6)->nullable()->after('coef_ua_us');
            $table->decimal('densite', 8, 3)->nullable()->after('coef_uv_us');
            $table->decimal('poids_brut', 12, 4)->nullable()->after('densite');
            $table->decimal('poids_net', 12, 4)->nullable()->after('poids_brut');
            $table->decimal('epaisseur', 8, 2)->nullable()->after('poids_net');
            $table->decimal('metrage', 10, 2)->nullable()->after('epaisseur');
        });
    }

    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unite_poids_id');
            $table->dropColumn([
                'code_prefix', 'lot_obligatoire', 'cq_entree', 'cq_sortie',
                'coef_ua_us', 'coef_uv_us', 'densite', 'poids_brut', 'poids_net', 'epaisseur', 'metrage',
            ]);
        });
    }
};
