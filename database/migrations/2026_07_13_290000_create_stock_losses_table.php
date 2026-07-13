<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [STO-12] Pertes & casses : déclaration, cause, photo, responsabilité,
 * valorisation et validation (sortie de stock au PMP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('lot_number')->nullable();
            $table->string('type')->default('casse'); // casse, perte, vol, peremption, deterioration, autre
            $table->text('cause')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('unit_cost', 15, 2)->default(0);       // PMP figé à la validation
            $table->decimal('estimated_value', 15, 2)->default(0); // quantité × PMP
            $table->string('status')->default('declaree');         // declaree, validee, rejetee
            $table->text('reject_reason')->nullable();
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_losses');
    }
};
