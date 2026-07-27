<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Division #3/#5] Snapshot COMPLET de la bobine avant division + valeur
 * RÉSIDUELLE réellement répartissable.
 *
 * Sans ces colonnes, la réconciliation financière est fausse dès qu'une bobine a
 * été partiellement consommée : l'écart d'arrondi était calculé contre le coût
 * historique TOTAL alors que seule la valeur résiduelle est répartie.
 *
 *   valeur résiduelle = coût historique − coût des consommations − retours − pertes
 *   valeur résiduelle = Σ coûts enfants + chutes + nouvelles pertes + arrondi
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coil_split_operations')) {
            return;
        }

        Schema::table('coil_split_operations', function (Blueprint $table) {
            foreach ([
                'mother_initial_weight'   => fn () => $table->decimal('mother_initial_weight', 12, 3)->nullable()->after('mother_qty_before'),
                'consumed_before_split'   => fn () => $table->decimal('consumed_before_split', 12, 3)->nullable()->after('mother_initial_weight'),
                'returned_before_split'   => fn () => $table->decimal('returned_before_split', 12, 3)->nullable()->after('consumed_before_split'),
                'released_before_split'   => fn () => $table->decimal('released_before_split', 12, 3)->nullable()->after('returned_before_split'),
                'quarantine_before_split' => fn () => $table->decimal('quarantine_before_split', 12, 3)->nullable()->after('released_before_split'),
                'residual_cost_before_split' => fn () => $table->integer('residual_cost_before_split')->nullable()->after('mother_historical_cost'),
                'consumed_cost_before_split' => fn () => $table->integer('consumed_cost_before_split')->nullable()->after('residual_cost_before_split'),
                'warehouse_before_split'  => fn () => $table->unsignedBigInteger('warehouse_before_split')->nullable()->after('consumed_cost_before_split'),
                'transferred_cost'        => fn () => $table->integer('transferred_cost')->nullable()->after('warehouse_before_split'),
            ] as $col => $add) {
                if (! Schema::hasColumn('coil_split_operations', $col)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coil_split_operations')) {
            return;
        }
        Schema::table('coil_split_operations', function (Blueprint $table) {
            foreach ([
                'mother_initial_weight', 'consumed_before_split', 'returned_before_split',
                'released_before_split', 'quarantine_before_split', 'residual_cost_before_split',
                'consumed_cost_before_split', 'warehouse_before_split', 'transferred_cost',
            ] as $col) {
                if (Schema::hasColumn('coil_split_operations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
