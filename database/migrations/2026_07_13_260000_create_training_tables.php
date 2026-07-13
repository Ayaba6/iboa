<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-10] Formation & compétences : sessions, coûts, présences, évaluations,
 * habilitations, certificats et échéances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('competence')->nullable();   // compétence / domaine visé
            $table->string('provider')->nullable();      // organisme de formation
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('cost', 15, 2)->nullable();  // coût total de la session
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->string('status')->default('planifiee'); // planifiee, en_cours, terminee, annulee
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('inscrit'); // inscrit, present, absent
            $table->decimal('score', 5, 2)->nullable();    // note d'évaluation
            $table->boolean('passed')->nullable();         // acquis / non acquis
            $table->string('certificate_number')->nullable();
            $table->date('certificate_expiry')->nullable(); // échéance habilitation
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('training_sessions');
    }
};
