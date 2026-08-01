<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes §17] Donner un chemin d'annulation TRACÉ aux bons de préparation.
 *
 * Le module n'exposait que `index`, `show`, `start-loading`, `finish-loading` et
 * `pdf` : ni annulation, ni suppression. Un bon créé par erreur restait à
 * l'écran indéfiniment, et un magasinier n'avait aucun moyen d'écarter un bon
 * devenu caduc. La seule issue était une écriture directe en base — hors de
 * toute règle métier et sans trace.
 *
 * Le modèle ne portait pas non plus `SoftDeletes` : toute suppression y était
 * définitive, sans rattrapage possible hors sauvegarde SQL.
 *
 * Cette migration ajoute les colonnes ; les gardes métier vivent dans
 * BonPreparationService::cancel().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_preparations', function (Blueprint $table) {
            if (! Schema::hasColumn('bon_preparations', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('bon_preparations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('loaded_at');
            }
            if (! Schema::hasColumn('bon_preparations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bon_preparations', 'cancellation_reason')) {
                // Le motif est stocké dans sa propre colonne, pas noyé dans `notes` :
                // il doit rester lisible, requêtable et non écrasable par une note.
                $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bon_preparations', function (Blueprint $table) {
            if (Schema::hasColumn('bon_preparations', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancellation_reason', 'cancelled_at', 'deleted_at'] as $column) {
                if (Schema::hasColumn('bon_preparations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
