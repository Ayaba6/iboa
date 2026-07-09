<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [FIX mouvement bloqué] Les lignes d'un mouvement manuel BLOQUÉ doivent être
// conservées sans être appliquées au stock — elles ne le seront qu'au déblocage.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_stock_movements', function (Blueprint $table) {
            $table->json('lines')->nullable()->after('is_blocked');
            $table->timestamp('unblocked_at')->nullable()->after('lines');
            $table->foreignId('unblocked_by')->nullable()->after('unblocked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manual_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unblocked_by');
            $table->dropColumn(['lines', 'unblocked_at']);
        });
    }
};
