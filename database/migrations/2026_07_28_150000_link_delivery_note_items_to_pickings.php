<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes §4.3 / §20-11] Rattachement du bon de livraison aux lignes de
 * préparation VALIDÉES.
 *
 * Sans ce lien, le BL se construit depuis la commande et personne ne peut
 * vérifier l'invariant « livré ≤ préparé validé » : l'audit des préparations
 * devait déclarer cette détection NON APPLICABLE.
 *
 * La colonne est NULLABLE, et ce n'est pas un compromis :
 *   - les BL historiques n'ont aucune préparation quantifiée derrière eux, et
 *     leur en inventer une falsifierait l'historique ;
 *   - le flux direct commande → BL reste légitime pour les cas sans préparation
 *     (livraison immédiate, article non stocké).
 *
 * Un BL issu d'une préparation porte le lien ; les autres portent NULL, et NULL
 * signifie ici « pas de préparation », jamais « préparation inconnue ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delivery_note_items', 'sales_picking_item_id')) {
            return;
        }

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_picking_item_id')->nullable()->after('order_item_id');
            $table->index('sales_picking_item_id', 'ix_dni_picking_item');
            $table->foreign('sales_picking_item_id', 'fk_dni_picking_item')
                ->references('id')->on('sales_picking_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('delivery_note_items', 'sales_picking_item_id')) {
            return;
        }

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign('fk_dni_picking_item');
            $table->dropIndex('ix_dni_picking_item');
            $table->dropColumn('sales_picking_item_id');
        });
    }
};
