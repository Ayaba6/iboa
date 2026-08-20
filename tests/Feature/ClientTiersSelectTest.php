<?php

/**
 * Un client ne se propose pas lui-même comme son propre tiers comptable.
 *
 * Les cinq champs « client facturé / payeur / groupe / risque / factor »
 * désignent un AUTRE client. `UpdateClientRequest` refuse déjà l'auto-référence
 * par `not_in`, et `ClientSelfReferenceTest` l'éprouve — mais l'écran d'édition
 * proposait quand même le client courant dans sa propre liste. L'utilisateur ne
 * découvrait donc l'interdit qu'après avoir enregistré.
 *
 * Le serveur reste la garde qui compte ; ce contrôle porte sur ce que l'écran
 * OFFRE, pour qu'il cesse de proposer ce que la validation rejette.
 */

use App\Models\Client;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ctsUtilisateur(): User
{
    $u = User::factory()->create(['email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

/**
 * Options réelles du select, hors placeholder.
 *
 * `toContain` étant VARIADIQUE en Pest, aucune assertion ci-dessous ne lui passe
 * de message : un second argument y serait pris pour une valeur supplémentaire à
 * trouver, et l'échec parlerait du message au lieu de la donnée.
 */
function ctsOptions(string $html, string $champ): array
{
    if (! preg_match('/<select\b(?=[^>]*\bname="'.preg_quote($champ, '/').'")[^>]*>(.*?)<\/select>/is', $html, $m)) {
        return [];
    }

    preg_match_all('/<option\b[^>]*value="(\d+)"/i', $m[1], $o);

    return array_map('intval', $o[1]);
}

$champs = ['client_facture_id', 'client_payeur_id', 'client_groupe_id', 'client_risque_id', 'factor_id'];

it('n’offre pas le client édité dans ses propres tiers comptables', function () use ($champs) {
    $edite = Client::factory()->create(['code' => 'CTS-EDITE', 'is_active' => true]);
    $autre = Client::factory()->create(['code' => 'CTS-AUTRE', 'is_active' => true]);

    $html = $this->actingAs(ctsUtilisateur())
        ->get(route('clients.edit', $edite))
        ->assertOk()
        ->getContent();

    foreach ($champs as $champ) {
        $options = ctsOptions($html, $champ);

        // Garde du test : une liste vide le rendrait vert sans rien prouver.
        expect($options)->not->toBeEmpty("{$champ} ne propose aucune option");

        expect($options)->not->toContain($edite->id);
        expect($options)->toContain($autre->id);
    }
});

it('offre tous les clients actifs à la création', function () use ($champs) {
    // À la création, aucun client n'est encore édité : rien n'est à exclure.
    $a = Client::factory()->create(['code' => 'CTS-A', 'is_active' => true]);
    $b = Client::factory()->create(['code' => 'CTS-B', 'is_active' => true]);

    $html = $this->actingAs(ctsUtilisateur())
        ->get(route('clients.create'))
        ->assertOk()
        ->getContent();

    foreach ($champs as $champ) {
        $options = ctsOptions($html, $champ);
        expect($options)->toContain($a->id)->toContain($b->id);
    }
});

it('n’offre pas un client inactif', function () use ($champs) {
    // La règle préexistante ne doit pas se perdre en chemin.
    $edite = Client::factory()->create(['code' => 'CTS-E2', 'is_active' => true]);
    $inactif = Client::factory()->create(['code' => 'CTS-INACTIF', 'is_active' => false]);
    $actif = Client::factory()->create(['code' => 'CTS-ACTIF', 'is_active' => true]);

    $html = $this->actingAs(ctsUtilisateur())
        ->get(route('clients.edit', $edite))
        ->assertOk()
        ->getContent();

    foreach ($champs as $champ) {
        $options = ctsOptions($html, $champ);
        expect($options)->not->toContain($inactif->id);
        expect($options)->toContain($actif->id);
    }
});

it('le serveur refuse toujours l’auto-référence, écran ou pas', function () use ($champs) {
    // Le select n'est qu'un confort. Une requête forgée l'ignore, et c'est la
    // validation qui doit tenir — d'où ce rappel ici.
    $client = Client::factory()->create(['code' => 'CTS-FORGE', 'is_active' => true]);

    foreach ($champs as $champ) {
        $this->actingAs(ctsUtilisateur())
            ->from(route('clients.edit', $client))
            ->put(route('clients.update', $client), [
                'code' => $client->code,
                'name' => $client->name,
                $champ => $client->id,
            ])
            ->assertSessionHasErrors($champ);
    }
});
