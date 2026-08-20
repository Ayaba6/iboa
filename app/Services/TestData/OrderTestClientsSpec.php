<?php

namespace App\Services\TestData;

/**
 * [Données de test] Trois clients pour éprouver les modes de traitement d'une commande.
 *
 * PÉRIMÈTRE RÉELLEMENT TESTABLE — établi par audit, pas supposé :
 *
 *   CRÉDIT   entièrement testable. `CustomerCreditExposureService` calcule
 *            encours + commandes ouvertes + nouvelle commande − acomptes, et
 *            `assertMaySubmit()` bloque au-delà du plafond. Les dérogations
 *            vivent dans `credit_decisions` avec motif et auteur.
 *
 *   COMPTANT NON testable. Aucune règle ne compare le paiement au TTC :
 *            `registerPayment()` accepte `min:1` et `createForCashOrder()` crée
 *            le bon de préparation sans contrôle de couverture. Un franc suffit
 *            donc à rendre une commande éligible à la production.
 *
 *   ACOMPTE  NON testable. Aucune colonne du client ne porte un pourcentage
 *            d'acompte minimum. La règle des 50 % n'a nulle part où vivre.
 *
 * Les trois clients sont créés quand même : le jeu de données est prêt le jour
 * où les règles existeront. Leurs scénarios restent déclarés BLOQUÉS plutôt que
 * silencieusement omis — un test absent se remarque moins qu'un test rouge.
 */
final class OrderTestClientsSpec
{
    /** Référence portée par toutes les données du lot. */
    public const BATCH = 'A3-CLIENTS-COMMANDE-TEST-20260731';

    /**
     * Champs jamais modifiés automatiquement sur un client existant.
     * Liste arrêtée par le métier (§8 de la mission).
     */
    public const STRUCTURANTS = [
        'code', 'category', 'condition_paiement', 'credit_limit', 'currency',
        'tax_regime', 'compte_collectif', 'compte_tiers', 'is_active', 'is_blocked',
        'payment_mode',
    ];

    /** Champs complétables après affichage. */
    public const FAIBLE_RISQUE = [
        'trade_name', 'phone', 'email', 'city', 'country', 'language',
        'payment_days', 'notes', 'is_livrable', 'is_facturable', 'sales_rep_id',
    ];

    public const LIBELLES = [
        'code' => 'code client', 'category' => 'catégorie',
        'condition_paiement' => 'condition de paiement', 'credit_limit' => 'ligne de crédit',
        'currency' => 'devise', 'tax_regime' => 'régime fiscal',
        'compte_collectif' => 'compte collectif', 'compte_tiers' => 'compte auxiliaire',
        'payment_mode' => 'mode de règlement', 'payment_days' => 'délai de paiement',
        'is_active' => 'actif', 'is_blocked' => 'bloqué', 'trade_name' => 'nom commercial',
        'sales_rep_id' => 'représentant', 'language' => 'langue',
    ];

