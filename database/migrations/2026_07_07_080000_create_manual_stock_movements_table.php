<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette X3 Mouvement manuel de stock] entête du document de mouvement —
// les lignes sont des stock_movements (reference_type = mouvement_manuel).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();                       // MVT-2026-0001
            $table->string('movement_type', 20)->default('manuel');
            $table->date('occurred_at');
            $table->time('occurred_time')->nullable();
            $table->string('status', 20)->default('saisi');              // saisi|valide|bloque
            $table->string('currency_code', 10)->default('XOF');
            $table->foreignId('warehouse_from_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('warehouse_to_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('location_from', 50)->nullable();
            $table->string('location_to', 50)->nullable();
            $table->string('reason', 30)->default('ajustement');          // ajustement|correction|don|perte|casse|autre
            $table->text('comment')->nullable();
            $table->string('project_reference', 60)->nullable();
            $table->string('analytic_section', 60)->nullable();
            $table->date('accounting_date')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_stock_movements');
    }
};
