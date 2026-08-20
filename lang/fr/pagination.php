<?php

/**
 * Libellés de pagination.
 *
 * Ce fichier manquait : `lang/fr/` ne contenait que `validation.php`. La locale
 * était pourtant bien « fr » (APP_LOCALE et APP_FALLBACK_LOCALE), mais sans
 * traduction à charger, Laravel rendait la clé anglaise telle quelle — « Showing
 * 1 to 20 of 27 results » sur TOUTES les listes paginées de l'ERP : articles,
 * commandes, factures, ordres de fabrication, clients.
 *
 * Les quatre chaînes du texte de comptage (Showing / to / of / results) sont des
 * traductions JSON et vivent dans `lang/fr.json` ; celles-ci sont des clés de
 * fichier, utilisées pour les libellés d'accessibilité des flèches.
 */

/*
 * Guillemets ÉCRITS EN CLAIR, pas en entités HTML.
 *
 * La vue du paginateur utilise ces clés de DEUX façons : en HTML brut pour les
 * boutons mobiles (`{!! !!}`), et dans un `aria-label` pour les flèches
 * (`{{ }}`). Avec « &laquo; », le second cas n'est pas décodé : un lecteur
 * d'écran annoncerait littéralement « &laquo; Précédent ». Les vrais caractères
 * s'affichent correctement dans les deux contextes.
 */
return [
    'previous' => '« Précédent',
    'next'     => 'Suivant »',
];
