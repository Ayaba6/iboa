<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes — TVA] Élargit `price_mode` pour accueillir la valeur « exonere ».
 *
 * La colonne a été créée en `varchar(5)` le 06/07/2026, quand seules deux
 * valeurs existaient : « ttc » et « ht ». L'ajout du mode « exonere » — sept
 * caractères — la fait déborder.
 *
 * Le défaut ne s'est vu que sur MySQL. SQLite n'applique pas la longueur d'un
 * VARCHAR : la suite de tests passait au vert sur un moteur et échouait sur
 * l'autre avec « SQLSTATE[22001] Data too long for column 'price_mode' ». En
 * production, enregistrer un devis exonéré aurait échoué net — pas une
 * troncature silencieuse, un refus d'écriture.
 *
 * Les CINQ tables sont élargies, pas seulement les trois qui écrivent
 * aujourd'hui « exonere » (devis, commandes, factures). Une même colonne
 * métier portant deux définitions selon la table est exactement le genre
 * d'asymétrie qui reproduit ce défaut le jour où l'exonération est ouverte au
 * cycle achat. La longueur de 10 laisse la marge d'un futur libellé sans
 * transformer la colonne en champ libre.
 *
 * Chaque table conserve SON défaut : ttc côté vente, ht côté achat.
 */
return new class extends Migration
{
    /** table => valeur par défaut historique, à préserver */
    private const TABLES = [
        'quotes'            => 'ttc',
        'orders'            => 'ttc',
        'invoices'          => 'ttc',
        'purchase_orders'   => 'ht',
        'supplier_invoices' => 'ht',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $default) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'price_mode')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($default) {
                $t->string('price_mode', 10)->nullable()->default($default)->change();
            });
        }
    }

    public function down(): void
    {
        // Retour à 5 caractères. Toute valeur « exonere » présente serait alors
        // refusée : on ne rétrécit pas une colonne sans que l'appelant sache ce
        // qu'il fait, d'où la vérification préalable.
        foreach (self::TABLES as $table => $default) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'price_mode')) {
                continue;
            }
            if (\Illuminate\Support\Facades\DB::table($table)->where('price_mode', 'exonere')->exists()) {
                throw new \RuntimeException(
                    "Rétrécissement refusé : « {$table} » contient des lignes en mode « exonere », "
                    . 'que la colonne ne pourrait plus stocker.'
                );
            }
            Schema::table($table, function (Blueprint $t) use ($default) {
                $t->string('price_mode', 5)->nullable()->default($default)->change();
            });
        }
    }
};
