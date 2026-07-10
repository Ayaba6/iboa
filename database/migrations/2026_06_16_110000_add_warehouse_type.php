<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D — typage des dépôts : achat / matière première / production /
 * produit fini / vente. Permet de cibler les flux (réception, allocation,
 * entrée PF, livraison) sur les bons dépôts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('warehouses', 'type')) {
            return;
        }
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type', 30)->nullable()->after('code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
