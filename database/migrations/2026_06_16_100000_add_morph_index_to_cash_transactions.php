<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur la relation polymorphe (reference_type, reference_id) de
 * cash_transactions — la seule colonne *_id métier non indexée détectée
 * lors de l'audit des tables. Accélère « transactions d'une source donnée ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_transactions', 'reference_id')) {
            return;
        }
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id'], 'cash_transactions_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex('cash_transactions_reference_index');
        });
    }
};
