<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Correctif] commercial_zone / client_group créés par la migration
// 2026_07_07_100000 doublonnaient zone_commerciale / groupe_client
// déjà présents (parité SAGE client). Suppression des doublons vides.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['commercial_zone', 'client_group']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_group', 60)->nullable();
            $table->string('commercial_zone', 60)->nullable();
        });
    }
};
