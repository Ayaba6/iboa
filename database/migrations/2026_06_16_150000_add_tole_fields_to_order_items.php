<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cas tôle bac (§5) : la ligne de commande saisit le nombre de tôles et le
 * métrage par tôle ; la quantité (stockée/facturée) est en mètres linéaires
 *   quantité MTL = nombre de tôles × métrage.
 * Ces deux champs sont conservés pour la traçabilité ; `quantity` reste le MTL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'nb_toles')) {
                $table->decimal('nb_toles', 12, 2)->nullable()->after('quantity');
                $table->decimal('metrage_par_tole', 10, 2)->nullable()->after('nb_toles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['nb_toles', 'metrage_par_tole']);
        });
    }
};
