<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_valuation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('original_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('adjustment_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('old_unit_cost', 15, 2);
            $table->decimal('new_unit_cost', 15, 2);
            $table->decimal('value_delta', 15, 2);
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['production_order_id', 'original_movement_id'], 'stock_val_adj_order_move_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_valuation_adjustments');
    }
};
