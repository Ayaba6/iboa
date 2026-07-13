<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-11] Évaluation et performance : campagnes, objectifs/critères, auto-évaluation,
 * évaluation manager, note globale, plan d'action et prime liée — avec historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('campaign');                 // libellé de campagne (ex. « Annuelle 2026 »)
            $table->unsignedSmallInteger('period_year');
            $table->string('evaluator_name')->nullable();
            $table->string('status')->default('a_faire'); // a_faire, auto_evaluation, evaluation_manager, finalisee
            $table->decimal('self_score', 5, 2)->nullable();     // note d'auto-évaluation /5
            $table->decimal('manager_score', 5, 2)->nullable();  // note manager /5
            $table->decimal('overall_score', 5, 2)->nullable();  // note globale pondérée /5
            $table->string('rating')->nullable();               // insuffisant, a_ameliorer, satisfaisant, bon, excellent
            $table->text('objectives')->nullable();
            $table->text('action_plan')->nullable();
            $table->decimal('bonus_amount', 15, 2)->nullable();  // prime liée
            $table->text('comments')->nullable();
            $table->date('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'period_year']);
        });

        Schema::create('appraisal_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained('appraisals')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label');
            $table->unsignedSmallInteger('weight')->default(1);   // pondération (%)
            $table->decimal('self_rating', 3, 1)->nullable();     // /5
            $table->decimal('manager_rating', 3, 1)->nullable();  // /5
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index('appraisal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_criteria');
        Schema::dropIfExists('appraisals');
    }
};
