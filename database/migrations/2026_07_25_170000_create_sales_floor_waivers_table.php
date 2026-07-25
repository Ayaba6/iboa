<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_floor_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('line_type');
            $table->unsignedBigInteger('line_id');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('proposed_price', 15, 2);
            $table->decimal('minimum_price', 15, 2);
            $table->decimal('cost_basis', 15, 2);
            $table->string('cost_source');
            $table->decimal('margin_rate', 8, 2)->default(0);
            $table->decimal('expected_margin', 15, 2);
            $table->decimal('gap', 15, 2);
            $table->string('reason', 1000);
            $table->string('justification_path')->nullable();
            $table->string('pricing_signature', 64);
            $table->string('status')->default('brouillon');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'sales_floor_waiver_document_idx');
            $table->index(['line_type', 'line_id', 'status'], 'sales_floor_waiver_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_floor_waivers');
    }
};
