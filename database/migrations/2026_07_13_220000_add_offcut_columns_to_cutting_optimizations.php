<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRO-08] Valorisation des chutes : distingue la chute réutilisable (>= seuil
 * min_reusable_offcut) du rebut réel, en complément de estimated_waste_m.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutting_optimizations', function (Blueprint $table) {
            if (! Schema::hasColumn('cutting_optimizations', 'reusable_offcut_m')) {
                $table->decimal('reusable_offcut_m', 12, 2)->default(0)->after('estimated_waste_m');
            }
            if (! Schema::hasColumn('cutting_optimizations', 'scrap_m')) {
                $table->decimal('scrap_m', 12, 2)->default(0)->after('reusable_offcut_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cutting_optimizations', function (Blueprint $table) {
            foreach (['reusable_offcut_m', 'scrap_m'] as $c) {
                if (Schema::hasColumn('cutting_optimizations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
