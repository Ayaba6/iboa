<?php

/**
 * [PRO-08] Valorisation des chutes de découpe : réutilisable vs rebut.
 */

use App\Modules\Production\Services\CuttingOptimizerService;

it('classe le reste de chaque barre en réutilisable ou rebut selon le seuil', function () {
    $svc = new CuttingOptimizerService();
    // 3 pièces de 5 m dans des barres de 12 m, sans kerf.
    // FFD → Barre 1 : [5,5] reste 2 ; Barre 2 : [5] reste 7.
    $plan = $svc->optimize(12, 0, [['length' => 5, 'quantity' => 3]], minReusableOffcut: 6);

    expect($plan['bars_count'])->toBe(2);
    expect($plan['used'])->toBe(15.0);
    expect($plan['waste'])->toBe(9.0);
    expect($plan['reusable_offcut'])->toBe(7.0); // barre 2 : reste 7 >= 6
    expect($plan['scrap'])->toBe(2.0);           // barre 1 : reste 2 < 6

    $types = array_column($plan['bars'], 'offcut_type');
    expect($types)->toContain('reutilisable')->toContain('rebut');
});

it('compte tout en rebut quand la valorisation est désactivée (seuil 0)', function () {
    $svc = new CuttingOptimizerService();
    $plan = $svc->optimize(12, 0, [['length' => 5, 'quantity' => 3]]); // seuil 0 par défaut

    expect($plan['reusable_offcut'])->toBe(0.0);
    expect($plan['scrap'])->toBe(9.0);
    expect($plan['scrap'])->toBe($plan['waste']);
});

it('n’altère pas le rendement matière', function () {
    $svc = new CuttingOptimizerService();
    $plan = $svc->optimize(12, 0, [['length' => 5, 'quantity' => 3]], minReusableOffcut: 6);
    // 15 utilisés / 24 stock → 62,5 %
    expect($plan['yield'])->toBe(62.5);
});
