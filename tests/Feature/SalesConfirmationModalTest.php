<?php

it('utilise exclusivement la modale applicative pour les confirmations ventes', function () {
    $views = collect(glob(resource_path('views/ventes/**/*.blade.php')))
        ->merge(glob(resource_path('views/ventes/*.blade.php')));

    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        $source = file_get_contents($view);
        expect($source)
            ->not->toContain('window.confirm(')
            ->not->toContain('return confirm(');
    }

    $modal = file_get_contents(resource_path('views/components/confirm-modal.blade.php'));
    expect($modal)
        ->toContain('data-confirm')
        ->not->toContain('window.confirm(');
});