<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RÈGLE A — division physique] Traçabilité bobine mère → bobines filles.
 * Idempotente (DDL MySQL non transactionnel) ; nom d'index court (limite 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            if (! Schema::hasColumn('coils', 'parent_coil_id')) {
                $table->unsignedBigInteger('parent_coil_id')->nullable()->after('quality_decision_id');
                $table->index('parent_coil_id', 'ix_coils_parent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            if (Schema::hasColumn('coils', 'parent_coil_id')) {
                $table->dropIndex('ix_coils_parent');
                $table->dropColumn('parent_coil_id');
            }
        });
    }
};
