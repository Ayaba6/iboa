<?php

/**
 * [Clients] Un client ne peut pas se désigner lui-même comme tiers comptable.
 *
 * La règle écrite était `different:id`. En Laravel, `different:autre` compare le
 * champ à un AUTRE CHAMP DE LA REQUÊTE nommé `autre` — pas à l'identifiant du
 * modèle. Le formulaire ne poste aucun champ `id`, celui-ci venant de l'URL :
 * la contrainte passait donc toujours. L'interdiction n'existait que dans le
 * commentaire qui la précédait.
 *
 * Conséquence : un client pouvait se déclarer son propre client facturé, payeur,
 * groupe, risque ou factor — une boucle dans la chaîne de facturation et de
 * recouvrement.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Http\Requests\Client\UpdateClientRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/** Instancie les règles de la requête pour un client donné, comme le ferait le routeur. */
function reglesClient(Client $client): array
{
    $req = new UpdateClientRequest();
    $req->setRouteResolver(function () use ($client) {
        $route = new RoutingRoute('PUT', 'gestion/clients/{client}', []);
        $route->bind(new Request());
        $route->setParameter('client', $client);

        return $route;
    });

    return $req->rules();
}

it('refuse qu\'un client se référence lui-même', function (string $champ) {
    Company::firstOrCreate(['name' => 'REF'], ['email' => 'ref@ref.io']);
    $client = Client::factory()->create();

    $v = Validator::make(
        ['name' => $client->name, 'type' => 'entreprise', $champ => $client->id],
        reglesClient($client)
    );

    expect($v->passes())->toBeFalse();
    expect($v->errors()->has($champ))->toBeTrue();
})->with([
    'client_facture_id', 'client_payeur_id', 'client_groupe_id',
    'client_risque_id', 'factor_id',
]);

it('accepte qu\'un client référence un AUTRE client', function (string $champ) {
    Company::firstOrCreate(['name' => 'REF'], ['email' => 'ref@ref.io']);
    $client = Client::factory()->create();
    $autre  = Client::factory()->create();

    // Contrôle en miroir : sans lui, une règle qui refuserait TOUTE valeur
    // passerait le test précédent sans rien prouver.
    $v = Validator::make(
        ['name' => $client->name, 'type' => 'entreprise', $champ => $autre->id],
        reglesClient($client)
    );

    expect($v->errors()->has($champ))->toBeFalse();
})->with([
    'client_facture_id', 'client_payeur_id', 'client_groupe_id',
    'client_risque_id', 'factor_id',
]);

it('refuse un tiers comptable inexistant', function () {
    Company::firstOrCreate(['name' => 'REF'], ['email' => 'ref@ref.io']);
    $client = Client::factory()->create();

    $v = Validator::make(
        ['name' => $client->name, 'type' => 'entreprise', 'client_payeur_id' => 999999],
        reglesClient($client)
    );

    expect($v->errors()->has('client_payeur_id'))->toBeTrue();
});
