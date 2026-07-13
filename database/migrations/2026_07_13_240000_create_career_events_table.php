<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-05] Mouvements et carrière : affectation, mutation, promotion, changement
 * de poste/grade/manager/site/centre de coût/rémunération — historique à date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type'); // affectation, mutation, promotion, changement_poste, changement_grade, revalorisation, reintegration, autre
            $table->date('effective_date');

            // Champs suivis sur l'employé (from → to)
            $table->foreignId('from_job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->foreignId('to_job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('from_category')->nullable();
            $table->string('to_category')->nullable();
            $table->string('from_fonction')->nullable();
            $table->string('to_fonction')->nullable();

            // Éléments déclaratifs (non portés par la fiche employé)
            $table->string('grade')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('site')->nullable();
            $table->string('cost_center')->nullable();
            $table->decimal('salary', 15, 2)->nullable();

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('applied')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_events');
    }
};
