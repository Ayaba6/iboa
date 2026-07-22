<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Chemins parallèles — argent caisse] Lie le bon de préparation payé au
 * comptoir à son encaissement central : l'argent du guichet devient visible
 * en trésorerie et en comptabilité, et confirmedReceipts() ne compte plus
 * ces BP séparément (l'acompte lié porte le montant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_preparations', function (Blueprint $table) {
            $table->foreignId('client_payment_id')->nullable()->after('payment_recorded_by')
                ->constrained('client_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bon_preparations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_payment_id');
        });
    }
};
