<?php

use App\Support\SheetConversion;

/**
 * [§5 Tôle bac] Règle de conversion centralisée : métrage = nb tôles × longueur.
 */
it('10 tôles de 5 m donnent 50 mètres linéaires', function () {
    expect(SheetConversion::linearMeters(10, 5))->toBe(50.0)
        ->and(SheetConversion::resolveQuantity(10, 5, 1))->toBe(50.0);
});

it('applique un arrondi centralisé à 2 décimales', function () {
    // 3 tôles × 1.333 m = 3.999 → 4.00
    expect(SheetConversion::linearMeters(3, 1.333))->toBe(4.0);
});

it('un article standard (sans longueur) garde la quantité saisie', function () {
    expect(SheetConversion::linearMeters(10, 0))->toBeNull()
        ->and(SheetConversion::resolveQuantity(null, null, 7))->toBe(7.0)
        ->and(SheetConversion::isMeasuredSheet(10, 0))->toBeFalse();
});

it('détecte une tôle mesurée', function () {
    expect(SheetConversion::isMeasuredSheet(10, 5))->toBeTrue();
});
