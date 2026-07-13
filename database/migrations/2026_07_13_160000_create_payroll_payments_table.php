<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PAI-07] Virements & paiements de paie.
 * Une ligne de paiement par salarié pour un run de paie validé : net à payer,
 * mode (virement/espèces/mobile), coordonnées bancaires, statut et rapprochement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_name');
            $table->string('employee_matricule', 40)->nullable();
            $table->unsignedBigInteger('net_amount')->default(0);      // net à payer (FCFA entiers)
            $table->string('method', 20)->default('virement');         // virement / especes / cheque / mobile_money
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 60)->nullable();            // IBAN / RIB / n° compte
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('status', 20)->default('en_attente');       // en_attente / paye / rejete
            $table->string('reference', 60)->nullable();               // réf virement / pièce caisse
            $table->date('paid_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
    }
};
