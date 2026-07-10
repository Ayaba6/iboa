<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E — enrichissement du référentiel article (cahier des charges §1).
 *
 * 100 % ADDITIF : aucune colonne existante n'est supprimée ni renommée.
 *   reference / family_id / unit_id / is_active restent en place (rétro-compat).
 *
 * Ajoute :
 *   • code_article (10), statut (actif/sommeil)
 *   • famille1/2/3 (3 niveaux explicites, FK product_families)
 *   • unités multiples : achat / vente / poids (US = unit_id existant) + coefficients
 *   • poids brut / net par unité de stockage
 *   • gestion stock : stock négatif autorisé, stock de sécurité, dépôt principal
 *
 * Back-fill des données existantes pour cohérence immédiate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'code_article')) {
                $table->string('code_article', 10)->nullable()->unique()->after('reference');
            }
            if (! Schema::hasColumn('products', 'statut')) {
                $table->string('statut', 10)->default('actif')->after('code_article')->index();
            }
            if (! Schema::hasColumn('products', 'famille1_id')) {
                $table->foreignId('famille1_id')->nullable()->after('family_id')->constrained('product_families')->nullOnDelete();
                $table->foreignId('famille2_id')->nullable()->after('famille1_id')->constrained('product_families')->nullOnDelete();
                $table->foreignId('famille3_id')->nullable()->after('famille2_id')->constrained('product_families')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'purchase_unit_id')) {
                $table->foreignId('purchase_unit_id')->nullable()->after('unit_id')->constrained('units')->nullOnDelete();
                $table->foreignId('sale_unit_id')->nullable()->after('purchase_unit_id')->constrained('units')->nullOnDelete();
                $table->foreignId('weight_unit_id')->nullable()->after('sale_unit_id')->constrained('units')->nullOnDelete();
                $table->decimal('ua_to_us_coef', 14, 4)->default(1)->after('weight_unit_id');
                $table->decimal('uv_to_us_coef', 14, 4)->default(1)->after('ua_to_us_coef');
                $table->decimal('gross_weight_per_us', 14, 4)->nullable()->after('uv_to_us_coef');
                $table->decimal('net_weight_per_us', 14, 4)->nullable()->after('gross_weight_per_us');
            }
            if (! Schema::hasColumn('products', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(false)->after('reorder_point');
                $table->decimal('stock_securite', 14, 3)->default(0)->after('allow_negative_stock');
                $table->foreignId('main_warehouse_id')->nullable()->after('stock_securite')->constrained('warehouses')->nullOnDelete();
            }
        });

        // ── Back-fill (données existantes) ───────────────────────────────────
        // FK simples : portables tous moteurs.
        DB::statement('UPDATE products SET famille1_id = family_id WHERE famille1_id IS NULL AND family_id IS NOT NULL');
        DB::statement('UPDATE products SET purchase_unit_id = unit_id WHERE purchase_unit_id IS NULL AND unit_id IS NOT NULL');
        DB::statement('UPDATE products SET sale_unit_id = unit_id WHERE sale_unit_id IS NULL AND unit_id IS NOT NULL');

        // code_article / statut : fonctions CONCAT/LPAD propres à MySQL.
        // (En tests SQLite la table est vide → rien à back-fill.)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE products SET statut = CASE WHEN is_active = 1 THEN 'actif' ELSE 'sommeil' END WHERE statut IS NULL OR statut = ''");
            DB::statement("UPDATE products SET code_article = CONCAT('ART', LPAD(id, 7, '0')) WHERE code_article IS NULL");
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('famille1_id');
            $table->dropConstrainedForeignId('famille2_id');
            $table->dropConstrainedForeignId('famille3_id');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropConstrainedForeignId('sale_unit_id');
            $table->dropConstrainedForeignId('weight_unit_id');
            $table->dropConstrainedForeignId('main_warehouse_id');
            $table->dropColumn([
                'code_article', 'statut', 'ua_to_us_coef', 'uv_to_us_coef',
                'gross_weight_per_us', 'net_weight_per_us', 'allow_negative_stock', 'stock_securite',
            ]);
        });
    }
};
