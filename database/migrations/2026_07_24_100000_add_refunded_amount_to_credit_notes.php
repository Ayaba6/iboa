<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [R2 §2] Invariant de l'avoir : total_ttc = applied_amount + refunded_amount
 * + remaining_credit. La colonne refunded_amount matérialise la part remboursée
 * en trésorerie (le remboursement PARTIEL et les remboursements successifs
 * doivent être traçables séparément de l'imputation sur facture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->decimal('refunded_amount', 15, 0)->default(0)->after('applied_amount');
        });
        // Rétro-cohérence : un avoir déjà « rembourse » avait tout remboursé.
        DB::table('credit_notes')->where('status', 'rembourse')
            ->update(['refunded_amount' => DB::raw('total_ttc - applied_amount')]);
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
