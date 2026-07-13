<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PAI-08] Archive persistante des déclarations sociales & fiscales (CNSS, IUTS).
 * Fige les montants d'un run pour l'historique légal et le suivi de dépôt/paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('type'); // cnss, iuts
            $table->decimal('base_amount', 15, 2)->default(0);      // assiette
            $table->decimal('salarial_amount', 15, 2)->default(0);  // part salariale / retenue
            $table->decimal('patronal_amount', 15, 2)->default(0);  // part patronale
            $table->decimal('total_amount', 15, 2)->default(0);     // total à déclarer
            $table->unsignedSmallInteger('headcount')->default(0);
            $table->string('status')->default('a_deposer'); // a_deposer, depose, paye
            $table->date('deposited_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('receipt_number')->nullable(); // N° accusé de télédéclaration
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'type']);
            $table->index(['company_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_declarations');
    }
};
