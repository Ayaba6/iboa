<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Poste de charge » : type (machine / ligne / poste),
 * atelier de rattachement et site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('name');
            $table->string('atelier', 60)->nullable()->after('type');
            $table->string('site', 20)->nullable()->after('atelier');
        });
    }

    public function down(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropColumn(['type', 'atelier', 'site']);
        });
    }
};
