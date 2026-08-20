<?php

/**
 * [i18n] Le paginateur parle français.
 *
 * `APP_LOCALE` et `APP_FALLBACK_LOCALE` valaient déjà « fr », mais `lang/fr/` ne
 * contenait que `validation.php` et `lang/fr.json` n'existait pas. Sans
 * traduction à charger, Laravel rend la CLÉ telle quelle : « Showing 1 to 20 of
 * 27 results » s'affichait sur TOUTES les listes paginées de l'ERP — articles,
 * commandes, factures, ordres de fabrication, clients.
 *
 * Une locale correctement réglée ne suffit donc pas : encore faut-il que les
 * fichiers existent. C'est ce que ces tests vérifient, plutôt que la seule
 * valeur de configuration.
 */

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function pageur(int $total = 27, int $parPage = 20, int $page = 1): LengthAwarePaginator
{
    $paginator = new LengthAwarePaginator(
        range(1, min($parPage, $total)), $total, $parPage, $page, ['path' => '/articles']
    );

    return $paginator;
}

it('traduit le texte de comptage', function () {
    app()->setLocale('fr');

    $texte = sprintf('%s 1 %s 20 %s 27 %s', __('Showing'), __('to'), __('of'), __('results'));

    expect($texte)->toBe('Affichage de 1 à 20 sur 27 résultats');
});

it('rend le comptage en français dans le paginateur réel', function () {
    app()->setLocale('fr');

    $html = pageur()->links()->toHtml();
    $texte = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));

    expect($texte)->toContain('Affichage de')
        ->and($texte)->toContain('résultats')
        ->and($texte)->not->toContain('Showing')
        ->and($texte)->not->toContain('results');
});

it('traduit les libellés précédent et suivant', function () {
    app()->setLocale('fr');

    expect(__('pagination.previous'))->toContain('Précédent')
        ->and(__('pagination.next'))->toContain('Suivant');
});

it('n’écrit pas les guillemets en entités HTML', function () {
    // La vue utilise ces clés DEUX fois : en HTML brut pour les boutons mobiles
    // (`{!! !!}`) et dans un `aria-label` pour les flèches (`{{ }}`). Avec
    // « &laquo; », le second n'est pas décodé et un lecteur d'écran annoncerait
    // littéralement « &laquo; Précédent ».
    app()->setLocale('fr');

    expect(__('pagination.previous'))->not->toContain('&laquo;')
        ->and(__('pagination.previous'))->not->toContain('&amp;')
        ->and(__('pagination.next'))->not->toContain('&raquo;');
});

it('nomme la navigation pour les lecteurs d’écran', function () {
    app()->setLocale('fr');

    expect(__('Pagination Navigation'))->toBe('Navigation par pages');
});

it('couvre toutes les clés que la vue du paginateur réclame', function () {
    // Garde structurelle : si une future version de Laravel introduit une clé
    // supplémentaire, ce test la signale au lieu de la laisser filer en anglais.
    app()->setLocale('fr');

    $vue = base_path('vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php');
    expect(file_exists($vue))->toBeTrue();

    preg_match_all("/__\('([^']+)'\)/", file_get_contents($vue), $m);

    foreach (array_unique($m[1]) as $cle) {
        // Une clé non traduite se rend elle-même : c'est le symptôme d'origine.
        expect(__($cle))->not->toBe($cle, "La clé « {$cle} » n'est pas traduite en français.");
    }
});
