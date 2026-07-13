<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-09] Notes de frais : dépenses professionnelles des salariés, workflow
 * de soumission/approbation, remboursement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->string('title');
            $table->date('report_date')->nullable();
            $table->string('status')->default('brouillon'); // brouillon, soumise, approuvee, rejetee, remboursee
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approved_at')->nullable();
            $table->date('reimbursed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status']);
        });

        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_report_id')->constrained('expense_reports')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('expense_date')->nullable();
            $table->string('category')->default('autre'); // transport, hebergement, repas, carburant, fourniture, telephone, autre
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->boolean('has_receipt')->default(false);
            $table->timestamps();
            $table->index('expense_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expense_reports');
    }
};
