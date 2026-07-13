<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-03] Recrutement & onboarding.
 *  - recruitments : besoins de recrutement (postes à pourvoir).
 *  - job_candidates : candidatures rattachées à un besoin, pipeline de sélection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('title');
            $table->string('contract_type')->default('cdi'); // cdi, cdd, stage, interim
            $table->unsignedSmallInteger('positions_count')->default(1);
            $table->string('status')->default('ouvert'); // ouvert, en_cours, pourvu, annule
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('job_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_id')->constrained('recruitments')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable(); // candidature spontanée, annonce, cooptation…
            $table->string('cv_path')->nullable();
            $table->string('status')->default('recu'); // recu, preselectionne, entretien, retenu, rejete, embauche
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->text('notes')->nullable();
            $table->date('applied_at')->nullable();
            $table->foreignId('hired_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'recruitment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_candidates');
        Schema::dropIfExists('recruitments');
    }
};
