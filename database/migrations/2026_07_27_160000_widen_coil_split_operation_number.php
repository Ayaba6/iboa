<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Division] Le numéro d'opération « DIV-<référence bobine>-<horodatage> » dépasse
 * 40 caractères dès que la référence de bobine est longue : MySQL rejette
 * (SQLSTATE 22001 « Data too long »), SQLite tronque silencieusement.
 * Colonne élargie à 80 ; le service borne également la référence.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coil_split_operations')) {
            Schema::table('coil_split_operations', function (Blueprint $table) {
                $table->string('number', 80)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coil_split_operations')) {
            Schema::table('coil_split_operations', function (Blueprint $table) {
                $table->string('number', 40)->change();
            });
        }
    }
};
