<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [CDC Tarifaire] Prix plafond indicatif (alerte non bloquante si dépassé). Miroir de min_sale_price.
            $table->unsignedBigInteger('max_sale_price')->nullable()->after('min_sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('max_sale_price');
        });
    }
};
