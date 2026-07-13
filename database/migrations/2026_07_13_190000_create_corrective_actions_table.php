<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [QUA-05] Actions correctives / préventives (CAPA) rattachées aux non-conformités.
 * Cause racine, plan d'action, responsable, délai, vérification d'efficacité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('non_conformity_id')->constrained('non_conformities')->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->string('type')->default('corrective'); // corrective, preventive
            $table->text('root_cause')->nullable();         // cause racine (5 pourquoi, Ishikawa…)
            $table->text('action_plan');                    // plan d'action à mener
            $table->foreignId('responsible_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('a_faire');   // a_faire, en_cours, faite, verifiee, cloturee
            $table->date('completed_at')->nullable();
            // Vérification d'efficacité
            $table->text('effectiveness_comment')->nullable();
            $table->boolean('is_effective')->nullable();    // null = non vérifié, true/false = résultat
            $table->foreignId('verified_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['non_conformity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
