<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRO-01] Substitution de composant : article de remplacement autorisé pour
 * une ligne de nomenclature (utilisable à l'allocation matière en cas de rupture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('bom_lines', 'substitute_product_id')) {
                $table->foreignId('substitute_product_id')->nullable()->after('product_id')
                    ->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('bom_lines', 'substitute_note')) {
                $table->string('substitute_note')->nullable()->after('substitute_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bom_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bom_lines', 'substitute_product_id')) {
                $table->dropConstrainedForeignId('substitute_product_id');
            }
            if (Schema::hasColumn('bom_lines', 'substitute_note')) {
                $table->dropColumn('substitute_note');
            }
        });
    }
};
