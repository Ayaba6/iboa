<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Paramétrage Vente X3] Comble les manques identifiés à l'audit :
 *  - clients : blocage + groupe + zone commerciale
 *  - payment_terms : acompte, échelonné, blocage impayé, validation
 *  - payment_methods : compte trésorerie, journal, PJ obligatoire
 *  - product_price_tiers : unité de vente + famille tarifaire (multi-unités ML/m²/kg/tonne/barre)
 *  - sales_discounts : remises commerciales paramétrables
 *  - sales_settings : paramètres généraux vente (singleton société)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('is_active');
            $table->string('blocked_reason', 255)->nullable()->after('is_blocked');
            $table->string('client_group', 60)->nullable()->after('category');
            $table->string('commercial_zone', 60)->nullable()->after('client_group');
        });

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->boolean('deposit_required')->default(false)->after('additional_days');
            $table->decimal('deposit_rate', 5, 2)->default(0)->after('deposit_required');       // % acompte
            $table->unsignedTinyInteger('installments_count')->default(1)->after('deposit_rate');
            $table->boolean('block_on_overdue')->default(false)->after('installments_count');
            $table->boolean('requires_validation')->default(false)->after('block_on_overdue');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('provider')
                  ->constrained('cash_accounts')->nullOnDelete();
            $table->string('journal_code', 10)->nullable()->after('cash_account_id');
            $table->boolean('attachment_required')->default(false)->after('requires_reference');
        });

        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('client_category')
                  ->constrained('units')->nullOnDelete();                                        // ML, m², pièce, kg, tonne, barre…
            $table->string('famille_tarifaire', 60)->nullable()->after('unit_id');
            $table->decimal('margin_min_percent', 5, 2)->nullable()->after('discount_percent');
        });

        Schema::create('sales_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('discount_type', 30)->default('client');          // client|groupe_client|categorie_client|article|famille_article|volume|promotionnelle|exceptionnelle
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_group', 60)->nullable();
            $table->string('client_category', 60)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_family_id')->nullable()->constrained('product_families')->nullOnDelete();
            $table->decimal('rate_percent', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);                     // remise fixe optionnelle
            $table->decimal('min_quantity', 14, 3)->nullable();               // remise par volume
            $table->decimal('cap_amount', 14, 2)->nullable();                 // plafond
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('requires_validation')->default(false);          // au-delà du seuil
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['discount_type', 'is_active']);
        });

        Schema::create('sales_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('reserve_stock_on_quote')->default(false);        // réservation temporaire au devis
            $table->boolean('allow_direct_invoicing')->default(true);         // facture sans BL décrémente
            $table->boolean('enforce_price_floor')->default(true);            // prix plancher strict
            $table->decimal('discount_validation_threshold', 5, 2)->default(10); // % remise → validation DAF/DG
            $table->decimal('default_margin_min', 5, 2)->default(0);          // marge minimale défaut
            $table->unsignedSmallInteger('quote_validity_days')->default(30);
            $table->boolean('block_sales_on_overdue')->default(false);        // client en retard bloqué
            $table->boolean('require_order_for_delivery')->default(true);
            $table->foreignId('default_sales_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('quote_footer_note')->nullable();
            $table->text('invoice_footer_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_settings');
        Schema::dropIfExists('sales_discounts');
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['famille_tarifaire', 'margin_min_percent']);
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
            $table->dropColumn(['journal_code', 'attachment_required']);
        });
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->dropColumn(['deposit_required', 'deposit_rate', 'installments_count', 'block_on_overdue', 'requires_validation']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_blocked', 'blocked_reason', 'client_group', 'commercial_zone']);
        });
    }
};
