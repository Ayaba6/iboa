<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caractéristiques techniques des articles (référentiel SAGE X3) :
 * épaisseur (mm), métrage (m), densité — colonnes du fichier d'import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('thickness', 8, 2)->nullable()->after('net_weight_per_us')->comment('Épaisseur en mm');
            $table->decimal('linear_meters', 10, 2)->nullable()->after('thickness')->comment('Métrage en mètres');
            $table->decimal('density', 8, 3)->nullable()->after('linear_meters')->comment('Densité matière');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['thickness', 'linear_meters', 'density']);
        });
    }
};
