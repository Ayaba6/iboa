<?php

/**
 * [Phase 2.1 §9] a3:audit-schema : propre sur base migrée, ET détecte
 * réellement une dérive (preuve du détecteur, pas seulement du résultat).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Tests\Concerns\RefreshDatabase::class);

it('audit-schema : propre sur une base fraîchement migrée', function () {
    $this->artisan('a3:audit-schema')->assertExitCode(0);
});

it('audit-schema : détecte une table d\'infrastructure manquante', function () {
    Schema::drop('personal_access_tokens');
    $this->artisan('a3:audit-schema')->assertExitCode(1);
});

it('audit-schema : détecte une migration fantôme enregistrée sans fichier', function () {
    DB::table('migrations')->insert(['migration' => '2099_01_01_000000_ghost_migration', 'batch' => 999]);
    $this->artisan('a3:audit-schema')->assertExitCode(1);
});
