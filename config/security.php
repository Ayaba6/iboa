<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maker-checker (séparation créateur / valideur)
    |--------------------------------------------------------------------------
    | [SEC-PHASE2 §2] Quand actif, l'auteur d'une opération ne peut pas la
    | valider/approuver lui-même (super_admin exempté). Désactivé par défaut
    | pour les petites équipes ; ACTIVER EN PRODUCTION via
    | SECURITY_MAKER_CHECKER=true. Chaque action peut être exemptée
    | individuellement ci-dessous (false = pas de contrôle pour cette action).
    | Toute tentative bloquée est journalisée dans audit_logs.
    */
    'maker_checker' => [
        'enabled' => env('SECURITY_MAKER_CHECKER', false),

        'actions' => [
            'decaissement.approve'   => true,
            'purchase_order.approve' => true,
            'credit_note.validate'   => true,
            'journal_entry.validate' => true,
            'payroll_run.validate'   => true,
        ],
    ],
];
