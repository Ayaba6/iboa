<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Machine : Création complète ».
 * Identification technique : fabricant, modèle, n° de série, site/atelier,
 * date de mise en service et puissance électrique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            if (! Schema::hasColumn('production_machines', 'manufacturer')) {
                $table->string('manufacturer', 80)->nullable()->after('type');
            }
            if (! Schema::hasColumn('production_machines', 'model')) {
                $table->string('model', 80)->nullable()->after('manufacturer');
            }
            if (! Schema::hasColumn('production_machines', 'serial_number')) {
                $table->string('serial_number', 80)->nullable()->after('model');
            }
            if (! Schema::hasColumn('production_machines', 'site')) {
                $table->string('site', 20)->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('production_machines', 'atelier')) {
                $table->string('atelier', 60)->nullable()->after('site');
            }
            if (! Schema::hasColumn('production_machines', 'commissioned_at')) {
                $table->date('commissioned_at')->nullable()->after('atelier');
            }
            if (! Schema::hasColumn('production_machines', 'power_kw')) {
                $table->decimal('power_kw', 8, 2)->nullable()->after('commissioned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            $table->dropColumn([
                'manufacturer', 'model', 'serial_number', 'site',
                'atelier', 'commissioned_at', 'power_kw',
            ]);
        });
    }
};
