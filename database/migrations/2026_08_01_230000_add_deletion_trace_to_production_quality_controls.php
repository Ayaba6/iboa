<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-A3-QUALITY-DELETE-006] Trace de suppression d'un contrôle qualité.
 *
 * `production_quality_controls` n'avait ni `deleted_at`, ni motif, ni auteur de
 * suppression : `ProductionQualityController::destroy()` effaçait la ligne
 * définitivement. Or toutes les gardes qualité — libération, clôture d'OF,
 * garde de livraison — lisent le DERNIER contrôle enregistré. Supprimer un
 * `non_conforme` fait redevenir « dernier » un `conforme` antérieur : la
 * marchandise repart, et l'historique affirme qu'aucun défaut n'a été constaté.
 *
 * Trois colonnes, additives et nullables : aucune ligne existante n'est
 * modifiée, et les quatre contrôles déjà en base restent actifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_quality_controls', function (Blueprint $table) {
            if (! Schema::hasColumn('production_quality_controls', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('production_quality_controls', 'deletion_reason')) {
                $table->string('deletion_reason', 255)->nullable()->after('deleted_at');
            }
            if (! Schema::hasColumn('production_quality_controls', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deletion_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_quality_controls', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deletion_reason', 'deleted_by']);
        });
    }
};
