<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_item_lot_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_allocation_id')->constrained('delivery_note_item_lot_allocations')->restrictOnDelete();
            $table->foreignId('source_stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('returned_stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost_snapshot', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['credit_note_item_id', 'delivery_allocation_id'], 'cn_item_delivery_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_item_lot_returns');
    }
};
