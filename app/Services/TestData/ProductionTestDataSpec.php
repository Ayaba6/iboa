<?php

namespace App\Services\TestData;

/**
 * [Données de test MTS/MTO] Configuration ATTENDUE des articles industriels.
 *
 * Cette classe ne décrit pas ce que la base contient : elle décrit ce que la
 * campagne de test exige. L'écart entre les deux est un CONFLIT, jamais une
 * correction implicite — c'est l'auditeur qui le qualifie et l'arbitre.
 *
 * DEUX FAMILLES DE CHAMPS
 *
 *   STRUCTURANTS  — jamais écrits automatiquement dès lors que l'article porte
 *                   des transactions. Changer l'unité de stock ou la méthode de
 *                   valorisation d'un article déjà mouvementé réécrit le sens
 *                   d'un historique qu'on ne peut plus recalculer.
 *
 *   FAIBLE RISQUE — complétables après affichage. Un seuil de réapprovisionnement
 *                   absent ne raconte aucune histoire passée ; il ne fait
 *                   qu'empêcher le calcul des besoins de démarrer.
 *
 * Le classement vient de l'arbitrage métier, pas d'une intuition technique.
 */
final class ProductionTestDataSpec
{
    /** Référence du lot de données de test. Tout ce qui est généré la porte. */
    public const BATCH = 'A3-PRODUCTION-TEST-20260731';

    /** Dépôt de test dédié : les données de test ne se mélangent pas au réel. */
    public const TEST_WAREHOUSE_CODE = 'DEP-MP-TEST';

    /**
     * Champs qu'aucune exécution automatique ne modifie sur un article
     * transactionné. Liste arrêtée par le métier.
     */
    public const STRUCTURANTS = [
        'code_article', 'type', 'type_article', 'production_mode',
        'is_purchasable', 'is_manufacturable', 'is_stockable', 'is_sellable',
        'unit_id', 'sale_unit_id', 'purchase_unit_id',
        'valuation_method', 'has_lot_number', 'has_serial_number',
        'item_category_id', 'family_id', 'sub_family_id',
        'kg_per_linear_meter', 'weight', 'thickness', 'largeur_utile',
        'couleur', 'longueur_standard',
    ];

    /** Champs complétables, après affichage, sans réécrire d'historique. */
    public const FAIBLE_RISQUE = [
        'stock_min', 'stock_max', 'stock_securite', 'reorder_point',
        'delivery_delay_days', 'designation_2', 'controle_qualite',
    ];

    /** Libellés lisibles pour le rapport. */
    public const LIBELLES = [
        'code_article' => 'code article',
        'type_article' => 'type d’article',
        'production_mode' => 'mode de production',
        'unit_id' => 'unité de stock',
        'sale_unit_id' => 'unité de vente',
        'purchase_unit_id' => 'unité d’achat',
        'valuation_method' => 'méthode de valorisation',
        'has_lot_number' => 'gestion par lot',
        'kg_per_linear_meter' => 'coefficient kg/ML',
        'thickness' => 'épaisseur / diamètre',
        'largeur_utile' => 'largeur utile',
        'couleur' => 'couleur',
        'longueur_standard' => 'longueur standard',
        'controle_qualite' => 'contrôle qualité',
        'stock_min' => 'stock minimum',
        'stock_max' => 'stock maximum',
        'stock_securite' => 'stock de sécurité',
        'reorder_point' => 'point de commande',
        'delivery_delay_days' => 'délai',
        'is_sellable' => 'vendable',
        'weight' => 'poids théorique',
        'nuance' => 'nuance',
    ];

    /**
     * Largeur retenue pour les bobines de test, faute de donnée technique sur
     * l'article comme dans les nomenclatures. HYPOTHÈSE DE TEST — à ne jamais
     * propager aux données réelles sans validation métier.
     */
    public const LARGEUR_HYPOTHESE_MM = 1200.0;

    /**
     * Coût conventionnel appliqué aux SEULS articles dépourvus de valorisation.
     *
     * Aucun article de la campagne ne porte de prix d'achat. Deux voies s'offraient :
     * inventer un prix plausible, ou assumer une convention visible. Un prix
     * inventé se lit comme une donnée réelle et contamine le PMP ; 1,00 se
     * reconnaît immédiatement pour ce qu'il est — un remplissage de test.
     *
     * Les articles qui portent DÉJÀ un PMP entrent à ce PMP : la moyenne pondérée
     * reste alors mathématiquement inchangée, et aucune valorisation réelle n'est
     * déplacée par des données de test.
     */
    public const COUT_TEST_DEFAUT = 1.00;

