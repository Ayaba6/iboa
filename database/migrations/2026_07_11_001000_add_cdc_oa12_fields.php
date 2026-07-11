<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC OA-12] Compléments référentiel :
 *  - products.bande / metrage : caractéristiques tôlerie (REF ARTICLE)
 *  - clients.created_by : traçabilité de la création de la fiche (règle 7)
 * NB : le prix plancher (règle 4) utilise la colonne existante products.min_sale_price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'bande')) {
                $table->decimal('bande', 10, 2)->nullable()->after('thickness')->comment('Largeur de bande (mm)');
            }
            if (! Schema::hasColumn('products', 'metrage')) {
                $table->decimal('metrage', 12, 2)->nullable()->after('bande')->comment('Métrage (m)');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('assigned_to')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['bande', 'metrage'] as $col) {
                if (Schema::hasColumn('products', $col)) $table->dropColumn($col);
            }
        });
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
