<?php

/**
 * [Phase 2.7] a3:audit-production : propre en local, DÉTECTE les points
 * bloquants en production (détecteur prouvé, pas seulement le résultat).
 */

uses(\Tests\Concerns\RefreshDatabase::class);

it('audit-production : passe en environnement local', function () {
    $this->artisan('a3:audit-production')->assertExitCode(0);
});

it('audit-production : BLOQUE en production si APP_DEBUG=true', function () {
    config(['app.debug' => true]);
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('a3:audit-production')->assertExitCode(1);
});

it('audit-production : BLOQUE en production si maker-checker désactivé', function () {
    config(['app.debug' => false, 'security.maker_checker.enabled' => false, 'queue.default' => 'database', 'cache.default' => 'file', 'app.url' => 'https://erp.oametal.bf']);
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('a3:audit-production')->assertExitCode(1);
});
