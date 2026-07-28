# AUDIT DU MODULE VENTES — A3 ERP

## Statut

**MODULE VENTES EN AUDIT — NO-GO STAGING — NO-GO PRODUCTION — PUSH INTERDIT**

> Module Ventes de profondeur fonctionnelle comparable à un ERP industriel de référence, adapté aux processus commerciaux, industriels, logistiques et comptables réels de l’ERP A3 et de OA METAL INDUSTRIE.

Cette formulation décrit la cible. Elle ne constitue pas une certification actuelle et ne prétend pas à une identité avec Sage X3.

## Identité et environnement — 28/07/2026

| Élément | Valeur | Preuve |
|---|---|---|
| Projet | A3 ERP | `artisan about` |
| Entreprise cible | OA METAL INDUSTRIE | mission |
| Module | Ventes | routes `/ventes` |
| Projet local | `C:\laragon\www\iboa` | environnement Codex |
| Dépôt | `https://github.com/rrodyz/iboa.git` | `git remote get-url origin` |
| Branche | `fix/erp-cdc-prod-compta-rh` | `git branch --show-current` |
| SHA initial | `4b876f795914029c0376ec02b7f64092ec6b1424` | `git rev-parse HEAD` |
| Avance locale | 46 commits | comparaison upstream |
| PHP | 8.2.28 | `php -v` |
| Laravel | 12.64.0 | `artisan --version` |
| MySQL | 8.4.3 | `artisan db:show` |
| Base développement | `iboa_erp` | `artisan db:show` |
| Base tests SQLite | `:memory:` | `phpunit.xml` + baseline |
| Base tests MySQL | `iboa_erp_test` | `phpunit.mysql.xml` + garde |
| Environnement | local, debug actif | `artisan about` |
| Mode | HTTP local, queue sync, cache/session fichiers | `artisan about` |

## Réserves d’environnement

1. **ÉLEVÉE — arbre Git non propre.** Des changements Ventes coexistent avec des changements Production hors périmètre (`ExecutionContext`, `CoilSplitApprovalTest`, `system_actors.php`). Ils ont été préservés et non modifiés.
2. **ÉLEVÉE — huit migrations Achats/Stocks en attente** (`2026_07_27_110000` à `180000`). Elles ne sont pas appliquées dans cet audit afin de ne pas altérer les lots certifiés Production/Achats.
3. **MOYENNE — initialisation MySQL très lente** : environ 237 à 311 secondes avant le premier test. Risque CI et diagnostic à traiter.
4. **ÉLEVÉE — parcours navigateur non authentifiés** : les sept URL redirigent vers la connexion. Aucun scénario UI ne peut être certifié sans session de recette.

## Baseline avant nouveau lot critique

| Moteur | Tests | Assertions | Échecs | Durée |
|---|---:|---:|---:|---:|
| SQLite | 98 | 406 | 0 | 76,77 s |
| MySQL | 98 | 406 | 0 | 299,44 s |
| Garde MySQL | 1 | 3 | 0 | 311,40 s |

## Inventaire synthétique

108 routes liées aux ventes : tableau de bord 1, devis 17, commandes 20, préparations 5, livraisons 12, factures 22, avoirs 13, fonctions connexes 18.

| Domaine | Contrôleur principal | Services dominants | Modèles/tables | Vues/PDF | État |
|---|---|---|---|---|---|
| Tableau de bord | `SalesDashboardController` | `SalesInsightsService`, `SalesProductionService` | invoices, orders, quotes | `ventes/dashboard` | PROUVÉE côté serveur ; navigateur NON TESTÉ |
| Devis | `QuoteController` | `QuoteService`, `CommercialWorkflowService`, `SalesPriceGuardService`, `SalesFloorWaiverService` | quotes, quote_items, sales_floor_waivers | index/form/show/PDF/export | PROUVÉE pour création, révision, validation, plancher et conversion |
| Commandes | `OrderController` | `OrderService`, `CommercialWorkflowService`, `CustomerCreditExposureService` | orders, order_items | index/form/show | PROUVÉE pour workflow, crédit, MTO/MTS et réservations |
| Préparation | `BonPreparationController` | `BonPreparationService` | bon_preparations | index/show/PDF | PARTIELLEMENT PROUVÉE : chargement oui, scan/contrôle détaillé incomplet |
| Livraison | `DeliveryNoteController` | `DeliveryNoteService`, allocations lots, garde production | delivery_notes/items/allocations, stock_movements | index/edit/show/PDF/verify | PROUVÉE sur stock, lots, qualité et garde double facture ; concurrence réelle partielle |
| Factures | `InvoiceController` | `InvoiceService`, `AccountingService` | invoices/items, journal_entries | index/form/show/PDF/verify | PROUVÉE sur validation, double facturation, coûts figés et écritures |
| Avoirs | `CreditNoteController` | `CreditNoteService`, retours historiques | credit_notes/items/lot_returns | create/index/show/PDF | PROUVÉE sur disposition, stock, extourne et remboursement ; RMA séparé incomplet |

## Anomalies prioritaires

| Sévérité | Anomalie | Statut |
|---|---|---|
| CRITIQUE | Contrôle crédit fondé seulement sur le solde mémorisé, sans nouvelle commande, commandes ouvertes, acomptes ni verrou client | CORRIGÉE dans ce lot ; SQLite et MySQL verts |
| ÉLEVÉE | Confirmations natives `confirm()` dans 32 actions Ventes | CORRIGÉE ; modale applicative obligatoire et test statique |
| ÉLEVÉE | KPI SQL directs non isolés par société | CORRIGÉE ; test d’isolation ajouté |
| ÉLEVÉE | Parcours navigateur A à G non certifiés | OUVERT — session authentifiée requise |
| ÉLEVÉE | Concurrence MySQL réelle à deux connexions non couverte pour tous les reliquats | INCOMPLÈTE |
| ÉLEVÉE | Axes de statut commande finance/logistique/facturation encore partiellement dérivés du statut principal | PARTIELLEMENT PROUVÉE |
| MOYENNE | Préparation sans workflow complet contrôlé/validé/scan | INCOMPLÈTE |
| MOYENNE | Retour client sans objet RMA/autorisations distinctes | PARTIELLEMENT PROUVÉE via avoirs et dispositions |
| MOYENNE | Détails complets du risque et de la marge non affichés dans toutes les modales | INCOMPLÈTE |
| FAIBLE | Initialisation MySQL trop lente pour une boucle de développement | OUVERT |

## Corrections réalisées dans le lot

- isolation multi-société des KPI et correction des statuts de commande ;
- navigation Ventes transverse sur les sept écrans ;
- suppression des confirmations natives Ventes et des fallbacks natifs ;
- contrôle centralisé de l’encours prévisionnel ;
- verrou pessimiste du client pendant la soumission d’une commande ;
- prise en compte des factures ouvertes, commandes ouvertes non facturées, nouvelle commande et acomptes confirmés non affectés ;
- tests SQLite et MySQL ciblés.

## Décision

**NO-GO STAGING.** Les baselines serveur sont solides, mais le GO exige encore les parcours navigateur authentifiés, les tests de concurrence MySQL à connexions réellement simultanées, la machine d’états multidimensionnelle complète, le workflow de préparation détaillé et la clôture des migrations/environnement.