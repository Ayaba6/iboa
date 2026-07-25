<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            $table->enum('type', [
                'entree',
                'sortie',
                'transfert',
                'ajustement',
                'inventaire',
                'retour_client',
                'retour_fournisseur',
                'valuation_adjustment',
            ]);

            // Polymorphic reference (ex: "invoice", "reception", "delivery_note")
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Positive = entrée, negative = sortie
            $table->decimal('quantity', 12, 4);

            $table->decimal('unit_cost', 15, 0)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->enum('valuation_method', ['cmp', 'fifo', 'lifo'])->default('cmp');

            // CMP après mouvement
            $table->decimal('avg_cost_after', 15, 2)->default(0);

            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiry_date')->nullable();

            // Pour les transferts
            $table->foreignId('from_warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            $table->foreignId('to_warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            // Date effective du mouvement
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
