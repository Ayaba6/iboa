<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maker-checker (séparation créateur / valideur)
    |--------------------------------------------------------------------------
    | [SEC-PHASE2 §2] FAIL CLOSED : en production le contrôle est ACTIF par
    | défaut — l'absence de la variable d'environnement ne désactive rien.
    | La désactivation en production exige SECURITY_MAKER_CHECKER=false posé
    | explicitement, et `a3:audit-security` la signale en échec CRITIQUE.
    | En local/testing : configurable librement selon le scénario.
    */
    'maker_checker' => [
        'enabled' => env('SECURITY_MAKER_CHECKER') !== null
            ? filter_var(env('SECURITY_MAKER_CHECKER'), FILTER_VALIDATE_BOOLEAN)
            : (env('APP_ENV') === 'production'),

        /*
        | Exemptions par action — IGNORÉES EN PRODUCTION pour les actions
        | listées dans `non_exemptable` ci-dessous. Hors production, false
        | désactive le contrôle pour l'action (scénarios de test/local).
        */
        'actions' => [
            'decaissement.approve'   => true,
            'purchase_order.approve' => true,
            'credit_note.validate'   => true,
            'credit_note.refund'     => true,
            'quality_derogation.approve' => true,
            'journal_entry.validate' => true,
            'payroll_run.validate'   => true,
            'cash_closure.validate'  => true,
            'reception.validate'     => true,
        ],

        /*
        | Actions jamais exemptables en production (annulations et validations
        | à effet financier irréversible). Toute exemption future devra passer
        | par une table dédiée (motif, approbateur, expiration, trace) — pas
        | par ce fichier.
        */
        'non_exemptable' => [
            'decaissement.approve',
            'credit_note.validate',
            'journal_entry.validate',
            'payroll_run.validate',
        ],
    ],
];
