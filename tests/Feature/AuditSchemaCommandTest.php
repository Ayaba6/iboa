<?php

/**
 * [Phase 2.1 §9] a3:audit-schema : propre sur base migree, et detecte
 * reellement une derive (preuve du detecteur, pas seulement du resultat).
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Tests\Concerns\RefreshDatabase::class);

it('audit-schema : propre sur une base fraichement migree', function () {
    $this->artisan('a3:audit-schema')->assertExitCode(0);
});

it('audit-schema : detecte une table d\'infrastructure manquante', function () {
    Schema::drop('personal_access_tokens');

    try {
        $this->artisan('a3:audit-schema')->assertExitCode(1);
    } finally {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }
});

it('audit-schema : detecte une migration fantome enregistree sans fichier', function () {
    DB::table('migrations')->insert(['migration' => '2099_01_01_000000_ghost_migration', 'batch' => 999]);
    $this->artisan('a3:audit-schema')->assertExitCode(1);
});