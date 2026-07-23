<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [SEC-PHASE2 §9] Chaînage cryptographique du journal d'audit :
 * row_hash = SHA-256(prev_hash | user_id | action | model | valeurs | date).
 * Toute suppression ou modification d'une entrée casse la chaîne — détectée
 * par la vérification d'intégrité de a3:audit-security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('prev_hash', 64)->nullable()->after('url');
            $table->string('row_hash', 64)->nullable()->after('prev_hash')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['prev_hash', 'row_hash']);
        });
    }
};
