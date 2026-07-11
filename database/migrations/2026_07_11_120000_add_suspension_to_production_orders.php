<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Refonte Prod X3 §5] Statut OF « suspendu » : mémorise le statut d'origine
 * pour la reprise + horodatage. Additif — aucun impact sur l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Extension de l'ENUM status : ajout de 'suspendu' (additif, valeurs existantes intactes).
        // SQLite (suite de tests) : l'enum Laravel est un varchar+CHECK — on le remplace par un
        // varchar simple via change(), qui reconstruit la table sans la contrainte.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_orders MODIFY status ENUM('brouillon','attente_chef','attente_responsable','matiere_allouee','lance','en_cours','termine_partiellement','termine','annule','suspendu') NOT NULL DEFAULT 'brouillon'");
        } else {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->string('status', 30)->default('brouillon')->change();
            });
        }

        Schema::table('production_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('production_orders', 'suspended_from')) {
                $table->string('suspended_from', 30)->nullable()->after('status');
            }
            if (! Schema::hasColumn('production_orders', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('suspended_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            foreach (['suspended_from', 'suspended_at'] as $col) {
                if (Schema::hasColumn('production_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::statement("UPDATE production_orders SET status = 'lance' WHERE status = 'suspendu'");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_orders MODIFY status ENUM('brouillon','attente_chef','attente_responsable','matiere_allouee','lance','en_cours','termine_partiellement','termine','annule') NOT NULL DEFAULT 'brouillon'");
        }
    }
};
