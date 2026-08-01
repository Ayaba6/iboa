<?php

/**
 * [Suite] Le moteur annoncé doit être le moteur employé.
 *
 * `php artisan test --env=testing.mysql` a servi pendant toute une session de
 * travail à produire des lignes « parité MySQL ✓ ». Cette commande tourne en
 * réalité sur SQLite : `phpunit.xml` impose `DB_CONNECTION=sqlite` avec
 * `force="true"`, et le drapeau `--env` ne désigne pas un fichier phpunit — il
 * ne pouvait donc rien y changer. `.env.testing.mysql` n'a jamais existé.
 *
 * L'invocation réelle du projet est `pest -c phpunit.mysql.xml`, déclarée dans
 * `composer.json` sous `test:mysql-full`.
 *
 * Le rapport paraissait vert et ne prouvait rien. Aucun test ne pouvait le
 * signaler, puisqu'un test qui passe sur SQLite passe aussi quand on croit être
 * sur MySQL. D'où ce fichier : il rend l'écart VISIBLE dans la sortie, à chaque
 * exécution, sans jamais faire échouer une course légitime.
 */

use Illuminate\Support\Facades\DB;

it('ouvre le moteur que la configuration annonce', function () {
    // Chaque configuration phpunit déclare le moteur qu'elle PRÉTEND utiliser.
    // La première version de ce test lisait `__PHPUNIT_CONFIGURATION_FILE`, que
    // PHPUnit n'expose pas : le test se sautait, et un garde qui se saute ne
    // garde rien — précisément le défaut qu'il était censé traiter.
    $annonce = env('DB_ENGINE_EXPECTED');

    expect($annonce)->not->toBeNull(
        'DB_ENGINE_EXPECTED absent : la configuration phpunit ne déclare aucun moteur, '
        .'le rapport ne peut donc rien attester.'
    );

    expect(DB::connection()->getDriverName())->toBe(
        $annonce,
        sprintf(
            'Configuration annoncée « %s », moteur réellement ouvert « %s ». '
            .'La suite MySQL se lance avec : pest -c phpunit.mysql.xml',
            $annonce,
            DB::connection()->getDriverName()
        )
    );
});

it('nomme le moteur réellement utilisé dans la sortie', function () {
    $driver = DB::connection()->getDriverName();
    $base = DB::connection()->getDatabaseName();

    // Trace lisible dans chaque rapport : le lecteur n'a plus à déduire le
    // moteur de la commande qu'il croit avoir tapée.
    expect(in_array($driver, ['mysql', 'sqlite', 'mariadb'], true))->toBeTrue(
        "Moteur inattendu : {$driver} ({$base})"
    );

    // Une base MySQL de test doit se reconnaître comme telle. Le jour où la
    // configuration pointerait la base de développement, ce test le dirait
    // avant que la suite ne la vide.
    if ($driver !== 'sqlite') {
        expect(preg_match('/test|testing|qa|ci/i', (string) $base))->toBe(
            1,
            "La base de test « {$base} » ne porte aucun marqueur de test : "
            .'refuser de dérouler une suite destructive dessus.'
        );
    }
});

it('déclare une configuration phpunit dédiée à MySQL', function () {
    // Le garde ne vaut que si l'alternative existe réellement.
    expect(file_exists(base_path('phpunit.mysql.xml')))->toBeTrue();

    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $scripts = $composer['scripts'] ?? [];

    // La bonne commande doit rester découvrable depuis composer.json, pour que
    // personne n'ait à la deviner — c'est ainsi qu'on la manque.
    $mysqlScripts = collect($scripts)->filter(
        fn ($v) => is_string($v) && str_contains($v, 'phpunit.mysql.xml')
    );

    expect($mysqlScripts)->not->toBeEmpty();
});
