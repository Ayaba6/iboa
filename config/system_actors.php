<?php

/*
|--------------------------------------------------------------------------
| Registre FERMÉ des acteurs système
|--------------------------------------------------------------------------
| [Sécurité #8/#9] Un appelant ne fournit JAMAIS ses propres permissions.
| Les permissions effectives d'un traitement automatique sont celles
| ENREGISTRÉES ici, pas celles demandées à l'appel.
|
|   permissions effectives = permissions du registre
|   ≠ permissions demandées par l'appelant
|
| Un identifiant inconnu, désactivé ou expiré est REFUSÉ.
| Aucun acteur ne détient « * » : chaque entrée liste ses permissions minimales.
*/

return [

    'purchase_import' => [
        'name'        => 'Import de réceptions fournisseurs',
        'origin'      => 'artisan:purchases:import',
        'owner'       => 'exploitation',
        'permissions' => [
            'receptions.create',
        ],
        'active'      => true,
        'expires_at'  => null,
    ],

    'inventory_reconciliation' => [
        'name'        => 'Réconciliation d\'inventaire',
        'origin'      => 'artisan:stock:audit-coil-lots',
        'owner'       => 'exploitation',
        'permissions' => [
            'stock.view',
        ],
        'active'      => true,
        'expires_at'  => null,
    ],

    'coil_recovery_job' => [
        'name'        => 'Reprise technique bobines après incident',
        'origin'      => 'queue:coil-recovery',
        'owner'       => 'exploitation',
        // Reprise d'incident : exécution + dérogation technique, rien de plus.
        // La dérogation reste soumise à sa procédure break-glass.
        'permissions' => [
            'coils.split.execute',
            'coils.split.technical_override',
        ],
        'active'      => true,
        'expires_at'  => null,
    ],

    'approved_migration' => [
        'name'        => 'Migration de données approuvée',
        'origin'      => 'artisan:migrate',
        'owner'       => 'technique',
        'permissions' => [],   // lecture seule par défaut
        'active'      => true,
        'expires_at'  => null,
    ],

];