    /**
     * Articles attendus, par code. `module` sert au filtre --module.
     *
     * `attendu` porte la configuration cible ; `stock` décrit ce que la campagne
     * veut voir en stock. Un `stock` nul est volontaire : c'est lui qui fait
     * naître le besoin MTS.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function articles(): array
    {
        return array_merge(
            self::sousProduits(),
            self::filMachine(),
            self::bobinesPrelaquees(),
            self::produitsFinisMts(),
        );
    }

    /** Sous-produits : alimentés par les déclarations, jamais par un stock initial. */
    private static function sousProduits(): array
    {
        $base = [
            'module' => 'commun',
            'role' => 'sous_produit',
            'attendu' => [
                'is_active' => true,
                'is_stockable' => true,
                // Le métier veut ces articles hors de la vente normale. La base
                // dit l'inverse : c'est un conflit à arbitrer, pas à écraser.
                'is_sellable' => false,
                'unite' => 'Kilogramme',
            ],
            // Dépôt cible selon la nature : chute et avarie ne se rangent pas ensemble.
            'stock' => ['quantite' => 0.0],
        ];

        return [
            'AVFAB0001' => $base + ['designation' => 'Avarie - fer à béton', 'depot' => 'DEP-REBUT'],
            'AVTBC0001' => $base + ['designation' => 'Avarie - morceaux de tôles bacs', 'depot' => 'DEP-REBUT'],
            'CHFAB0001' => $base + ['designation' => 'Chute - fer à béton', 'depot' => 'DEP-CHUTE'],
            'CHTBC0001' => $base + ['designation' => 'Chute - tôles bacs', 'depot' => 'DEP-CHUTE'],
        ];
    }

    /**
     * Fil machine : 9 diamètres, tous en SAE 1008.
     *
     * ATTENTION — le diamètre est stocké dans `thickness`, colonne nommée
     * « épaisseur ». Détournement constaté en base, pas introduit ici.
     * La nuance n'a AUCUNE colonne sur `products` : elle n'existe que sur
     * `coils`. Elle est donc invérifiable côté article.
     */
    private static function filMachine(): array
    {
        $diametres = [
            'MPFAB0001' => 6.50, 'MPFAB0006' => 7.00, 'MPFAB0008' => 8.00,
            'MPFAB0002' => 9.00, 'MPFAB0003' => 11.00, 'MPFAB0005' => 12.00,
            'MPFAB0004' => 13.00, 'MPFAB0009' => 14.00, 'MPFAB0007' => 15.00,
        ];

        $out = [];
        foreach ($diametres as $code => $d) {
            $out[$code] = [
                'module' => 'mts',
                'role' => 'matiere_fil',
                'designation' => sprintf('Bobine fil machine SAE 1008 de diamètre %.2f', $d),
                'depot' => self::TEST_WAREHOUSE_CODE,
                'attendu' => [
                    'is_active' => true,
                    'is_stockable' => true,
                    'is_purchasable' => true,
                    'has_lot_number' => true,
                    'controle_qualite' => true,
                    'unite' => 'Kilogramme',
                    'thickness' => $d,
                    'nuance' => 'SAE 1008',
                    'stock_min' => 500, 'stock_securite' => 300,
                    'reorder_point' => 800, 'stock_max' => 5000,
                    'delivery_delay_days' => 15,
                ],
                'stock' => [
                    'quantite' => 3000.0,
                    'unite' => 'kg',
                    // Deux lots : un stock d'une seule masse ne permet de tester
                    // ni le FIFO, ni la consommation partielle, ni la traçabilité.
                    'lots' => [
                        ['suffixe' => 'A', 'quantite' => 1500.0],
                        ['suffixe' => 'B', 'quantite' => 1500.0],
                    ],
                ],
            ];
        }

        return $out;
    }

