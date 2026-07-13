<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [VEN Crédit client] Historique des décisions de crédit : blocage, déblocage,
 * dérogation, relèvement/réduction de plafond — traçabilité des décisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type'); // blocage, deblocage, derogation, relevement_plafond, reduction_plafond, autre
            $table->decimal('previous_limit', 15, 2)->nullable();
            $table->decimal('new_limit', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();   // montant de dérogation ponctuelle
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_decisions');
    }
};
