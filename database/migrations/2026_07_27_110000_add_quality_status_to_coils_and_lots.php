<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS Qualité #11] La quarantaine est une DISPOSITION, pas seulement un dépôt.
 *
 * Le statut qualité doit exister sur le lot et la bobine, indépendamment de leur
 * emplacement : un article peut être physiquement en DEP-QUAR avec un statut
 * incohérent — l'audit doit pouvoir le détecter.
 *
 * `coils.status` (disponible|en_production|epuisee) reste le statut LOGISTIQUE :
 * sa sémantique n'est pas modifiée. On ajoute une colonne DISTINCTE `quality_status` :
 *   recu | quarantaine | libere | libere_partiel | refuse | retour_attente |
 *   retourne | annule
 * NULL = statut qualité INCONNU (bobines/lots historiques) — jamais interprété
 * comme « libéré » ; signalé par l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->string('quality_status', 20)->nullable()->after('status');
            $table->foreignId('quality_decision_id')->nullable()->after('quality_status');
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->string('quality_status', 20)->nullable()->after('status');
        });
        // Historique laissé à NULL (inconnu) — aucune libération inventée.
    }

    public function down(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->dropColumn(['quality_status', 'quality_decision_id']);
        });
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropColumn('quality_status');
        });
    }
};
