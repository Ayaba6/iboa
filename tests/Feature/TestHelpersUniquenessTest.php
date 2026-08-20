<?php

/**
 * [Suite] Deux fichiers de test ne peuvent pas déclarer la même fonction globale.
 *
 * Pest charge TOUS les fichiers de test dans un seul processus PHP. Deux
 * `function xyz()` de même nom produisent une erreur fatale « Cannot redeclare »
 * qui abat la suite entière — et le fichier fautif passe pourtant en isolation,
 * puisqu'il est alors seul en mémoire.
 *
 * C'est arrivé deux fois dans la même journée (`stockCompany`, puis `kpiAdmin`).
 * La vigilance n'a pas suffi ; ce test la remplace. Il échoue au moment de
 * l'écriture du doublon, pas trois quarts d'heure plus tard au bout d'une course
 * complète, et il nomme les deux fichiers en cause.
 */

it('ne déclare aucune fonction d\'aide en double dans la suite', function () {
    $racine = base_path('tests');
    $declarations = [];

    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine));
    foreach ($fichiers as $fichier) {
        if ($fichier->isDir() || $fichier->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($fichier->getPathname());
        // Fonctions globales seulement : celles déclarées en début de ligne.
        // Les méthodes de classe sont indentées et portent une visibilité.
        preg_match_all('/^\s*function\s+([A-Za-z_]\w*)\s*\(/m', $source, $trouvees);

        foreach ($trouvees[1] as $nom) {
            $declarations[$nom][] = str_replace([$racine, DIRECTORY_SEPARATOR], ['', '/'], $fichier->getPathname());
        }
    }

    $doublons = array_filter($declarations, fn ($f) => count($f) > 1);

    $message = collect($doublons)
        ->map(fn ($f, $nom) => sprintf('%s déclarée dans : %s', $nom, implode(' et ', $f)))
        ->implode("\n");

    expect($doublons)->toBe([], "Fonctions de test déclarées plusieurs fois :\n".$message);

    // Contrôle en miroir : si le balayage ne trouvait rien du tout, le test
    // passerait sans rien prouver.
    expect(count($declarations))->toBeGreaterThan(10);
});
