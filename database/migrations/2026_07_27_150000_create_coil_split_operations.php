<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Division #1/#2/#3/#4] Préservation de l'historique de la bobine mère + document
 * append-only de l'opération de division.
 *
 * Règle : « division physique ≠ suppression de l'historique qualité ».
 *  - `quality_status` de la mère est CONSERVÉ (statut certifié avant division) ;
 *  - `quality_status_before_transformation` fige explicitement ce statut ;
 *  - `initial_weight` (poids reçu) et le coût historique ne sont JAMAIS modifiés ;
 *  - seuls les SOLDES ACTIFS tombent à zéro (remaining_weight, qty_*) ;
 *  - `transferred_to_children_qty` trace la matière passée aux filles.
 *
 * Idempotente (DDL MySQL non transactionnel), noms d'index courts (limite 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            foreach ([
                'quality_status_before_transformation' => fn () => $table->string('quality_status_before_transformation', 20)->nullable()->after('transformation_status'),
                'transferred_to_children_qty'          => fn () => $table->decimal('transferred_to_children_qty', 12, 3)->nullable()->after('quality_status_before_transformation'),
                'transformed_at'                       => fn () => $table->timestamp('transformed_at')->nullable()->after('transferred_to_children_qty'),
                'transformed_by'                       => fn () => $table->unsignedBigInteger('transformed_by')->nullable()->after('transformed_at'),
            ] as $col => $add) {
                if (! Schema::hasColumn('coils', $col)) {
                    $add();
                }
            }
        });

        if (! Schema::hasTable('coil_split_operations')) {
            Schema::create('coil_split_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable();
                $table->unsignedBigInteger('coil_id');                 // bobine mère
                $table->string('number', 40);                          // numéro d'opération
                // État de la mère AVANT l'opération (figé).
                $table->decimal('mother_qty_before', 12, 3);
                $table->string('mother_quality_status_before', 20)->nullable();
                $table->decimal('mother_cost_per_kg', 15, 2)->nullable();
                $table->integer('mother_historical_cost')->nullable();
                // Répartition.
                $table->string('allocation_method', 30)->default('proportion_poids');
                $table->decimal('weighing_tolerance', 8, 3)->default(0);
                $table->decimal('scrap_qty', 12, 3)->default(0);
                $table->decimal('loss_qty', 12, 3)->default(0);
                $table->integer('scrap_value')->default(0);
                $table->integer('loss_value')->default(0);
                $table->integer('rounding_difference')->default(0);
                $table->boolean('requires_post_split_quality_control')->default(false);
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('idempotency_key', 128)->nullable();
                $table->string('status', 20)->default('appliquee');
                $table->char('calculation_hash', 64)->nullable();
                $table->timestamps();

                $table->index('coil_id', 'ix_cso_coil');
                $table->unique(['coil_id', 'idempotency_key'], 'uq_cso_idem');
            });
        }

        if (! Schema::hasTable('coil_split_operation_items')) {
            Schema::create('coil_split_operation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('split_operation_id');
                $table->unsignedBigInteger('child_coil_id');
                $table->decimal('weight', 12, 3);
                $table->integer('transferred_cost')->default(0);
                $table->string('quality_disposition', 20)->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('split_operation_id', 'ix_csoi_operation');
                $table->index('child_coil_id', 'ix_csoi_child');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coil_split_operation_items');
        Schema::dropIfExists('coil_split_operations');

        Schema::table('coils', function (Blueprint $table) {
            foreach ([
                'quality_status_before_transformation', 'transferred_to_children_qty',
                'transformed_at', 'transformed_by',
            ] as $col) {
                if (Schema::hasColumn('coils', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
