<?php

use App\Models\Client;
use App\Models\User;

/**
 * [Parité Sage X3] Les champs juridiques/fiscaux, risque crédit et tiers
 * comptables sont persistés et exposés par le modèle Client.
 */
it('persiste les champs Sage X3 du client (mass assignment autorisé)', function () {
    $parent = Client::create(['code' => 'CLI-GRP', 'name' => 'GROUPE MERE', 'is_active' => true]);

    $client = Client::create([
        'code'                 => 'CLI-X3',
        'name'                 => 'CIMENTS AFRIQUE',
        'is_active'            => true,
        // juridique / fiscal
        'forme_juridique'      => 'SARL',
        'regime_imposition'    => 'Régime normal',
        'no_agrement'          => 'AGR-2026-01',
        // risque crédit
        'code_risque'          => 'Bon',
        'garantie_montant'     => 5_000_000,
        'nature_garantie'      => 'Caution bancaire',
        'assurance_credit'     => 'SUNU Assurances',
        'rrr_montant'          => 100_000,
        'rrr_taux'             => 2.5,
        'reference_cadastrale' => 'CAD-OUA-042',
        // tiers comptables
        'client_facture_id'    => $parent->id,
        'client_groupe_id'     => $parent->id,
    ]);

    $fresh = $client->fresh();

    expect($fresh->forme_juridique)->toBe('SARL')
        ->and($fresh->regime_imposition)->toBe('Régime normal')
        ->and($fresh->no_agrement)->toBe('AGR-2026-01')
        ->and($fresh->code_risque)->toBe('Bon')
        ->and((float) $fresh->garantie_montant)->toBe(5000000.0)
        ->and((float) $fresh->rrr_taux)->toBe(2.5)
        ->and($fresh->reference_cadastrale)->toBe('CAD-OUA-042')
        ->and($fresh->client_facture_id)->toBe($parent->id);

    // Relations tiers résolues
    expect($fresh->clientFacture->name)->toBe('GROUPE MERE')
        ->and($fresh->clientGroupe->id)->toBe($parent->id);
});
