<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [SEC-PHASE2 §13] Unicité d'une révision ACTIVE par devis, garantie EN BASE :
 * colonne générée = revision_of_id quand le statut est actif (ni annulé ni
 * refusé), NULL sinon ; index UNIQUE dessus (NULL multiples autorisés).
 * Protège contre la concurrence, les scripts et les chemins hors service —
 * le lockForUpdate du service reste la première ligne.
 * SQLite (tests) : colonnes générées STORED non altérables — l'unicité y est
 * couverte par le service ; la contrainte vise MySQL (production).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // L'ALTER reconstruit la table et revalide les FK (erreur 1215 avec
        // l'algorithme COPY) — désactivation locale des checks le temps du DDL.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::statement("
                ALTER TABLE quotes
                ADD COLUMN active_revision_key BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        IF(revision_of_id IS NOT NULL AND status NOT IN ('annule', 'refuse'), revision_of_id, NULL)
                    ) VIRTUAL
            ");
            DB::statement('ALTER TABLE quotes ADD UNIQUE INDEX quotes_active_revision_unique (active_revision_key)');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE quotes DROP INDEX quotes_active_revision_unique');
        DB::statement('ALTER TABLE quotes DROP COLUMN active_revision_key');
    }
};
