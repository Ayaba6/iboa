<?php

/**
 * [VEN Crédit client] Historique des décisions de crédit + application au client.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditDecision;
use App\Services\CreditDecisionService;

uses(\Tests\Concerns\RefreshDatabase::class);

function creditClient(): Client
{
    $co = Company::firstOrCreate(['name' => 'CRED Co'], ['email' => 'cred@iboa.test']);

    return Client::factory()->create(['name' => 'Client X', 'credit_limit' => 1000000, 'is_blocked' => false]);
}

it('journalise un blocage et bloque le client', function () {
    $client = creditClient();
    app(CreditDecisionService::class)->record($client, ['type' => 'blocage', 'reason' => 'Impayés répétés']);

    $client->refresh();
    expect((bool) $client->is_blocked)->toBeTrue();
    expect($client->blocked_reason)->toBe('Impayés répétés');
    $d = CreditDecision::where('client_id', $client->id)->first();
    expect($d->type)->toBe('blocage');
});

it('journalise un relèvement de plafond et l’applique', function () {
    $client = creditClient();
    app(CreditDecisionService::class)->record($client, ['type' => 'relevement_plafond', 'new_limit' => 1500000, 'reason' => 'Bon historique']);

    $client->refresh();
    expect((int) $client->credit_limit)->toBe(1500000);
    $d = CreditDecision::where('client_id', $client->id)->first();
    expect((float) $d->previous_limit)->toBe(1000000.0);
    expect((float) $d->new_limit)->toBe(1500000.0);
});

it('journalise une dérogation sans modifier le client', function () {
    $client = creditClient();
    app(CreditDecisionService::class)->record($client, ['type' => 'derogation', 'amount' => 250000, 'reason' => 'Commande urgente']);

    $client->refresh();
    expect((bool) $client->is_blocked)->toBeFalse();
    expect((int) $client->credit_limit)->toBe(1000000); // inchangé
    $d = CreditDecision::where('client_id', $client->id)->first();
    expect((float) $d->amount)->toBe(250000.0);
});

it('débloque un client précédemment bloqué', function () {
    $client = creditClient();
    $svc = app(CreditDecisionService::class);
    $svc->record($client, ['type' => 'blocage', 'reason' => 'x']);
    $svc->record($client->fresh(), ['type' => 'deblocage', 'reason' => 'Régularisé']);

    expect((bool) $client->fresh()->is_blocked)->toBeFalse();
    expect(CreditDecision::where('client_id', $client->id)->count())->toBe(2);
});
