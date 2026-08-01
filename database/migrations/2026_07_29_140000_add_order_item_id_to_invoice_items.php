<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes §21.3] Rattacher chaque ligne de facture à SA ligne de commande.
 *
 * `invoice_items` portait déjà `delivery_note_item_id` mais pas `order_item_id` :
 * une facture créée directement depuis une commande ne conservait aucun lien de
 * ligne à ligne. Le rapprochement se faisait par `product_id`.
 *
 * Conséquence chiffrée, pas théorique. À l'annulation d'une facture, le code
 * exécutait :
 *
 *     OrderItem::where('order_id', …)->where('product_id', …)
 *              ->decrement('invoiced_quantity', $invItem->quantity);
 *
 * Sur une commande portant DEUX lignes du même article — cas courant en tôle bac,
 * même référence en longueurs différentes — le `where` en sélectionne deux et
 * `decrement()` retranche la quantité ENTIÈRE à CHACUNE. Annuler une facture de
 * 40 retirait 40 à la ligne facturée et 40 à sa jumelle, qui n'avait rien à
 * rendre : `invoiced_quantity` devenait négatif et la commande repassait comme
 * facturable au-delà de ce qui restait réellement à facturer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_items', 'order_item_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->foreignId('order_item_id')->nullable()->after('delivery_note_item_id');
                // Nom court : MySQL plafonne les identifiants à 64 caractères et le
                // nom dérivé automatiquement dépassait la limite.
                $table->foreign('order_item_id', 'inv_items_order_item_fk')
                    ->references('id')->on('order_items')->nullOnDelete();
                $table->index('order_item_id', 'inv_items_order_item_idx');
            });
        }

        // ── Reprise de l'historique ──────────────────────────────────────────
        // Deux sources, de la plus sûre à la moins sûre. Aucune ligne n'est
        // rattachée par approximation : ce qui reste ambigu reste NULL, et le
        // service retombe alors sur l'ancien comportement, bridé.

        // 1. Via le bon de livraison, qui porte déjà le lien de ligne à ligne.
        //    C'est une égalité, pas une déduction.
        DB::table('invoice_items')
            ->whereNull('order_item_id')
            ->whereNotNull('delivery_note_item_id')
            ->update([
                'order_item_id' => DB::raw(
                    '(SELECT dni.order_item_id FROM delivery_note_items dni'
                    .' WHERE dni.id = invoice_items.delivery_note_item_id)'
                ),
            ]);

        // 2. Via le produit, UNIQUEMENT quand la commande ne contient qu'une
        //    seule ligne de ce produit. Dès qu'il y en a deux, le lien est
        //    indécidable — c'est précisément le cas qui produisait le bug — et la
        //    colonne reste NULL plutôt que de figer une association inventée.
        DB::table('invoice_items')
            ->whereNull('order_item_id')
            ->whereNotNull('product_id')
            ->update([
                // `MIN(oi.id)` et non `oi.id` : sous `only_full_group_by` (actif par
                // défaut sur MySQL 8), une colonne non agrégée dans un SELECT porteur
                // d'un HAVING est rejetée. Le HAVING garantissant l'unicité, MIN()
                // renvoie de toute façon la seule ligne existante.
                'order_item_id' => DB::raw(
                    '(SELECT MIN(oi.id) FROM order_items oi'
                    .' JOIN invoices inv ON inv.id = invoice_items.invoice_id'
                    .' WHERE oi.order_id = inv.order_id'
                    .'   AND oi.product_id = invoice_items.product_id'
                    .' HAVING COUNT(*) = 1)'
                ),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_items', 'order_item_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropForeign('inv_items_order_item_fk');
                $table->dropIndex('inv_items_order_item_idx');
                $table->dropColumn('order_item_id');
            });
        }
    }
};
