<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §Workflow — notifications complémentaires] Canal email optionnel :
 * chaque utilisateur choisit de recevoir (ou non) les notifications de
 * validation par email en plus de la notification interne ERP (obligatoire).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_by_email')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_by_email');
        });
    }
};
