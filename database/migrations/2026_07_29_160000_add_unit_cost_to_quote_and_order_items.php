<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes] Rendre la marge calculable AVANT la facture.
 *
 * `unit_cost` n'existait que sur `invoice_items` : le coût n'apparaissait donc
 * qu'à la facturation, soit APRÈS la décision commerciale. Calculer une marge
 * sur un devis ou une commande était structurellement impossible — alors que
 * l'écran de devis comporte une section intitulée « Marge / Cumul » qui
 * n'affichait, faute de coût, que du stock et un total HT.
 *
 * Le coût est FIGÉ à la saisie de la ligne, pas lu à la volée : le CUMP d'un
 * article bouge à chaque réception. Une marge recalculée aujourd'hui sur un
 * devis de la semaine dernière ne serait pas celle que le commercial a vue en
 * négociant, et ne serait donc pas auditable.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['quote_items', 'order_items'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'unit_cost')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                // `decimal(15,2)` — EXACTEMENT le type de `invoice_items.unit_cost`.
                // Une précision supérieure en amont ferait perdre des décimales au
                // moment de recopier le coût sur la facture, et la marge du devis
                // ne serait plus réconciliable avec celle de la facture.
                $blueprint->decimal('unit_cost', 15, 2)->nullable()->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'unit_cost')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('unit_cost');
            });
        }
    }
};
