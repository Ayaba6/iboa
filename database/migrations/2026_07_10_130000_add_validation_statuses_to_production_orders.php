<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [BUG MySQL — workflow §13.3] L'ENUM production_orders.status ne contenait pas
 * « attente_chef » ni « attente_responsable » : submitForValidation() /
 * validateByChef() échouaient en MySQL (« Data truncated ») alors que les tests
 * passaient (SQLite = TEXT). Élargit l'ENUM aux statuts du circuit de
 * validation 2 niveaux (brouillon → attente_chef → attente_responsable →
 * matiere_allouee → lance → …).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite (tests) : colonne TEXT — rien à faire
        }

        DB::statement("ALTER TABLE production_orders MODIFY status ENUM(
            'brouillon','attente_chef','attente_responsable','matiere_allouee',
            'lance','en_cours','termine_partiellement','termine','annule'
        ) NOT NULL DEFAULT 'brouillon'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE production_orders SET status = 'brouillon' WHERE status IN ('attente_chef','attente_responsable')");
        DB::statement("ALTER TABLE production_orders MODIFY status ENUM(
            'brouillon','matiere_allouee','lance','en_cours','termine_partiellement','termine','annule'
        ) NOT NULL DEFAULT 'brouillon'");
    }
};
