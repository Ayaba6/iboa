# A3 ERP — Rapport final de la mission QA (22/07/2026)

## Identité et environnement
| | |
|---|---|
| Projet | A3 ERP — https://github.com/rrodyz/iboa.git |
| Branche | `fix/erp-cdc-prod-compta-rh` |
| SHA initial (mission) | `10498b3` — SHA final : voir `git log` (commits QA listés ci-dessous) |
| Environnement | PHP 8.2.28 · Laravel 12.64 · MySQL 8.4.3 · Node 22.16 |
| Sauvegardes | Dump complet pré-nettoyage `storage/backups/iboa_erp_avant_nettoyage_20260722_1323.sql` |

## État technique final
- **Tests : 742 passés, 2 492 assertions, 0 échec** (~110 s, 8 process) — 174 fichiers.
- **Build Vite : OK** · **composer audit : 0** · **npm audit : 0** · 0 migration en attente.
- 1 090 routes · 212 modèles · 190 contrôleurs · 102 services · 319 migrations (toutes additives).
- Smoke : 321 routes GET → 0 erreur (base vide comprise).

## Modules — synthèse
**Terminés et testés** : ventes (chaîne complète A), production (MTO/MTS, gates, B/C),
stocks (lots/bobines/réservations/CMP), qualité (SoD, libération), achats (E),
trésorerie (FIFO, anti-doublon, devise), comptabilité SYSCOHADA (équilibre garanti,
331/6033/372/737), RH/paie (F, IUTS/CNSS versionnés), référentiel X3
(catégories/familles/sous-familles/articles, héritage/propagation/gardes),
rôles/permissions (151, middleware serveur testés), PDF (identité 100 % paramétrage).
**Fonctionnels avec réserves** : CRM/Maintenance/Analytique (testés, périmètre standard),
Intégrations (webhooks HMAC OK — activation opérateurs à faire en production),
Budgets (CRUD + premiers tests — module jeune).

## Anomalies corrigées pendant la mission (extraits marquants)
| Gravité | Anomalie | Correction |
|---|---|---|
| Bloquant | Heures de pointage NÉGATIVES (Carbon 3 signé) | sens du diff corrigé + test |
| Bloquant | Allocations paiement fournisseur ignorées en silence (facture jamais soldée, GL posté) | exception explicite + alias |
| Critique | Réservation fantôme sur commande livrée (re-confirmation) | garde statut + recalcul global |
| Critique | Stock MP surévalué de 384 kg (consommations legacy) | réconciliation tracée (audit-coil-lots) |
| Critique | Gate lancement ignorait l'approbation gérant (scénario B impossible) | reconnaissance hasValidProductionApproval |
| Majeur | Re-saisie pointage = 500 ; création budget sans version = 500 ; création perte stock/plan contrôle = 500 ; save plan maintenance = 500 | corrigés + tests |
| Majeur | Congés chevauchants acceptés | gardes création + approbation |
| Majeur | Dashboard invisible des tests (YEAR/MONTH MySQL-only) | bornes de dates portables |
| Majeur | `sort_order` familles jamais persisté ; pivot dépôts vidé à chaque update famille | colonne créée ; appels legacy purgés |

## Base de données
0 orphelin FK (31 relations vérifiées) · 0 doublon de numéro · 0 écriture déséquilibrée ·
0 incohérence stock (réconcilié) · 0 incohérence paiement · casts complets · index critiques
présents. Base NETTOYÉE pour recette (`erp:pre-production-clean`, référentiel intact,
1 client + 1 fournisseur de test).

## Performance
Budgets de requêtes en continu : listes commandes/articles delta ≤ 10 requêtes entre 10 et
100 lignes (aucun N+1) ; dashboard 81 requêtes constantes (garde-fou 90, cache KPI 5 min).

## Sécurité
Routes métier 100 % derrière auth (test réel) ; permissions par module ; SoD qualité ;
webhooks signés HMAC ; 0 advisory dépendances ; registration désactivée ; anti-doublon
paiements ; garde devise ; mono-société par construction.

## Paramètres métier restants (bloquants recette, pas code)
1. Prix de vente catalogue : tôles PFTBC (repère hist. ~5 144 F/ML), fers PFFAB (5 000 F),
   MTOND, chutes, avaries. Prix d'achat bobines (CMP hist. MPTBC 1 496 F/kg).
2. Seuils stock (min/max/sécurité) par article ou par catégorie.
3. Activation des intégrations Orange/Moov (clés opérateur).

## Décision
**PRÊT POUR STAGING.** Passage « prêt pour production » conditionné à : recette manuelle
A-F déroulée sur staging (voir docs/DEPLOIEMENT.md), saisie des paramètres métier,
stock initial réel.
