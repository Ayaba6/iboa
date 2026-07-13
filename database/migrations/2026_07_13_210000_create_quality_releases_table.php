<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [QUA-07] Libération qualité des lots de fabrication avant mise à disposition
 * ou expédition. Décision tracée : libéré / refusé / sous dérogation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->foreignId('control_plan_id')->nullable()->constrained('control_plans')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->string('status')->default('en_attente'); // en_attente, libere, refuse, derogation
            $table->text('decision_comment')->nullable();
            $table->string('derogation_reference')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('production_batch_id'); // une décision de libération par lot
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_releases');
    }
};
