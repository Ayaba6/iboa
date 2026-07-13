<?php

/**
 * [PRO-08] Optimisation 2D — refente largeur (bandes par bobine) combinée au 1D longueur.
 */

use App\Modules\Production\Services\CuttingOptimizerService;

it('calcule le nombre de bandes de refente par bobine', function () {
    $svc = new CuttingOptimizerService();

    expect($svc->stripsPerCoil(1250, 600))->toBe(2);
    expect($svc->stripsPerCoil(1200, 400))->toBe(3);
    expect($svc->stripsPerCoil(1250, 600, slitKerf: 10))->toBe(2); // (1250+10)/(610)=2,06 → 2
    expect($svc->stripsPerCoil(1250, 0))->toBe(0);                 // largeur utile invalide
});

it('combine refente largeur et découpe longueur', function () {
    $svc = new CuttingOptimizerService();
    // Bobine 12 m × 1250 mm, bande utile 600 mm ; 3 pièces de 5 m.
    $plan = $svc->optimize2D(12, 1250, 600, 0, [['length' => 5, 'quantity' => 3]]);

    expect($plan['strips_per_coil'])->toBe(2);
    expect($plan['bars_count'])->toBe(2);      // 1D : 2 barres nécessaires
    expect($plan['coils_needed'])->toBe(1);    // 2 barres réparties sur 2 bandes → 1 bobine
    expect($plan['width_offcut_mm'])->toBe(50.0); // 1250 - 2×600
    expect($plan['width_yield'])->toBe(96.0);  // 1200/1250
    expect($plan['combined_yield'])->toBe(60.0); // 62,5 % longueur × 96 % largeur
});

it('conserve les métriques 1D sous-jacentes', function () {
    $svc = new CuttingOptimizerService();
    $plan = $svc->optimize2D(12, 1250, 600, 0, [['length' => 5, 'quantity' => 3]]);

    expect($plan['used'])->toBe(15.0);
    expect($plan['yield'])->toBe(62.5); // rendement longueur inchangé
    expect($plan)->toHaveKey('reusable_offcut');
});
