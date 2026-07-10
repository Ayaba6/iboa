<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — articles sous-produits liés à la nomenclature :
 *   scrap_product_id  : article « chute » (vendu au kg)
 *   defect_product_id : article « avarié » (déclassé)
 * Renseignés au suivi de fabrication (quantité/poids réels) → entrée en stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('bills_of_materials', 'scrap_product_id')) {
                $table->foreignId('scrap_product_id')->nullable()->after('product_id')
                    ->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('bills_of_materials', 'defect_product_id')) {
                $table->foreignId('defect_product_id')->nullable()->after('scrap_product_id')
                    ->constrained('products')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scrap_product_id');
            $table->dropConstrainedForeignId('defect_product_id');
        });
    }
};
