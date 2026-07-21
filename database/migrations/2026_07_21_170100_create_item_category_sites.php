<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3 Catégories §9] Déclinaison d'une catégorie par SITE (agence).
 * La valeur du site, quand renseignée, a priorité sur la valeur globale.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_category_sites')) {
            return;
        }

        Schema::create('item_category_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_category_id')->constrained('item_categories')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('warehouses')->cascadeOnDelete(); // site = Warehouse (agence)

            $table->foreignId('mp_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('pf_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('receipt_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('production_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->decimal('stock_min', 12, 2)->nullable();
            $table->decimal('stock_max', 12, 2)->nullable();
            $table->decimal('stock_securite', 12, 2)->nullable();
            $table->boolean('mrp_planned')->nullable();

            $table->timestamps();
            $table->unique(['item_category_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category_sites');
    }
};
