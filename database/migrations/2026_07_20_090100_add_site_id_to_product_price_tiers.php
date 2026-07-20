<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            // [CDC Tarifaire] Tarif par zone = agence (site_id / Warehouse). NULL = tous sites.
            $table->foreignId('site_id')->nullable()->after('client_id')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
