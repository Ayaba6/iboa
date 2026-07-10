<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-005] Déclaration de production : traçabilité du lot produit fini et
 * observation libre sur la sortie de production. Colonnes nullable → additif,
 * aucun impact sur l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_outputs', 'lot_number')) {
                $table->string('lot_number', 60)->nullable()->after('warehouse_id');
            }
            if (! Schema::hasColumn('production_outputs', 'notes')) {
                $table->string('notes', 500)->nullable()->after('lot_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            foreach (['lot_number', 'notes'] as $col) {
                if (Schema::hasColumn('production_outputs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
