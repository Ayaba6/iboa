<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §3 — étend les statuts d'OF : ajoute « matière allouée » (après allocation,
 * avant lancement) et « terminé partiellement » (production partielle).
 *
 * MySQL : MODIFY de l'enum. SQLite (tests) : colonne TEXT, aucun changement
 * nécessaire (toute valeur est acceptée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_orders MODIFY COLUMN status ENUM(
                'brouillon','matiere_allouee','lance','en_cours','termine_partiellement','termine','annule'
            ) NOT NULL DEFAULT 'brouillon'");

            return;
        }

        // SQLite (tests) : l'enum d'origine est une CHECK constraint. On bascule
        // la colonne en string pour lever la contrainte sur les nouveaux statuts.
        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('status', 30)->default('brouillon')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Rétablit les statuts d'origine (les nouveaux retombent sur des proches).
        DB::table('production_orders')->where('status', 'matiere_allouee')->update(['status' => 'brouillon']);
        DB::table('production_orders')->where('status', 'termine_partiellement')->update(['status' => 'en_cours']);
        DB::statement("ALTER TABLE production_orders MODIFY COLUMN status ENUM(
            'brouillon','lance','en_cours','termine','annule'
        ) NOT NULL DEFAULT 'brouillon'");
    }
};
