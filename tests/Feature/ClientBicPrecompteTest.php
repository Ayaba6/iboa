<?php

use App\Models\Client;

/**
 * [Précompte BIC] Le flag soumis_bic et son motif d'exemption sont persistés
 * et castés. Défaut = soumis (true).
 */
it('soumis au BIC par défaut', function () {
    $client = Client::create(['code' => 'CLI-BIC1', 'name' => 'CLIENT LAMBDA', 'is_active' => true]);
    expect($client->fresh()->soumis_bic)->toBeTrue();
});

it('exclut un client du BIC avec motif', function () {
    $client = Client::create([
        'code'                 => 'CLI-DGE',
        'name'                 => 'GRANDE ENTREPRISE',
        'is_active'            => true,
        'soumis_bic'           => false,
        'bic_exemption_reason' => 'Grande entreprise (DGE)',
    ]);

    $fresh = $client->fresh();
    expect($fresh->soumis_bic)->toBeFalse()
        ->and($fresh->bic_exemption_reason)->toBe('Grande entreprise (DGE)');
});
