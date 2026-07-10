<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('financial_authorization', 20)->nullable()->after('status');
            $table->timestamp('financial_authorized_at')->nullable()->after('financial_authorization');
            $table->foreignId('financial_authorized_by')->nullable()->after('financial_authorized_at')->constrained('users')->nullOnDelete();
            $table->string('financial_notes', 500)->nullable()->after('financial_authorized_by');
            $table->string('payment_mode', 30)->nullable()->after('financial_notes'); // comptant/acompte/credit
            $table->decimal('payment_rate', 5, 2)->nullable()->after('payment_mode');  // % réglé
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropForeign(['financial_authorized_by']);
            $table->dropColumn(['financial_authorization','financial_authorized_at','financial_authorized_by','financial_notes','payment_mode','payment_rate']);
        });
    }
};
