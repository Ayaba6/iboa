<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3 §10 — Article-site] Paramètres d'un article POUR UN SITE donné.
 * Résolution : article-site > catégorie-site > catégorie globale > article.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_sites')) {
            return;
        }

        Schema::create('product_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('warehouses')->cascadeOnDelete();

            $table->foreignId('mp_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('pf_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('receipt_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('production_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->decimal('stock_min', 12, 2)->nullable();
            $table->decimal('stock_max', 12, 2)->nullable();
            $table->decimal('stock_securite', 12, 2)->nullable();

            $table->timestamps();
            $table->unique(['product_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sites');
    }
};
