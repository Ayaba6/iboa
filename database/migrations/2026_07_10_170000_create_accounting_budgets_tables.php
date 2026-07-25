<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette X3 — Budgets] Budgets comptables par compte général.
 * Réalisé calculé dynamiquement depuis journal_entry_lines (jamais stocké).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('code', 30);                     // BUD-2026
            $table->string('label', 120);                   // Budget principal 2026
            $table->string('version', 10)->default('V1');
            $table->unsignedTinyInteger('period_from')->default(1);   // mois 1-12
            $table->unsignedTinyInteger('period_to')->default(12);
            $table->string('status', 20)->default('en_cours');        // en_cours | valide | cloture
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code', 'version']);
        });

        Schema::create('accounting_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('cost_center', 30)->nullable();
            $table->string('axe', 30)->nullable();
            $table->bigInteger('initial_amount')->default(0);
            $table->bigInteger('revised_amount')->default(0);
            $table->bigInteger('committed_amount')->default(0);  // engagements (PO en cours…)
            $table->timestamps();
            $table->unique(['accounting_budget_id', 'account_id', 'cost_center'], 'abl_budget_account_cc_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_budget_lines');
        Schema::dropIfExists('accounting_budgets');
    }
};
