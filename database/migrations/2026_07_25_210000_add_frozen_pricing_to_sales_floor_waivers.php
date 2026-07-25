<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_floor_waivers', function (Blueprint $table) {
            $table->decimal('conversion_factor', 15, 6)->default(1)->after('cost_source');
            $table->decimal('line_discount', 8, 4)->default(0)->after('margin_rate');
            $table->decimal('global_discount_ratio', 12, 8)->default(0)->after('line_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sales_floor_waivers', function (Blueprint $table) {
            $table->dropColumn(['conversion_factor', 'line_discount', 'global_discount_ratio']);
        });
    }
};
