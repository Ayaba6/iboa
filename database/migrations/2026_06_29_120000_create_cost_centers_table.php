<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['cost','profit','investment'])->default('cost');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->unique(['company_id','code']);
        });

        Schema::create('analytic_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->unsignedBigInteger('journal_entry_line_id')->nullable();
            $table->string('ref_type', 80)->nullable();   // App\Models\Invoice etc.
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->date('date');
            $table->string('label', 200);
            $table->enum('category', ['matiere','main_oeuvre','energie','maintenance','emballage','overhead','autre'])->default('autre');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->cascadeOnDelete();
            $table->foreign('journal_entry_line_id')->references('id')->on('journal_entry_lines')->nullOnDelete();
            $table->index(['company_id','date']);
            $table->index(['cost_center_id','date']);
            $table->index(['ref_type','ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytic_lines');
        Schema::dropIfExists('cost_centers');
    }
};
