<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité complète avec la fiche SAGE X3 « Articles : Création complète » :
 * site, désignation 2, dépôts (production/vente/qualité), seuil d'alerte,
 * TVA achat, canal client, caractéristiques de production tôle bac
 * (profil, couleur, largeur, longueur, machine, rendement, perte, articles
 * avarié/chute liés), imputation analytique, et pivot « dépôts autorisés ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('id')->constrained('warehouses')->nullOnDelete();
            $table->string('designation_2', 200)->nullable()->after('name');
            $table->string('client_type_canal', 60)->nullable()->after('supplier_reference');

            // Dépôts dédiés (en plus du dépôt principal existant)
            $table->foreignId('production_warehouse_id')->nullable()->after('main_warehouse_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('sale_warehouse_id')->nullable()->after('production_warehouse_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('quality_warehouse_id')->nullable()->after('sale_warehouse_id')->constrained('warehouses')->nullOnDelete();
            $table->decimal('seuil_alerte', 14, 3)->nullable()->after('reorder_point');

            // TVA achat (la TVA vente reste tax_rate_id) + imputation analytique
            $table->foreignId('tax_rate_achat_id')->nullable()->after('tax_rate_id')->constrained('tax_rates')->nullOnDelete();
            $table->foreignId('section_analytique_id')->nullable()->after('variation_stock_account_id')->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->after('section_analytique_id')->constrained('cost_centers')->nullOnDelete();

            // Caractéristiques de production (tôle bac)
            $table->string('nomenclature_ref', 60)->nullable()->after('production_mode');
            $table->string('profil', 60)->nullable()->after('nomenclature_ref');
            $table->string('couleur', 60)->nullable()->after('profil');
            $table->decimal('largeur_utile', 10, 2)->nullable()->after('couleur');
            $table->decimal('longueur_standard', 10, 2)->nullable()->after('largeur_utile');
            $table->foreignId('machine_defaut_id')->nullable()->after('longueur_standard')->constrained('production_machines')->nullOnDelete();
            $table->decimal('rendement_standard', 6, 4)->nullable()->after('machine_defaut_id');
            $table->decimal('taux_perte', 6, 4)->nullable()->after('rendement_standard');
            $table->foreignId('article_avarie_id')->nullable()->after('taux_perte')->constrained('products')->nullOnDelete();
            $table->foreignId('article_chute_id')->nullable()->after('article_avarie_id')->constrained('products')->nullOnDelete();
        });

        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->boolean('can_production')->default(false);
            $table->boolean('can_sale')->default(false);
            $table->boolean('can_purchase')->default(false);
            $table->boolean('can_stock')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');

        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'site_id', 'production_warehouse_id', 'sale_warehouse_id', 'quality_warehouse_id',
                'tax_rate_achat_id', 'section_analytique_id', 'cost_center_id',
                'machine_defaut_id', 'article_avarie_id', 'article_chute_id',
            ] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn([
                'designation_2', 'client_type_canal', 'seuil_alerte',
                'nomenclature_ref', 'profil', 'couleur', 'largeur_utile', 'longueur_standard',
                'rendement_standard', 'taux_perte',
            ]);
        });
    }
};
