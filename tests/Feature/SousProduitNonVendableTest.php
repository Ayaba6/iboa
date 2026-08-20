<?php

/**
 * [Référentiel] Les sous-produits ne naissent pas vendables.
 *
 * Les quatre sous-produits ont été passés « non vendables » sur décision métier :
 * avaries et chutes sortent du catalogue de vente standard. Mais leur CATÉGORIE
 * restait vendable, et `CategoryDefaultsService` recopie `is_sellable` de la
 * catégorie vers chaque article créé.
 *
 *     'is_sellable' => $cat->is_sellable,
 *
 * Le prochain sous-produit serait donc né vendable, annulant la correction sans
 * que personne ne le voie. On avait corrigé les pièces, pas le moule.
 *
 * La cession de déchets et ferrailles reste possible : elle devient une décision
 * explicite sur l'article concerné, au lieu d'un défaut hérité en silence.
 */

use App\Models\ItemCategory;
use App\Services\CategoryDefaultsService;
use Database\Seeders\ItemCategorySeeder;

uses(\Tests\Concerns\RefreshDatabase::class);

it('garde la catégorie des sous-produits hors de la vente standard', function () {
    $this->seed(ItemCategorySeeder::class);

    $cat = ItemCategory::where('code', 'SOUS_PRODUIT')->first();
    expect($cat)->not->toBeNull();
    expect((bool) $cat->is_sellable)->toBeFalse();

    // Stockable et traçable, en revanche : un sous-produit se déclare, se pèse
    // et se valorise — il ne se vend simplement pas au catalogue.
    expect((bool) $cat->is_stockable)->toBeTrue();
});

it('fait naître non vendable un article créé sous cette catégorie', function () {
    $this->seed(ItemCategorySeeder::class);

    $defauts = app(CategoryDefaultsService::class)
        ->defaultsFor(ItemCategory::where('code', 'SOUS_PRODUIT')->firstOrFail());

    // C'est CE point qui protège la décision métier dans la durée : sans lui,
    // la correction ne tient que jusqu'à la prochaine création d'article.
    expect($defauts['is_sellable'])->toBeFalse();
});

it('laisse vendables les catégories qui doivent l\'être', function () {
    $this->seed(ItemCategorySeeder::class);

    // Contrôle en miroir : sans lui, une catégorie entièrement non vendable
    // passerait le test précédent sans rien prouver.
    foreach (['PF_TOLE_MTO', 'PF_FER_MTS'] as $code) {
        $cat = ItemCategory::where('code', $code)->first();
        expect($cat)->not->toBeNull();
        expect((bool) $cat->is_sellable)->toBeTrue();
    }
});
