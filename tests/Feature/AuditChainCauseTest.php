<?php

/**
 * [Journal d'audit — reproduction des 3 causes de rupture] Sur base de TEST
 * isolée uniquement. Prouve que `verifyChain()` distingue :
 *   A. altération réelle d'une ligne scellée (row_hash_mismatch) ;
 *   B. défaut de génération / divergence de payload — hash calculé sur un user_id
 *      différent de celui stocké (row_hash_mismatch) — cas observé à l'entrée 394 ;
 *   C. chaînage rompu (prev_hash ≠ row_hash précédent) — typique d'un reseed /
 *      import partiel (prev_hash_mismatch).
 * Aucune « réparation » : on constate la détection, on ne réécrit rien.
 */

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

function sealOne(string $action = 'test.seal'): AuditLog
{
    app(AuditService::class)->log($action, null, [], ['k' => 'v']);

    return AuditLog::orderByDesc('id')->first();
}

it('chaîne intacte : verifyChain ne signale aucune rupture', function () {
    sealOne();
    sealOne();
    sealOne();
    expect(app(AuditService::class)->verifyChain())->toBe([]);
});

it('cas A — altération réelle d\'une ligne scellée est détectée', function () {
    sealOne();
    $log = sealOne();
    sealOne();

    // Altération APRÈS scellement, sans recalcul du hash (falsification).
    $log->updateQuietly(['new_values' => ['k' => 'ALTERED']]);

    expect(app(AuditService::class)->verifyChain())->toContain($log->id);
});

it('cas B — hash calculé sur un user_id différent de celui stocké (défaut de génération)', function () {
    $prev = sealOne(); // pour un prev_hash valide

    // Ligne dont le row_hash est calculé avec user_id=42 mais STOCKÉE avec user_id NULL
    // (reproduit l'entrée 394 : auth.login, user_id NULL, hash divergent).
    $rowHashSurAutrePayload = AuditService::computeRowHash([
        'user_id' => 42, 'action' => 'auth.login', 'model_type' => User::class, 'model_id' => 22,
        'old_values' => null, 'new_values' => null, 'ip_address' => '127.0.0.1',
    ], $prev->row_hash);

    $id = DB::table('audit_logs')->insertGetId([
        'user_id' => null, // ← divergence : NULL au lieu de 42
        'user_name' => 'Système', 'action' => 'auth.login',
        'model_type' => User::class, 'model_id' => 22,
        'old_values' => null, 'new_values' => null,
        'ip_address' => '127.0.0.1', 'prev_hash' => $prev->row_hash,
        'row_hash' => $rowHashSurAutrePayload,
        'created_at' => now(),
    ]);

    $broken = app(AuditService::class)->verifyChain();
    expect($broken)->toContain($id);
});

it('cas C — chaînage rompu (prev_hash incohérent, typique reseed/import)', function () {
    $first = sealOne();

    // Ligne correctement hachée sur elle-même MAIS avec un prev_hash faux
    // (ne pointe pas vers le row_hash de la ligne précédente).
    $fauxPrev = str_repeat('a', 64);
    $rowHash = AuditService::computeRowHash([
        'user_id' => null, 'action' => 'test.reseed', 'model_type' => null, 'model_id' => null,
        'old_values' => null, 'new_values' => null, 'ip_address' => null,
    ], $fauxPrev);

    $id = DB::table('audit_logs')->insertGetId([
        'user_id' => null, 'user_name' => 'x', 'action' => 'test.reseed',
        'model_type' => null, 'model_id' => null, 'old_values' => null, 'new_values' => null,
        'ip_address' => null, 'prev_hash' => $fauxPrev, 'row_hash' => $rowHash,
        'created_at' => now(),
    ]);

    // Rupture de chaînage prev→prev détectée (le prev_hash ≠ row_hash de $first).
    expect(app(AuditService::class)->verifyChain())->toContain($id);
});
