<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C — mode de production de l'article fini :
 *   mts = Make To Stock (production pour stock, ex. fer à béton)
 *   mto = Make To Order (production à la commande, ex. tôle bac)
 * Pilote le déclenchement et la quantité des ordres de fabrication.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'production_mode')) {
            return;
        }
        Schema::table('products', function (Blueprint $table) {
            $table->string('production_mode', 3)->nullable()->after('is_semi_finished')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('production_mode');
        });
    }
};