    /**
     * Bobines prélaquées : gérées en mètres linéaires, converties en kilos par
     * le coefficient. Le coefficient N'EST PAS un prix — il convertit une
     * longueur en masse et rien d'autre.
     */
    private static function bobinesPrelaquees(): array
    {
        $defs = [
            'MPTBC0001' => ['couleur' => 'Beige',  'ep' => 0.27, 'coef' => 2.056],
            'MPTBC0002' => ['couleur' => 'Beige',  'ep' => 0.30, 'coef' => 2.284],
            'MPTBC0003' => ['couleur' => 'Orange', 'ep' => 0.27, 'coef' => 2.056],
            'MPTBC0004' => ['couleur' => 'Orange', 'ep' => 0.30, 'coef' => 2.284],
        ];

        $out = [];
        foreach ($defs as $code => $d) {
            $out[$code] = [
                'module' => 'mto',
                'role' => 'matiere_bobine',
                'designation' => sprintf('Bobine prélaquée %s de %d/100', strtolower($d['couleur']), (int) round($d['ep'] * 100)),
                'depot' => self::TEST_WAREHOUSE_CODE,
                'attendu' => [
                    'is_active' => true,
                    'is_stockable' => true,
                    'is_purchasable' => true,
                    'has_lot_number' => true,
                    'controle_qualite' => true,
                    'unite' => 'Mètre linéaire',
                    'couleur' => $d['couleur'],
                    'thickness' => $d['ep'],
                    'kg_per_linear_meter' => $d['coef'],
                    'largeur_utile' => self::LARGEUR_HYPOTHESE_MM,
                ],
                'stock' => [
                    'quantite' => 1000.0,
                    'unite' => 'ML',
                    'coef' => $d['coef'],
                    'poids_attendu' => round(1000.0 * $d['coef'], 3),
                    // Deux bobines PHYSIQUES, pas deux lots : pour cette matière
                    // l'objet de traçabilité est la bobine, portée par un lot.
                    'bobines' => [
                        ['suffixe' => '01', 'metres' => 500.0],
                        ['suffixe' => '02', 'metres' => 500.0],
                    ],
                    'couleur' => $d['couleur'],
                    'epaisseur' => $d['ep'],
                    'largeur' => self::LARGEUR_HYPOTHESE_MM,
                ],
            ];
        }

        return $out;
    }

    /**
     * Produits finis fer à béton, fabriqués sur stock.
     *
     * Stock initial NUL, volontairement : c'est l'absence de stock face à un
     * seuil qui fait naître le besoin. Sans seuil, le calcul classe l'article
     * « non paramétré » et ne propose rien — le stock nul seul ne suffit pas.
     */
    private static function produitsFinisMts(): array
    {
        $defs = [
            'PFFAB0001' => ['d' => 6.0,  'poids' => 2.544,  'matiere' => 'MPFAB0001', 'min' => 100, 'secu' => 50, 'reappro' => 150, 'max' => 500],
            'PFFAB0003' => ['d' => 10.0, 'poids' => 7.070,  'matiere' => 'MPFAB0003', 'min' => 80,  'secu' => 40, 'reappro' => 120, 'max' => 400],
            'PFFAB0004' => ['d' => 12.0, 'poids' => 10.176, 'matiere' => 'MPFAB0005', 'min' => 60,  'secu' => 30, 'reappro' => 90,  'max' => 300],
        ];

        $out = [];
        foreach ($defs as $code => $d) {
            $out[$code] = [
                'module' => 'mts',
                'role' => 'produit_fini',
                'designation' => sprintf('Fer à béton de %d normalisé NB BL', (int) $d['d']),
                'depot' => 'DEP-PF',
                'attendu' => [
                    'is_active' => true,
                    'is_stockable' => true,
                    'is_sellable' => true,
                    'is_manufacturable' => true,
                    'production_mode' => 'mts',
                    'controle_qualite' => true,
                    'has_lot_number' => true,
                    'unite' => 'Unité',
                    'thickness' => $d['d'],
                    'weight' => $d['poids'],
                    'longueur_standard' => 12.0,
                    'stock_min' => $d['min'], 'stock_securite' => $d['secu'],
                    'reorder_point' => $d['reappro'], 'stock_max' => $d['max'],
                ],
                'nomenclature' => [
                    'composant' => $d['matiere'],
                    'quantite' => $d['poids'],
                    // Le taux de perte est déclaré à part : il ne s'ajoute pas au
                    // poids du produit fini, il alimente chutes et avaries.
                    'taux_perte' => 2.0,
                ],
                'stock' => ['quantite' => 0.0],
            ];
        }

        return $out;
    }
}
