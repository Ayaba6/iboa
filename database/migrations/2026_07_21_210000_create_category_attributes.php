<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3 §10 — Attributs dynamiques] Caractéristiques définies PAR CATÉGORIE
 * (ex. PF_TOLE_MTO : nuance, garantie…) + valeurs saisies par article.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_attributes')) {
            Schema::create('category_attributes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_category_id')->constrained('item_categories')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('label', 120);
                $table->enum('type', ['text', 'number', 'select'])->default('text');
                $table->json('options')->nullable();   // valeurs possibles pour type=select
                $table->boolean('required')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['item_category_id', 'code']);
            });
        }

        if (! Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_attribute_id')->constrained('category_attributes')->cascadeOnDelete();
                $table->string('value', 255)->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'category_attribute_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('category_attributes');
    }
};
