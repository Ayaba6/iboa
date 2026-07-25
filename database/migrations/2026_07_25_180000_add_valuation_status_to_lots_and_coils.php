<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->string('valuation_status', 30)->default('valorisation_definitive')->after('unit_cost');
            $table->text('valuation_reason')->nullable()->after('valuation_status');
            $table->foreignId('valuation_responsible_id')->nullable()->after('valuation_reason')->constrained('users')->nullOnDelete();
        });
        Schema::table('coils', function (Blueprint $table) {
            $table->string('valuation_status', 30)->default('valorisation_definitive')->after('cost_per_kg');
            $table->text('valuation_reason')->nullable()->after('valuation_status');
            $table->foreignId('valuation_responsible_id')->nullable()->after('valuation_reason')->constrained('users')->nullOnDelete();
        });
        DB::table('stock_lots')->where('quantity', '>', 0)->where('unit_cost', '<=', 0)
            ->update(['valuation_status' => 'valorisation_manquante', 'valuation_reason' => 'Coût historique absent']);
        DB::table('coils')->where('remaining_weight', '>', 0)->where('cost_per_kg', '<=', 0)
            ->update(['valuation_status' => 'valorisation_manquante', 'valuation_reason' => 'Coût historique absent']);
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valuation_responsible_id');
            $table->dropColumn(['valuation_status', 'valuation_reason']);
        });
        Schema::table('coils', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valuation_responsible_id');
            $table->dropColumn(['valuation_status', 'valuation_reason']);
        });
    }
};
