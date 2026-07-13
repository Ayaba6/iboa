<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-13] Départs & solde de tout compte : démission, fin de contrat,
 * licenciement, retraite — préavis, restitution, indemnités, congés, STC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type'); // demission, fin_contrat, licenciement, retraite, rupture, deces, autre
            $table->date('notice_start')->nullable();   // début du préavis
            $table->unsignedSmallInteger('notice_days')->nullable();
            $table->date('effective_date');             // date effective de départ
            $table->string('status')->default('declare'); // declare, en_cours, cloture
            $table->text('reason')->nullable();

            // Solde de tout compte
            $table->decimal('severance_amount', 15, 2)->default(0);      // indemnité de licenciement / départ
            $table->decimal('notice_amount', 15, 2)->default(0);         // indemnité de préavis
            $table->decimal('leave_balance_days', 8, 2)->default(0);     // congés restants (jours)
            $table->decimal('leave_balance_amount', 15, 2)->default(0);  // congés payés soldés
            $table->decimal('other_amount', 15, 2)->default(0);          // autres (primes, rappels)
            $table->decimal('total_stc', 15, 2)->default(0);             // total solde de tout compte

            $table->boolean('equipment_returned')->default(false);       // restitution matériel
            $table->boolean('documents_issued')->default(false);         // certificat travail + attestations
            $table->text('notes')->nullable();
            $table->date('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_departures');
    }
};
