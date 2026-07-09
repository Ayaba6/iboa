<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité complète avec la fiche SAGE X3 « Catégories : Création complète » :
 * site, désignation longue, type catégorie, famille principale, options de
 * gestion (suivi bobine, utilisable en production…), règles métier
 * (numérotation auto, prix plancher obligatoire, surcharge), imputation
 * analytique + taxes achat/vente, et pivot « dépôts autorisés » par catégorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('parent_id')->constrained('warehouses')->nullOnDelete();
            $table->string('designation_longue', 255)->nullable()->after('name');
            $table->string('type_categorie', 30)->nullable()->after('code_prefix');
            $table->foreignId('famille_principale_id')->nullable()->after('type_categorie')->constrained('product_families')->nullOnDelete();

            // Options de gestion supplémentaires
            $table->boolean('suivi_bobine')->default(false)->after('lot_obligatoire');
            $table->boolean('utilisable_production')->default(false)->after('suivi_bobine');
            $table->boolean('actif_tous_sites')->default(true)->after('utilisable_production');

            // Règles métier
            $table->boolean('numerotation_auto')->default(true)->after('cq_sortie');
            $table->boolean('prix_plancher_obligatoire')->default(false)->after('numerotation_auto');
            $table->boolean('autoriser_surcharge')->default(true)->after('prix_plancher_obligatoire');

            // Imputation analytique + taxes
            $table->foreignId('section_analytique_id')->nullable()->after('stock_account_id')->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->after('section_analytique_id')->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('tax_rate_achat_id')->nullable()->after('cost_center_id')->constrained('tax_rates')->nullOnDelete();
            $table->foreignId('tax_rate_vente_id')->nullable()->after('tax_rate_achat_id')->constrained('tax_rates')->nullOnDelete();
        });

        // Pivot : dépôts autorisés par catégorie (Production / Vente / Achat / Stock)
        Schema::create('category_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_family_id')->constrained('product_families')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->boolean('can_production')->default(false);
            $table->boolean('can_sale')->default(false);
            $table->boolean('can_purchase')->default(false);
            $table->boolean('can_stock')->default(false);
            $table->timestamps();
            $table->unique(['product_family_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_warehouse');

        Schema::table('product_families', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('famille_principale_id');
            $table->dropConstrainedForeignId('section_analytique_id');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropConstrainedForeignId('tax_rate_achat_id');
            $table->dropConstrainedForeignId('tax_rate_vente_id');
            $table->dropColumn([
                'designation_longue', 'type_categorie',
                'suivi_bobine', 'utilisable_production', 'actif_tous_sites',
                'numerotation_auto', 'prix_plancher_obligatoire', 'autoriser_surcharge',
            ]);
        });
    }
};
