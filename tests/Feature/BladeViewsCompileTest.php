<?php

/**
 * [Régression @json] Garde-fou contre la classe de bug « @json(expr à 3+ virgules) »
 * (la directive découpe son expression sur les virgules → tableau/objet 4+ éléments
 * tronqué → PHP compilé invalide « Unclosed [ », crash 500 au rendu).
 *
 * Compile TOUTES les vues Blade et vérifie que le PHP produit est syntaxiquement
 * valide via token_get_all(TOKEN_PARSE) — en process, sans sous-processus.
 */

use Illuminate\Support\Facades\Blade;

it('compile toutes les vues Blade en PHP syntaxiquement valide', function () {
    $viewsPath = resource_path('views');
    $compiler  = Blade::getFacadeRoot();

    $errors = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

    foreach ($iterator as $file) {
        if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

        try {
            $php = $compiler->compileString(file_get_contents($file->getPathname()));
        } catch (\Throwable $e) {
            $errors[] = "$relative — compilation Blade : " . $e->getMessage();
            continue;
        }

        // Valide la syntaxe du PHP généré sans l'exécuter.
        try {
            token_get_all($php, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $errors[] = "$relative — PHP compilé invalide : " . $e->getMessage();
        }
    }

    expect($errors)->toBe([], "Vues Blade produisant du PHP invalide :\n" . implode("\n", $errors));
});
