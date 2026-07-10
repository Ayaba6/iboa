<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conformité CDC « Règles de gestion des articles » :
 * - Catégorie : S<0 (stock négatif autorisé)
 * - Article : flux F (fabriqué) + coefficients UA-US / UV-US à 6 décimales
 * - Dépôts : propriétés indépendantes Production / Ventes / Achat / Stock
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->boolean('stock_negatif')->default(false)->after('gestion_stock')->comment('S<0 : stock pouvant passer en négatif');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_manufacturable')->default(false)->after('is_sellable')->comment('Flux F : fabriqué');
            $table->decimal('ua_to_us_coef', 14, 6)->default(1)->change();
            $table->decimal('uv_to_us_coef', 14, 6)->default(1)->change();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('can_production')->default(false)->after('type');
            $table->boolean('can_sale')->default(false)->after('can_production');
            $table->boolean('can_purchase')->default(true)->after('can_sale');
            $table->boolean('can_stock')->default(true)->after('can_purchase');
        });

        // Backfill des flags dépôts depuis le type existant
        DB::table('warehouses')->update([
            'can_production' => DB::raw("CASE WHEN type IN ('production','matiere_premiere','produit_fini') THEN 1 ELSE 0 END"),
            'can_sale'       => DB::raw("CASE WHEN type IN ('vente','produit_fini') OR type IS NULL THEN 1 ELSE 0 END"),
            'can_purchase'   => 1,
            'can_stock'      => 1,
        ]);

        // Backfill flux F : produits finis + chutes/avariés issus de production
        DB::table('products')
            ->whereIn('type_article', ['produit_fini'])
            ->update(['is_manufacturable' => true]);
    }

    public function down(): void
    {
        Schema::table('product_families', fn (Blueprint $t) => $t->dropColumn('stock_negatif'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_manufacturable');
            $table->decimal('ua_to_us_coef', 14, 4)->default(1)->change();
            $table->decimal('uv_to_us_coef', 14, 4)->default(1)->change();
        });
        Schema::table('warehouses', fn (Blueprint $t) => $t->dropColumn(['can_production', 'can_sale', 'can_purchase', 'can_stock']));
    }
};
