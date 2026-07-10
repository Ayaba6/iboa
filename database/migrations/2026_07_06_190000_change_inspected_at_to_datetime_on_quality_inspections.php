<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** [Maquette Contrôle qualité] Date du contrôle avec heure (06/07/2026 10:30). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->dateTime('inspected_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->date('inspected_at')->nullable()->change();
        });
    }
};