    /**
     * Les trois clients.
     *
     * `testable` dit si les scénarios de ce client peuvent être éprouvés
     * aujourd'hui. `blocage` en donne la raison, à afficher plutôt qu'à taire.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function clients(): array
    {
        return [
            'CLT-TEST-COMPTANT' => [
                'testable' => false,
                'blocage' => 'aucune règle ne compare le paiement encaissé au TTC — '
                    .'`registerPayment()` accepte min:1 et le bon de préparation est créé sans contrôle',
                'attendu' => [
                    'name' => 'Client Test Comptant SARL',
                    'trade_name' => 'TEST COMPTANT',
                    'type' => 'entreprise',
                    'category' => 'Client comptant',
                    'payment_mode' => 'cash',
                    'condition_paiement' => 'Paiement immédiat',
                    'payment_days' => 0,
                    'credit_limit' => 0,
                    'city' => 'Ouagadougou',
                    'country' => 'Burkina Faso',
                    'phone' => '+226 70 00 10 01',
                    'email' => 'comptant.test@a3-example.invalid',
                    'currency' => 'XOF',
                    'language' => 'fr',
                    'is_active' => true,
                    'is_blocked' => false,
                    'is_livrable' => true,
                    'is_facturable' => true,
                ],
                'scenarios' => [
                    'commande sans paiement → bloquée',
                    'paiement en brouillon → bloquée',
                    'paiement non confirmé → bloquée',
                    'paiement partiel → bloquée',
                    'paiement total confirmé → éligible',
                    'paiement supérieur au TTC → retenu plafonné au TTC',
                    'paiement annulé → de nouveau bloquée',
                ],
            ],

            'CLT-TEST-ACOMPTE' => [
                'testable' => false,
                'blocage' => 'aucune colonne du client ne porte un pourcentage d’acompte minimum ; '
                    .'et aucun blocage de livraison sur solde impayé n’existe',
                'attendu' => [
                    'name' => 'Client Test Acompte SARL',
                    'trade_name' => 'TEST ACOMPTE',
                    'type' => 'entreprise',
                    'category' => 'Client avec acompte',
                    'payment_mode' => 'cash',
                    'condition_paiement' => '50 % à la commande, solde avant livraison',
                    'payment_days' => 0,
                    'credit_limit' => 0,
                    'city' => 'Bobo-Dioulasso',
                    'country' => 'Burkina Faso',
                    'phone' => '+226 70 00 10 02',
                    'email' => 'acompte.test@a3-example.invalid',
                    'currency' => 'XOF',
                    'language' => 'fr',
                    'is_active' => true,
                    'is_blocked' => false,
                    'is_livrable' => true,
                    'is_facturable' => true,
                ],
                'scenarios' => [
                    'commande sans acompte → bloquée',
                    'acompte non confirmé → bloquée',
                    'acompte confirmé < 50 % → bloquée',
                    'acompte confirmé = 50 % → production autorisée',
                    'acompte confirmé > 50 % → production autorisée',
                    'solde non payé → livraison bloquée',
                    'acompte annulé avant lancement → éligibilité retirée',
                    'acompte libre → ne rend pas plusieurs commandes sœurs éligibles au-delà du disponible',
                ],
            ],

            'CLT-TEST-CREDIT' => [
                'testable' => true,
                'blocage' => null,
                'attendu' => [
                    'name' => 'Client Test Crédit SARL',
                    'trade_name' => 'TEST CRÉDIT',
                    'type' => 'entreprise',
                    'category' => 'Client à crédit',
                    'payment_mode' => 'credit',
                    'condition_paiement' => '30 jours',
                    'payment_days' => 30,
                    'credit_limit' => 10_000_000,
                    'city' => 'Ouagadougou',
                    'country' => 'Burkina Faso',
                    'phone' => '+226 70 00 10 03',
                    'email' => 'credit.test@a3-example.invalid',
                    'currency' => 'XOF',
                    'language' => 'fr',
                    'is_active' => true,
                    'is_blocked' => false,
                    'is_livrable' => true,
                    'is_facturable' => true,
                ],
                'scenarios' => [
                    'commande 2 000 000 avec encours nul → éligible',
                    'deuxième commande sous le plafond → éligible',
                    'commande faisant dépasser 10 000 000 → bloquée',
                    'paiement non confirmé → ne libère pas la ligne',
                    'paiement confirmé → réduit l’encours',
                    'acompte confirmé → compté une seule fois (part non affectée)',
                    'dérogation sans permission → refusée',
                    'dérogation sans motif → refusée',
                    'dérogation valide → journalisée dans credit_decisions',
                    'dérogation révoquée → éligibilité recalculée',
                ],
                // Montants évalués INDÉPENDAMMENT les uns des autres, chacun sur
                // l'encours réel du moment — ce n'est pas une séquence cumulative.
                // Le dernier dépasse seul le plafond : sans lui, les trois premiers
                // passeraient tous et le cas bloquant ne serait jamais démontré.
                'montants' => [2_000_000, 3_000_000, 9_000_000, 11_000_000],
            ],
        ];
    }

    /**
     * Valeurs fiscales fictives, employées seulement si le champ est obligatoire.
     * Le domaine `.invalid` est réservé par la RFC 2606 : il ne peut correspondre
     * à aucune adresse réelle, ni aujourd'hui ni plus tard.
     */
    public const IFU_FICTIF = 'TEST-IFU-0001';
    public const RCCM_FICTIF = 'TEST-RCCM-0001';
}
