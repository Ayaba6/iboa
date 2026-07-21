<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [X3 Catégories] Rattachement de l'article à sa catégorie de gestion.
            if (! Schema::hasColumn('products', 'item_category_id')) {
                $table->foreignId('item_category_id')->nullable()->after('family_id')
                    ->constrained('item_categories')->nullOnDelete();
            }
            // [X3 Axes §6] Axes statistiques 4 et 5 (1-3 existent déjà : famille1..3_id).
            if (! Schema::hasColumn('products', 'famille4_id')) {
                $table->foreignId('famille4_id')->nullable()->after('famille3_id')
                    ->constrained('product_families')->nullOnDelete();
                $table->foreignId('famille5_id')->nullable()->after('famille4_id')
                    ->constrained('product_families')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_category_id');
            $table->dropConstrainedForeignId('famille4_id');
            $table->dropConstrainedForeignId('famille5_id');
        });
    }
};
