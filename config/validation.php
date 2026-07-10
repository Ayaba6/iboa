<?php

/*
 * [CDC §Workflow — relance automatique]
 * Délais (en heures) au-delà desquels un document en attente de validation
 * déclenche une relance vers ses valideurs. Configurable par module.
 */
return [

    // Autorise globalement le canal email (chaque utilisateur doit en plus
    // l'activer sur son profil — notification interne ERP toujours envoyée).
    'email_channel' => env('VALIDATION_EMAIL_CHANNEL', true),

    'reminder_hours' => [
        'commercial' => env('VALIDATION_REMINDER_COMMERCIAL', 48), // devis, commandes, BL, factures, avoirs
        'production' => env('VALIDATION_REMINDER_PRODUCTION', 24), // OF (attente chef / responsable)
        'achats'     => env('VALIDATION_REMINDER_ACHATS', 48),     // demandes d'achat
    ],

];
