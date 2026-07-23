<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [SEC-PHASE2 §8] La migration d'origine appelait nullOnDelete() AVANT
 * constrained() : le modificateur était perdu et la FK créée SANS
 * ON DELETE SET NULL — la suppression d'un compte était bloquée dès que le
 * journal le référençait. Le journal doit survivre à la suppression du
 * compte (user_name reste renseigné), pas l'empêcher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
