<?php

/**
 * [Phase 2.1 — webhooks] Sécurité avancée des webhooks Mobile Money :
 * signature absente/invalide, préfixe sha256=, rejeu exact neutre,
 * même référence à montant différent rejetée, course simultanée = une
 * seule transaction (contrainte unique).
 */

use App\Models\ApiIntegration;
use App\Models\Company;
use App\Models\ExternalTransaction;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\Queue;

uses(\Tests\Concerns\RefreshDatabase::class);

function whkSetup(): ApiIntegration
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    Company::firstOrCreate(['name' => 'WHK'], ['email' => 'whk@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    return ApiIntegration::create([
        'provider' => 'orange_money', 'slug' => 'om-test', 'name' => 'OM Test', 'type' => 'payment', 'is_active' => true,
        'webhook_secret' => 'secret-test-om', 'status' => 'connected',
    ]);
}

function whkSign(array $payload): string
{
    return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), 'secret-test-om');
}

it('rejette un webhook sans signature et un webhook à signature invalide', function () {
    whkSetup();
    Queue::fake();
    $payload = ['txnid' => 'OM-REF-1', 'status' => 'SUCCESS', 'amount' => 10000];

    $this->postJson('integrations/webhooks/orange-money', $payload)
        ->assertOk()->assertJson(['status' => 'rejected', 'reason' => 'signature']);

    $this->postJson('integrations/webhooks/orange-money', $payload, ['X-Orange-Signature' => 'mauvaise-signature'])
        ->assertOk()->assertJson(['status' => 'rejected']);

    expect(ExternalTransaction::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('accepte la signature avec préfixe sha256= même quand elle commence par un caractère de la liste ltrim', function () {
    whkSetup();
    Queue::fake();
    // Payload choisi pour produire une signature commençant par un caractère
    // parmi s/h/a/2/5/6 — l'ancien ltrim la tronquait (faux rejet).
    $payload = null;
    for ($i = 0; $i < 500; $i++) {
        $candidate = ['txnid' => 'OM-LT-' . $i, 'status' => 'SUCCESS', 'amount' => 5000];
        if (preg_match('/^[sha256=]/', whkSign($candidate))) {
            $payload = $candidate;
            break;
        }
    }
    expect($payload)->not->toBeNull();

    $this->postJson('integrations/webhooks/orange-money', $payload, ['X-Orange-Signature' => 'sha256=' . whkSign($payload)])
        ->assertOk()->assertJson(['status' => 'ok']);
    expect(ExternalTransaction::count())->toBe(1);
});

it('rejeu exact : une seule transaction, un seul job', function () {
    whkSetup();
    Queue::fake();
    $payload = ['txnid' => 'OM-REPLAY-1', 'status' => 'SUCCESS', 'amount' => 25000];
    $headers = ['X-Orange-Signature' => whkSign($payload)];

    $this->postJson('integrations/webhooks/orange-money', $payload, $headers)->assertOk();
    // Simule le traitement du 1er job (statut confirmé)
    ExternalTransaction::where('external_reference', 'OM-REPLAY-1')->update(['status' => 'confirmed']);

    // Rejeu exact de la même livraison
    $this->postJson('integrations/webhooks/orange-money', $payload, $headers)->assertOk();

    expect(ExternalTransaction::where('external_reference', 'OM-REPLAY-1')->count())->toBe(1);
    Queue::assertPushed(\App\Jobs\ProcessExternalPayment::class, 1);
});

it('rejette la même référence avec un montant différent (référence mutée)', function () {
    whkSetup();
    Queue::fake();
    $p1 = ['txnid' => 'OM-MUT-1', 'status' => 'PENDING', 'amount' => 10000];
    $this->postJson('integrations/webhooks/orange-money', $p1, ['X-Orange-Signature' => whkSign($p1)])->assertOk();

    // Même référence, montant gonflé — signé correctement (secret compromis ou
    // rejeu modifié côté opérateur) : le HMAC passe, la cohérence métier refuse.
    $p2 = ['txnid' => 'OM-MUT-1', 'status' => 'SUCCESS', 'amount' => 900000];
    $this->postJson('integrations/webhooks/orange-money', $p2, ['X-Orange-Signature' => whkSign($p2)])
        ->assertOk()->assertJson(['status' => 'rejected', 'reason' => 'amount_mismatch']);

    $tx = ExternalTransaction::where('external_reference', 'OM-MUT-1')->first();
    expect((int) $tx->amount)->toBe(10000)
        ->and($tx->status)->not->toBe('confirmed');
    Queue::assertNothingPushed();
});
