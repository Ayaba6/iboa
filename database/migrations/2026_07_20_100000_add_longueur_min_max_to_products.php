<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [CDC §3 tôle bac] Longueurs mini/maxi fabricables (mètres). Contrôle des lignes de vente.
            if (! Schema::hasColumn('products', 'longueur_min')) {
                $table->decimal('longueur_min', 10, 3)->nullable()->after('longueur_standard');
            }
            if (! Schema::hasColumn('products', 'longueur_max')) {
                $table->decimal('longueur_max', 10, 3)->nullable()->after('longueur_min');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['longueur_min', 'longueur_max']);
        });
    }
};
