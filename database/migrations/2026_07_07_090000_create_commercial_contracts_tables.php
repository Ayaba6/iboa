<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette X3 Contrats] contrats commerciaux (vente/achat) + lignes contractuelles.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();                              // CT-2026-0001
            $table->string('contract_type', 20)->default('vente');               // vente|achat
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255);
            $table->string('currency_code', 10)->default('XOF');
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('contract_date');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_framework')->default(false);                     // contrat cadre
            $table->string('status', 20)->default('brouillon');                  // brouillon|actif|suspendu|termine|annule
            $table->string('priority', 20)->default('normale');
            $table->string('project_reference', 60)->nullable();
            $table->string('category', 60)->nullable();                          // fourniture industrielle…
            $table->string('payment_terms', 80)->nullable();
            $table->string('incoterm', 40)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_currency', 10)->nullable();
            $table->string('client_contact', 100)->nullable();
            $table->string('supplier_contact', 100)->nullable();
            $table->string('transport_mode', 30)->nullable();
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->text('observations')->nullable();
            $table->decimal('total_ht', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'contract_date']);
        });

        Schema::create('commercial_contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('designation', 255);
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity', 14, 3)->default(0);                      // qté contractuelle
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('amount_ht', 18, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('brouillon');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_contract_items');
        Schema::dropIfExists('commercial_contracts');
    }
};
