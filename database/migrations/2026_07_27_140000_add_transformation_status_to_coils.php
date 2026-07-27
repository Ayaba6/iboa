<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Qualité #3] Séparer le cycle de vie QUALITÉ et le cycle de vie TRANSFORMATION.
 *
 * Une bobine divisée est dans un état de TRANSFORMATION, pas dans une disposition
 * qualité. Mettre « split » dans `quality_status` mélangeait deux axes :
 *
 *   coils.status                → état logistique (disponible|en_production|epuisee)
 *   coils.quality_status        → disposition qualité (recu|quarantaine|libere|refuse|…)
 *   coils.transformation_status → intacte | divisee | transformee
 *
 * Reprise des données : les bobines dont quality_status = 'split' passent à
 * transformation_status = 'divisee' et retrouvent un quality_status NULL
 * (la disposition qualité appartient désormais aux bobines filles).
 * Idempotente ; index court (limite MySQL 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            if (! Schema::hasColumn('coils', 'transformation_status')) {
                $table->string('transformation_status', 20)->nullable()->after('quality_status');
                $table->index('transformation_status', 'ix_coils_transfo');
            }
        });

        // Reprise : 'split' n'est plus une disposition qualité — il migre vers
        // l'axe transformation. Le statut qualité antérieur n'étant PAS prouvable
        // pour ces lignes (l'information avait été écrasée), il reste NULL =
        // INCONNU. On n'invente jamais « libéré ». Un rapport est journalisé.
        $rows = DB::table('coils')->where('quality_status', 'split')->get(['id', 'reference']);
        if ($rows->isNotEmpty()) {
            DB::table('coils')->where('quality_status', 'split')->update([
                'transformation_status' => 'divisee',
                'quality_status'        => null, // inconnu : aucune preuve du statut antérieur
            ]);
            logger()->warning('[Division] Bobines reclassées split → transformation_status=divisee ; statut qualité antérieur NON prouvable, laissé à NULL (inconnu).', [
                'nombre'  => $rows->count(),
                'bobines' => $rows->pluck('reference')->all(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('coils')->where('transformation_status', 'divisee')->update(['quality_status' => 'split']);

        Schema::table('coils', function (Blueprint $table) {
            if (Schema::hasColumn('coils', 'transformation_status')) {
                $table->dropIndex('ix_coils_transfo');
                $table->dropColumn('transformation_status');
            }
        });
    }
};
