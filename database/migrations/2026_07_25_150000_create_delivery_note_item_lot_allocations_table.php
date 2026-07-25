<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('delivery_note_item_id')->nullable()->after('invoice_id')
                ->constrained('delivery_note_items')->nullOnDelete();
        });

        Schema::create('delivery_note_item_lot_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost_snapshot', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['delivery_note_item_id', 'stock_lot_id'], 'dn_item_lot_alloc_unique');
            $table->index(['stock_lot_id', 'reversed_at'], 'dn_lot_alloc_reversed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_item_lot_allocations');
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_note_item_id');
        });
    }
};
