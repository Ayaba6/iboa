<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_rep_id')->constrained('sales_reps')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('client_payments')->cascadeOnDelete();
            $table->decimal('base_amount', 15, 2)->comment('Montant encaissé base de calcul');
            $table->decimal('commission_rate', 5, 2)->comment('Taux appliqué au moment du calcul');
            $table->decimal('commission_amount', 15, 2)->comment('Montant commission = base × taux/100');
            $table->string('period', 7)->comment('YYYY-MM — période de la commission');
            $table->enum('status', ['calculee', 'validee', 'payee'])->default('calculee');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index(['sales_rep_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
