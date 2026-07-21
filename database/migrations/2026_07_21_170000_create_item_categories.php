<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3 Catégories] La CATÉGORIE d'article = modèle de gestion (comment l'article
 * fonctionne dans l'ERP). Distincte de la FAMILLE (= classement commercial /
 * statistique). Ne jamais fusionner les deux concepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_categories')) {
            return;
        }

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            // ── Informations générales ──
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('site_declinable')->default(false); // déclinable par site (item_category_sites)

            // ── Nature de l'article ──
            $table->enum('nature', [
                'matiere_premiere', 'semi_fini', 'produit_fini', 'marchandise',
                'consommable', 'service', 'sous_produit', 'chute', 'rebut',
            ]);

            // ── Origine et utilisation ──
            $table->boolean('is_purchasable')->default(false);
            $table->boolean('is_sellable')->default(false);
            $table->boolean('is_stockable')->default(false);
            $table->boolean('is_manufactured')->default(false);
            $table->boolean('is_subcontracted')->default(false);
            $table->boolean('usable_in_bom')->default(false);
            $table->boolean('usable_as_finished')->default(false);

            // ── Stratégie logistique ──
            $table->enum('strategy', ['mto', 'mts', 'achat_revente', 'service', 'conso_interne'])->nullable();

            // ── Gestion du stock ──
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('lot_managed')->default(false);
            $table->boolean('serial_managed')->default(false);
            $table->boolean('coil_managed')->default(false);
            $table->boolean('expiry_managed')->default(false);
            $table->boolean('qc_on_receipt')->default(false);
            $table->decimal('default_stock_min', 12, 2)->nullable();
            $table->decimal('default_stock_max', 12, 2)->nullable();
            $table->decimal('default_stock_securite', 12, 2)->nullable();
            $table->enum('valuation_method', ['cmp', 'fifo'])->nullable();

            // ── Gestion commerciale ──
            $table->foreignId('default_sale_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('default_pricing_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('default_tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('exempt_allowed')->default(false);
            $table->boolean('floor_price_required')->default(false);
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->boolean('credit_check')->default(true);

            // ── Gestion des achats ──
            $table->foreignId('default_purchase_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('receipt_tolerance_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->foreignId('default_receipt_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // ── Gestion de la production ──
            $table->boolean('bom_required')->default(false);
            $table->boolean('routing_required')->default(false);
            $table->boolean('auto_of')->default(false);
            $table->boolean('qc_required')->default(false);
            $table->foreignId('default_mp_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('default_pf_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('default_production_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->decimal('setup_loss', 10, 3)->nullable();
            $table->decimal('scrap_rate_percent', 5, 2)->nullable();
            $table->boolean('offcut_managed')->default(false);
            $table->boolean('cutting_optimized')->default(false);
            $table->boolean('mrp_planned')->default(false);

            // ── Comptabilité ──
            $table->foreignId('stock_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('sale_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('variation_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('consumption_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('scrap_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('finished_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('analytic_section_id')->nullable();
            $table->enum('cost_method', ['standard', 'moyen'])->nullable();

            // ── Héritage ──
            // Champs de l'article que l'utilisateur PEUT surcharger (liste blanche).
            $table->json('overridable_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
