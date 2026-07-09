<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette Unité de mesure] code, nom anglais, dimension, hiérarchie parent/facteur,
// arrondi, catégorie, défauts inventaire/vente, description, commentaires internes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('id');
            $table->string('name_en', 100)->nullable()->after('name');
            $table->string('dimension', 30)->nullable()->after('type');           // masse, longueur, volume…
            $table->foreignId('parent_unit_id')->nullable()->after('dimension')
                  ->constrained('units')->nullOnDelete();                          // unité de base si null
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('parent_unit_id'); // 1 unité = X parent
            $table->string('rounding_mode', 20)->default('mathematique')->after('decimal_places'); // mathematique|superieur|inferieur
            $table->string('unit_category', 50)->nullable()->after('rounding_mode');
            $table->boolean('is_default_inventory')->default(false)->after('unit_category');
            $table->boolean('is_default_sales')->default(false)->after('is_default_inventory');
            $table->text('description')->nullable()->after('is_default_sales');
            $table->text('internal_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_unit_id');
            $table->dropColumn([
                'code', 'name_en', 'dimension', 'conversion_factor', 'rounding_mode',
                'unit_category', 'is_default_inventory', 'is_default_sales',
                'description', 'internal_notes',
            ]);
        });
    }
};
