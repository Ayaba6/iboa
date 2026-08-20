<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-A3-MTO-ALLOC-011] Rien ne portait l'affectation d'une production MTO à
 * la commande qui l'a déclenchée.
 *
 * Vérifié colonne par colonne avant d'écrire cette migration :
 *
 *     production_orders.order_item_id  ABSENTE
 *     production_outputs.order_id      ABSENTE
 *     stock_lots.order_id              ABSENTE
 *     product_stocks.order_id          ABSENTE
 *
 * La tôle fabriquée pour un client entrait donc en stock GÉNÉRAL. Seule la
 * garde de livraison, en aval, recoupait les quantités conformes par ordre de
 * fabrication — mais rien n'empêchait une autre commande de réserver entre
 * temps le produit fabriqué pour le premier client.
 *
 * Trois rattachements, chacun à son niveau :
 *
 *   - `production_orders.order_item_id` : l'OF vise la LIGNE, pas seulement la
 *     commande. Sur une commande portant deux fois le même article dans deux
 *     couleurs, la commande seule ne dit pas laquelle est produite.
 *   - `production_outputs.order_id` / `order_item_id` : la déclaration de
 *     production nomme son destinataire.
 *   - `stock_lots.reserved_for_order_id` : le lot de produit fini est réservé
 *     au client, ce qui rend l'affectation lisible par le stock lui-même.
 *
 * `ON DELETE SET NULL` partout : supprimer une commande ne doit pas emporter la
 * trace d'une production réellement faite. L'affectation disparaît, la
 * production reste.
 *
 * Additive et nullable. Les productions historiques restent non affectées —
 * c'est leur état réel, et le leur inventer serait pire que de l'admettre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('production_orders', 'order_item_id')) {
                $t->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
                $t->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            }
        });

        Schema::table('production_outputs', function (Blueprint $t) {
            if (! Schema::hasColumn('production_outputs', 'order_id')) {
                $t->unsignedBigInteger('order_id')->nullable()->after('production_order_id');
                $t->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('production_outputs', 'order_item_id')) {
                $t->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
                $t->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            }
        });

        Schema::table('stock_lots', function (Blueprint $t) {
            if (! Schema::hasColumn('stock_lots', 'reserved_for_order_id')) {
                $t->unsignedBigInteger('reserved_for_order_id')->nullable()->after('source_id');
                $t->foreign('reserved_for_order_id')->references('id')->on('orders')->nullOnDelete();
                $t->index('reserved_for_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $t) {
            $t->dropForeign(['order_item_id']);
            $t->dropColumn('order_item_id');
        });

        Schema::table('production_outputs', function (Blueprint $t) {
            $t->dropForeign(['order_id']);
            $t->dropForeign(['order_item_id']);
            $t->dropColumn(['order_id', 'order_item_id']);
        });

        Schema::table('stock_lots', function (Blueprint $t) {
            $t->dropForeign(['reserved_for_order_id']);
            $t->dropIndex(['reserved_for_order_id']);
            $t->dropColumn('reserved_for_order_id');
        });
    }
};
