<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRO-08] Optimisation 2D (refente largeur) : bandes par bobine, rendement
 * largeur et chute de refente, en complément de l'optimisation 1D en longueur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutting_optimizations', function (Blueprint $table) {
            if (! Schema::hasColumn('cutting_optimizations', 'strips_per_coil')) {
                $table->unsignedSmallInteger('strips_per_coil')->default(0)->after('coils_used');
            }
            if (! Schema::hasColumn('cutting_optimizations', 'width_yield')) {
                $table->decimal('width_yield', 5, 2)->default(0)->after('strips_per_coil');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cutting_optimizations', function (Blueprint $table) {
            foreach (['strips_per_coil', 'width_yield'] as $c) {
                if (Schema::hasColumn('cutting_optimizations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
