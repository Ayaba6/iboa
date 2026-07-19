<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes — tôles bac] Propage la conversion tôles (nombre + longueur unitaire)
 * du devis à la facture. order_items possède déjà nb_toles / metrage_par_tole ;
 * on les ajoute aux lignes de devis, factures et bons de livraison pour que les
 * deux informations restent visibles tout au long de la chaîne documentaire.
 * Nullable, idempotent (articles standards : colonnes laissées nulles).
 */
return new class extends Migration
{
    private array $tables = ['quote_items', 'invoice_items', 'delivery_note_items'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'nb_toles')) {
                    $table->decimal('nb_toles', 12, 2)->nullable()->after('quantity');
                }
                if (! Schema::hasColumn($t, 'metrage_par_tole')) {
                    $table->decimal('metrage_par_tole', 12, 4)->nullable()->after('nb_toles');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                $cols = array_values(array_filter(
                    ['nb_toles', 'metrage_par_tole'],
                    fn ($c) => Schema::hasColumn($t, $c)
                ));
                if ($cols) $table->dropColumn($cols);
            });
        }
    }
};
