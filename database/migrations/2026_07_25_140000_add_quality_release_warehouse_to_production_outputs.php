<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->foreignId('release_warehouse_id')->nullable()->after('warehouse_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->dateTime('quality_released_at')->nullable()->after('release_warehouse_id');
            $table->foreignId('quality_released_by')->nullable()->after('quality_released_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quality_released_by');
            $table->dropColumn('quality_released_at');
            $table->dropConstrainedForeignId('release_warehouse_id');
        });
    }
};
