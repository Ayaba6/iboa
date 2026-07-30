<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comptabilisation automatique de la production (SYSCOHADA)
    |--------------------------------------------------------------------------
    |
    | Désactivée par défaut. Lorsqu'elle est activée, la clôture d'un OF
    | (statut « terminé ») génère automatiquement :
    |   - la sortie de stock matières premières  (DR 6032 / CR 321)
    |   - la production stockée des produits finis (DR 361 / CR 736)
    |
    | Pour activer : PRODUCTION_ACCOUNTING_ENABLED=true dans .env
    |
    */
    'accounting' => [
        'enabled' => env('PRODUCTION_ACCOUNTING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compatibilité bobine ↔ ordre de fabrication  (règle MTO §9)
    |--------------------------------------------------------------------------
    |
    | Tolérances appliquées à la comparaison des caractéristiques DIMENSIONNELLES
    | entre la bobine engagée et l'OF. Elles existent parce qu'une épaisseur ou
    | une largeur ne se compare pas comme une chaîne : « 0.30 » et « 0.300 » sont
    | la même tôle, et la métallurgie tolère un écart de laminage.
    |
    | Épaisseur, en MILLIMÈTRES. 0,01 mm — soit 1/100e, l'unité de désignation
    | commerciale des tôles (« 27/100 »). Deux bobines qui diffèrent de plus d'un
    | centième ne portent pas la même désignation. L'OF peut imposer sa propre
    | tolérance via `tolerance_epaisseur`, qui prime alors sur cette valeur.
    |
    | Largeur, en MILLIMÈTRES. 1 mm : les largeurs de bobine se commandent au
    | millimètre (1000, 1200, 1250), et le refendage a sa propre dispersion.
    |
    | Ces tolérances ne masquent rien : tout écart, même toléré, reste visible en
    | consultation. Elles évitent seulement de bloquer un atelier sur un arrondi.
    |
    */
    'coil_compatibility' => [
        'thickness_tolerance_mm' => env('COIL_THICKNESS_TOLERANCE_MM', 0.01),
        'width_tolerance_mm'     => env('COIL_WIDTH_TOLERANCE_MM', 1.0),
    ],

];
