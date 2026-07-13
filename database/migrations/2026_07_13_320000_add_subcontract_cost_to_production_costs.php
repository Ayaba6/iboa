<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRO-10] Coût de revient : ajoute le coût de sous-traitance (opérations
 * externalisées) aux composantes du coût de production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_costs', 'subcontract_cost')) {
                $table->integer('subcontract_cost')->default(0)->after('overhead_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            if (Schema::hasColumn('production_costs', 'subcontract_cost')) {
                $table->dropColumn('subcontract_cost');
            }
        });
    }
};
