<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3 §5] Sous-famille distincte sur l'article : dépend obligatoirement d'une
 * famille (product_families.parent_id) ; un article ne peut choisir qu'une
 * sous-famille appartenant à SA famille (garde serveur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sub_family_id')) {
                $table->foreignId('sub_family_id')->nullable()->after('family_id')
                    ->constrained('product_families')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_family_id');
        });
    }
};
