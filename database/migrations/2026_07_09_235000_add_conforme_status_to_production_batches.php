<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Audit OF — lot PF] Ajoute le statut « conforme » aux lots de fabrication :
 * après contrôle qualité conforme, le lot PF est libéré (traçabilité qualité)
 * avant sa clôture définitive. en_cours → conforme → cloture.
 *
 * MySQL : élargit l'ENUM. SQLite (tests) : l'enum est un TEXT + CHECK —
 * on recrée la colonne en string libre (mêmes valeurs, contrainte applicative).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_batches MODIFY status ENUM('en_cours','conforme','cloture') NOT NULL DEFAULT 'en_cours'");

            return;
        }

        Schema::table('production_batches', function (Blueprint $t) {
            $t->string('status', 20)->default('en_cours')->change();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE production_batches SET status = 'en_cours' WHERE status = 'conforme'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_batches MODIFY status ENUM('en_cours','cloture') NOT NULL DEFAULT 'en_cours'");
        }
    }
};
