<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Synchronisation coils ↔ stock_lots ↔ stock_movements ↔ product_stocks.
 *
 * Architecture cible (CDC 17/07/2026) :
 *   Produit → Lot de stock (traçabilité, quantité en KG)
 *           → Bobines physiques (unités individuelles rattachées au lot)
 *   Une consommation = UN SEUL mouvement de sortie économique, exprimé dans
 *   l'unité de tenue de stock (KG pour les matières en bobines), avec
 *   conversion tracée (uom saisie, facteur, quantité stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_movements', 'uom')) Schema::table('stock_movements', function (Blueprint $table) {
            // Conversion d'unités : la saisie opérationnelle (ML…) est conservée
            // dans quantity/uom ; la quantité qui meut le stock est
            // quantity_in_stock_uom exprimée en stock_uom (KG pour les bobines).
            $table->string('uom', 10)->nullable()->after('quantity');
            $table->decimal('conversion_factor', 10, 4)->nullable()->after('uom');
            $table->decimal('quantity_in_stock_uom', 14, 4)->nullable()->after('conversion_factor');
            $table->string('stock_uom', 10)->nullable()->after('quantity_in_stock_uom');
            // Traçabilité lot / bobine / production
            $table->foreignId('stock_lot_id')->nullable()->after('lot_number')
                  ->constrained('stock_lots')->nullOnDelete();
            $table->unsignedBigInteger('coil_id')->nullable()->after('stock_lot_id');
            $table->unsignedBigInteger('production_order_id')->nullable()->after('coil_id');
            $table->unsignedBigInteger('production_consumption_id')->nullable()->after('production_order_id');
            // Idempotence + extourne
            $table->string('idempotency_key', 120)->nullable()->unique()->after('notes');
            $table->foreignId('reversal_of_movement_id')->nullable()->after('idempotency_key')
                  ->constrained('stock_movements')->nullOnDelete();

            $table->index('coil_id');
            $table->index('production_order_id');
            $table->index('production_consumption_id');
        });

        if (! Schema::hasColumn('stock_lots', 'initial_quantity')) Schema::table('stock_lots', function (Blueprint $table) {
            // quantity (existant) = quantité RESTANTE du lot.
            $table->decimal('initial_quantity', 14, 4)->nullable()->after('quantity');
            $table->decimal('reserved_quantity', 14, 4)->default(0)->after('initial_quantity');
            $table->string('stock_uom', 10)->nullable()->after('reserved_quantity');
            $table->decimal('kg_per_linear_meter', 10, 4)->nullable()->after('stock_uom');
            $table->string('supplier_lot_number', 80)->nullable()->after('lot_number');
            $table->string('source_type')->nullable()->after('status');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('created_by')->nullable()->after('source_id')
                  ->constrained('users')->nullOnDelete();

            $table->index(['product_id', 'warehouse_id']);
        });

        if (! Schema::hasColumn('coils', 'stock_lot_id')) Schema::table('coils', function (Blueprint $table) {
            $table->foreignId('stock_lot_id')->nullable()->after('lot_number')
                  ->constrained('stock_lots')->nullOnDelete();
            $table->decimal('kg_per_linear_meter', 10, 4)->nullable()->after('estimated_length');
            $table->index('stock_lot_id');
        });

        if (! Schema::hasColumn('products', 'kg_per_linear_meter')) Schema::table('products', function (Blueprint $table) {
            // Facteur de conversion par défaut (kg par mètre linéaire) — utilisé
            // en dernier recours après le facteur bobine puis lot.
            $table->decimal('kg_per_linear_meter', 10, 4)->nullable()->after('linear_meters');
        });

        Schema::table('production_consumptions', function (Blueprint $table) {
            if (! Schema::hasColumn('production_consumptions', 'consumption_source')) {
                $table->string('consumption_source', 20)->default('coil')->after('cost');
            }
            if (! Schema::hasColumn('production_consumptions', 'stock_movement_id')) {
                $table->foreignId('stock_movement_id')->nullable()->after('consumption_source')
                      ->constrained('stock_movements')->nullOnDelete();
            }
            if (! Schema::hasColumn('production_consumptions', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('stock_movement_id');
            }
            if (! Schema::hasColumn('production_consumptions', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')
                      ->constrained('users')->nullOnDelete();
            }
        });

        // Contraintes d'intégrité (MySQL 8+ uniquement — SQLite des tests ne
        // supporte pas ADD CONSTRAINT ; la validation applicative les couvre).
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'ALTER TABLE coils ADD CONSTRAINT chk_coils_remaining CHECK (remaining_weight >= 0)',
                'ALTER TABLE stock_lots ADD CONSTRAINT chk_lots_remaining CHECK (quantity >= 0)',
                'ALTER TABLE stock_lots ADD CONSTRAINT chk_lots_reserved CHECK (reserved_quantity >= 0)',
            ] as $sql) {
                try { DB::statement($sql); } catch (\Throwable) { /* déjà présente */ }
            }
        }

        // ── Données : facteur kg/ML des bobines existantes (déduit poids/longueur) ──
        DB::table('coils')
            ->whereNull('kg_per_linear_meter')
            ->whereNotNull('estimated_length')
            ->where('estimated_length', '>', 0)
            ->update(['kg_per_linear_meter' => DB::raw('ROUND(initial_weight / estimated_length, 4)')]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'ALTER TABLE coils DROP CONSTRAINT chk_coils_remaining',
                'ALTER TABLE stock_lots DROP CONSTRAINT chk_lots_remaining',
                'ALTER TABLE stock_lots DROP CONSTRAINT chk_lots_reserved',
            ] as $sql) {
                try { DB::statement($sql); } catch (\Throwable) { }
            }
        }

        Schema::table('production_consumptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_movement_id');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['consumption_source', 'reversed_at']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('kg_per_linear_meter');
        });
        Schema::table('coils', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropColumn('kg_per_linear_meter');
        });
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'initial_quantity', 'reserved_quantity', 'stock_uom', 'kg_per_linear_meter',
                'supplier_lot_number', 'source_type', 'source_id',
            ]);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropConstrainedForeignId('reversal_of_movement_id');
            $table->dropColumn([
                'uom', 'conversion_factor', 'quantity_in_stock_uom', 'stock_uom',
                'coil_id', 'production_order_id', 'production_consumption_id', 'idempotency_key',
            ]);
        });
    }
};
