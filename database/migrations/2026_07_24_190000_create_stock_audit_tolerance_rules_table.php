<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_tolerance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('absolute_tolerance_qty', 12, 4)->nullable();
            $table->string('tolerance_unit', 10)->default('KG');
            $table->decimal('relative_tolerance_percent', 8, 4)->nullable();
            $table->enum('selection_mode', ['strictest', 'widest', 'absolute_only', 'relative_only'])->default('strictest');
            $table->timestamp('effective_at')->nullable();
            $table->enum('status', ['draft', 'validated', 'retired'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'effective_at', 'company_id', 'item_category_id', 'product_id', 'warehouse_id'],
                'stock_audit_tol_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_tolerance_rules');
    }
};
