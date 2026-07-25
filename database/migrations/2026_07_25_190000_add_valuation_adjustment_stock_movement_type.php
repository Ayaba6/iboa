<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY type ENUM('entree','sortie','transfert','ajustement','inventaire','retour_client','retour_fournisseur','valuation_adjustment') NOT NULL");

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_movements')->where('type', 'valuation_adjustment')->update(['type' => 'ajustement']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY type ENUM('entree','sortie','transfert','ajustement','inventaire','retour_client','retour_fournisseur') NOT NULL");
        }
    }
};
