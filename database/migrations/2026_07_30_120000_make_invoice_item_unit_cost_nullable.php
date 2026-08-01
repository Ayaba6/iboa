<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes] Aligner `invoice_items.unit_cost` sur les lignes de devis et de commande.
 *
 * État avant :
 *   quote_items.unit_cost    decimal(15,2)  NULL autorisé
 *   order_items.unit_cost    decimal(15,2)  NULL autorisé
 *   invoice_items.unit_cost  decimal(15,2)  NOT NULL, défaut 0.00
 *
 * Conséquence de cette divergence : sur une facture, un coût INCONNU devenait
 * 0,00 — indiscernable d'un coût réellement nul. Or les deux faits n'ont pas le
 * même sens : un coût à zéro affiche 100 % de marge et masque exactement le cas à
 * surveiller, un article dont le coût n'est pas renseigné. C'est la raison pour
 * laquelle `SalesLineDefaultsService::resolveUnitCost()` ne retient jamais un zéro
 * et rend `null`.
 *
 * Les valeurs EXISTANTES ne sont pas réécrites. Un 0,00 déjà enregistré peut être
 * un zéro légitime ; le transformer en NULL reviendrait à reconstruire de la
 * donnée historique sans preuve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_items', 'unit_cost')) {
            return;
        }

        // `change()` via Doctrine n'est pas disponible sur toutes les installations ;
        // l'instruction native est explicite et portable sur les deux moteurs du
        // projet — SQLite recrée la table, MySQL modifie la colonne en place.
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite ne sait pas relâcher une contrainte NOT NULL par ALTER. Les
            // migrations de test partant d'une base vide, la colonne y est déjà créée
            // nullable par la migration d'origine si celle-ci l'est ; sinon on
            // reconstruit la table via le schéma Laravel.
            Schema::table('invoice_items', function ($table) {
                $table->decimal('unit_cost', 15, 2)->nullable()->default(null)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE invoice_items MODIFY unit_cost DECIMAL(15,2) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoice_items', 'unit_cost')) {
            return;
        }

        // Retour à l'état antérieur : les NULL redeviennent 0,00, faute de quoi la
        // contrainte NOT NULL ne peut pas être reposée.
        DB::table('invoice_items')->whereNull('unit_cost')->update(['unit_cost' => 0]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('invoice_items', function ($table) {
                $table->decimal('unit_cost', 15, 2)->default(0)->nullable(false)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE invoice_items MODIFY unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00');
    }
};
