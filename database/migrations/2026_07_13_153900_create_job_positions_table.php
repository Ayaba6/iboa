<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-01] Référentiel Postes / Grades / Emplois.
 * Structure les intitulés libres (employees.job_title) en un référentiel
 * rattaché aux départements, avec grade, catégorie, centre de coût et
 * fourchette salariale — base des mouvements de carrière et de la paie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('grade', 60)->nullable();
            $table->string('category', 60)->nullable();          // A, B, C… / cadre, agent de maîtrise, ouvrier
            $table->string('cost_center', 40)->nullable();
            $table->unsignedInteger('headcount_target')->nullable(); // effectif cible
            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('missions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'job_position_id')) {
                $table->foreignId('job_position_id')->nullable()->after('department_id')
                    ->constrained('job_positions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'job_position_id')) {
                $table->dropConstrainedForeignId('job_position_id');
            }
        });
        Schema::dropIfExists('job_positions');
    }
};
