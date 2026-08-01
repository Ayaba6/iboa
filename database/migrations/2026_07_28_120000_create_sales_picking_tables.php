<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes §4.3] Bons de préparation QUANTIFIÉS — nouvelles tables.
 *
 * Ce schéma NE remplace PAS `bon_preparations` : les documents historiques sont
 * des bons de chargement sans lignes (LEGACY_UNQUANTIFIED) et ne peuvent pas
 * être reconstruits en préparations quantifiées sans inventer des données.
 * Les deux familles de documents coexistent ; le BL devra se construire depuis
 * les quantités préparées et validées ici, jamais depuis la commande seule.
 *
 * Conventions :
 *  - noms de contraintes COURTS et explicites (limite MySQL 64 caractères) ;
 *  - migration idempotente (le DDL MySQL n'est pas transactionnel : un échec
 *    partiel ne doit pas rendre la migration irrejouable) ;
 *  - aucune suppression physique prévue : l'annulation est un statut.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_pickings')) {
            Schema::create('sales_pickings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('fiscal_year_id')->nullable();
                $table->string('number', 50);
                // Machine d'états — 8 axes. BROUILLON n'existe qu'avant soumission.
                $table->string('status', 30)->default('brouillon');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('priority', 20)->default('normale');
                $table->date('requested_date')->nullable();
                $table->text('notes')->nullable();

                // Acteurs séparés : préparateur ≠ contrôleur ≠ validateur.
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->unsignedBigInteger('prepared_by')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->unsignedBigInteger('controlled_by')->nullable();
                $table->timestamp('controlled_at')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason', 500)->nullable();

                // Idempotence durable : création / lancement / validation / annulation.
                $table->string('idempotency_key', 100)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'uq_spick_number');
                $table->unique('idempotency_key', 'uq_spick_idem');
                $table->index(['company_id', 'order_id'], 'ix_spick_order');
                $table->index(['company_id', 'status'], 'ix_spick_status');
                $table->foreign('order_id', 'fk_spick_order')->references('id')->on('orders');
                $table->foreign('warehouse_id', 'fk_spick_wh')->references('id')->on('warehouses');
            });
        }

        if (! Schema::hasTable('sales_picking_items')) {
            Schema::create('sales_picking_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_picking_id');
                $table->unsignedBigInteger('order_item_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('unit_id')->nullable();

                // [Ventes §11] Modèle des quantités — champs SÉPARÉS, jamais un
                // champ unique pour réservation + prélèvement + validation.
                // Snapshot du contexte commande au moment de la création :
                $table->decimal('qty_ordered', 15, 3)->default(0);
                $table->decimal('qty_previously_delivered', 15, 3)->default(0);
                $table->decimal('qty_cancelled', 15, 3)->default(0);
                $table->decimal('qty_remaining_snapshot', 15, 3)->default(0);
                // Vie du bon :
                $table->decimal('qty_reserved', 15, 3)->default(0);
                $table->decimal('qty_allocated', 15, 3)->default(0);
                $table->decimal('qty_picked', 15, 3)->default(0);
                $table->decimal('qty_controlled', 15, 3)->default(0);
                $table->decimal('qty_validated', 15, 3)->default(0);
                // Écart entre alloué et réellement prélevé — jamais silencieux.
                $table->decimal('variance_qty', 15, 3)->default(0);
                $table->string('variance_reason', 500)->nullable();

                $table->timestamps();

                $table->index('sales_picking_id', 'ix_spitem_picking');
                $table->index('order_item_id', 'ix_spitem_oitem');
                $table->foreign('sales_picking_id', 'fk_spitem_picking')->references('id')->on('sales_pickings');
                $table->foreign('order_item_id', 'fk_spitem_oitem')->references('id')->on('order_items');
                $table->foreign('product_id', 'fk_spitem_product')->references('id')->on('products');
            });
        }

        if (! Schema::hasTable('sales_picking_allocations')) {
            Schema::create('sales_picking_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_picking_item_id');
                $table->unsignedBigInteger('stock_lot_id')->nullable();
                $table->unsignedBigInteger('coil_id')->nullable();
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('location_id')->nullable();
                $table->decimal('quantity', 15, 3);
                $table->unsignedBigInteger('unit_id')->nullable();
                // Conversion figée au moment de l'allocation — pas recalculée après.
                $table->json('conversion_snapshot')->nullable();
                // Coût HISTORIQUE du lot/bobine, figé — jamais le coût courant.
                $table->decimal('historical_unit_cost', 15, 4)->nullable();
                $table->unsignedBigInteger('stock_reservation_id')->nullable();
                $table->string('status', 20)->default('allouee'); // allouee|prelevee|annulee
                $table->timestamps();

                $table->index('sales_picking_item_id', 'ix_spalloc_item');
                $table->index('stock_lot_id', 'ix_spalloc_lot');
                $table->index('coil_id', 'ix_spalloc_coil');
                $table->foreign('sales_picking_item_id', 'fk_spalloc_item')->references('id')->on('sales_picking_items');
                $table->foreign('stock_lot_id', 'fk_spalloc_lot')->references('id')->on('stock_lots');
                $table->foreign('warehouse_id', 'fk_spalloc_wh')->references('id')->on('warehouses');
            });
        }

        if (! Schema::hasTable('sales_picking_controls')) {
            Schema::create('sales_picking_controls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_picking_id');
                $table->unsignedBigInteger('sales_picking_item_id')->nullable();
                $table->unsignedBigInteger('controlled_by');
                $table->string('result', 20); // conforme|ecart
                // Points vérifiés : article, lot, bobine, quantité, poids, dépôt,
                // emplacement, qualité, commande, client — structure explicite.
                $table->json('checkpoints')->nullable();
                $table->string('notes', 1000)->nullable();
                // Un contrôle n'est jamais supprimé : une modification postérieure
                // l'INVALIDE avec motif, et un nouveau contrôle est exigé.
                $table->timestamp('invalidated_at')->nullable();
                $table->string('invalidated_reason', 500)->nullable();
                $table->timestamps();

                $table->index('sales_picking_id', 'ix_spctrl_picking');
                $table->foreign('sales_picking_id', 'fk_spctrl_picking')->references('id')->on('sales_pickings');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_picking_controls');
        Schema::dropIfExists('sales_picking_allocations');
        Schema::dropIfExists('sales_picking_items');
        Schema::dropIfExists('sales_pickings');
    }
};
