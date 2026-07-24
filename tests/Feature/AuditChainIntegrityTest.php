<?php

/**
 * [R2 §6] La chaîne du journal d'audit : le scellement est write-time sur la
 * ligne créée (jamais de recalcul rétroactif) ; une rupture est SIGNALÉE, pas
 * réparée ; verifyChain() est en LECTURE SEULE.
 */

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

it('verifyChain est en lecture seule : ne modifie aucune ligne, même sur rupture', function () {
    $svc = app(AuditService::class);
    $svc->log('r2.a'); $svc->log('r2.b'); $svc->log('r2.c');

    // Empreinte de TOUS les row_hash avant vérification
    $avant = AuditLog::orderBy('id')->pluck('row_hash', 'id')->toArray();
    $svc->verifyChain();
    $apres = AuditLog::orderBy('id')->pluck('row_hash', 'id')->toArray();
    expect($apres)->toBe($avant); // aucune écriture

    // Altération SQL directe d'une valeur métier
    DB::table('audit_logs')->where('action', 'r2.b')->update(['action' => 'r2.b.falsifie']);
    $broken = $svc->verifyChain();
    expect($broken)->not->toBe([]); // rupture DÉTECTÉE

    // La vérification n'a RIEN réparé : la ligne falsifiée reste falsifiée
    expect(AuditLog::where('action', 'r2.b.falsifie')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'r2.b')->exists())->toBeFalse();
});

it('a3:audit-security signale la rupture en échec critique (exit 1), sans la réparer', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $svc = app(AuditService::class);
    $svc->log('r2.x'); $svc->log('r2.y');
    DB::table('audit_logs')->where('action', 'r2.y')->update(['ip_address' => '9.9.9.9']);

    $this->artisan('a3:audit-security')->assertExitCode(1);
    // Toujours rompue après l'audit (aucune réparation silencieuse)
    expect(app(AuditService::class)->verifyChain())->not->toBe([]);
});

it('le scellement ne touche que la ligne créée : les hash antérieurs sont immuables', function () {
    $svc = app(AuditService::class);
    $svc->log('r2.1');
    $hash1 = AuditLog::where('action', 'r2.1')->value('row_hash');

    // Créer d'autres entrées ne recalcule JAMAIS le hash de la première
    $svc->log('r2.2'); $svc->log('r2.3');
    expect(AuditLog::where('action', 'r2.1')->value('row_hash'))->toBe($hash1);
});
