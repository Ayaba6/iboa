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
        Schema::create('bon_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('number', 50)->unique();
            // [CDC] Mode déclencheur : cash (paiement caissier) ou credit (validation responsable)
            $table->enum('payment_mode', ['cash', 'credit'])->default('credit');
            $table->enum('status', ['en_attente', 'en_cours', 'charge', 'annule'])->default('en_attente');
            // Cash: référence et montant du règlement à la caisse
            $table->string('payment_reference', 100)->nullable();
            $table->unsignedBigInteger('payment_amount')->nullable();
            $table->foreignId('payment_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_recorded_at')->nullable();
            // Credit: responsable qui a déclenché la création
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            // Magasinier qui procède au chargement
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_preparations');
    }
};
