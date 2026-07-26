# Audit du module Production — A3 ERP

Date : 25 juillet 2026
Projet : **A3 ERP — OA METAL INDUSTRIE**
Référence Git de départ : `6976609cd6320cce9a99bb16703c1d3e107524cf` (`fix/erp-cdc-prod-compta-rh`)
Positionnement : profondeur fonctionnelle comparable aux besoins industriels couverts par Sage X3, adaptée aux processus réels d’OA METAL INDUSTRIE.

## 1. Confirmation du projet

| Élément | Preuve |
|---|---|
| Application | A3 ERP |
| Chemin | `C:\laragon\www\iboa` |
| Dépôt | `https://github.com/rrodyz/iboa.git` |
| Branche | `fix/erp-cdc-prod-compta-rh` |
| PHP / Laravel | 8.2.28 / 12.64.0 |
| SGBD | MySQL 8.4.3, base locale `iboa_erp` |
| Environnement | `local` |
| Routes Production/Qualité | 168 |
| Contrôleurs | 33 |
| Services métier | 25 |
| Modèles | 34 |
| Vues | 53 |
| Migrations liées | 61 |
| Permissions liées | 25 |
| Tests liés recensés | 72 fichiers |

Le dépôt analysé est explicitement **l’ERP A3**. Il ne s’agit ni de POS PITCH, ni de SOULGA.

## 2. Méthode et preuves

- inventaire des routes Laravel, contrôleurs, services, modèles, migrations, vues, permissions et tests ;
- ouverture authentifiée des 24 écrans dans le navigateur sur `http://iboa.test` ;
- recherche de `TODO`, placeholders, liens `#` et actions vides dans Production/Qualité ;
- audits `a3:audit-production`, `a3:audit-database`, `a3:audit-security`, `a3:audit-schema`, `stock:audit-coil-lots`, `audit:business` ;
- tests ciblés SQLite et MySQL ;
- suite SQLite de référence : 882 tests, 3 072 assertions, tous réussis avant le présent lot.

## 3. Synthèse des 24 sous-modules

| # | Sous-module | Routes/écran | Statut | Observation principale |
|---:|---|---|---|---|
| 1 | Tableau de bord | Oui | PARTIELLEMENT PROUVÉ | KPI réels et chaîne documentaire ; filtres multidimensionnels à approfondir |
| 2 | Commandes à produire | Oui | PROUVÉ | éligibilité MTO/MTS, contrôle financier et prévention des doublons testés |
| 3 | Planification MTS | Oui | PROUVÉ | propositions validables, paramètres stock et parcours E2E MTS |
| 4 | Ordres de fabrication | Oui | PROUVÉ | workflow, réservations, exécution, coûts, annulation et clôture testés |
| 5 | Suivi de fabrication | Oui | PARTIELLEMENT PROUVÉ | saisie et listes opérationnelles ; mode tablette/temps réel à renforcer |
| 6 | Nomenclatures | Oui | PARTIELLEMENT PROUVÉ | versions, composants et substitutions ; profondeur multi-niveau à compléter |
| 7 | Gammes | Oui | PARTIELLEMENT PROUVÉ | opérations et coûts ; parallélisme/chevauchement non totalement prouvés |
| 8 | Machines | Oui | PROUVÉ | disponibilité et garde maintenance testées |
| 9 | Centres de travail | Oui | PARTIELLEMENT PROUVÉ | capacité et coûts présents ; calendriers fins à compléter |
| 10 | Lignes | Oui | PARTIELLEMENT PROUVÉ | référentiel opérationnel ; compatibilités produit/profil à renforcer |
| 11 | Maintenance | Oui | PROUVÉ | corrective, préventive, pièces, démarrage/fin et indisponibilité machine |
| 12 | Plan de charge | Oui | PROUVÉ | charge/capacité et replanification testées |
| 13 | Temps d’arrêt | Oui | PROUVÉ | déclaration, clôture, causes et impact planning |
| 14 | Optimisation découpe | Oui | PROUVÉ | nesting, chutes, reliquats, bobines et exécution testés |
| 15 | Indicateurs qualité | Oui | PROUVÉ | KPI issus des données et tests dédiés |
| 16 | Plans de contrôle | Oui | PARTIELLEMENT PROUVÉ | CRUD et caractéristiques ; échantillonnage avancé à renforcer |
| 17 | Contrôles qualité | Oui | PROUVÉ | mesures, résultat, validation et liaison production |
| 18 | Non-conformités | Oui | PROUVÉ | CAPA, disposition et traçabilité |
| 19 | Actions correctives | Oui | PARTIELLEMENT PROUVÉ | workflow présent ; méthodes 5 pourquoi/Ishikawa à enrichir |
| 20 | Libération qualité | Oui | PROUVÉ | séparation des tâches et quarantaine testées |
| 21 | Certificats qualité | Oui | PARTIELLEMENT PROUVÉ | PDF/approbation présents ; preuve QR + archivage SHA à compléter |
| 22 | Réapprovisionnement MRP | Oui | PROUVÉ | calcul et propositions sans transformation automatique |
| 23 | Prévision de trésorerie | Oui | PARTIELLEMENT PROUVÉ | engagements par OF ; scénarios prudent/probable/optimiste à compléter |
| 24 | Rapports | Oui | PARTIELLEMENT PROUVÉ | filtres, Excel/PDF ; vues sauvegardées et couverture catalogue à étendre |

## 4. Anomalies classées

### Corrigées dans ce lot

| Gravité | Anomalie | Cause racine | Correction |
|---|---|---|---|
| ÉLEVÉE | faux écarts de soldes comptables dans `audit:business` | les lignes d’écritures brouillon étaient sommées malgré la jointure filtrée | agrégation conditionnée à une écriture non brouillon/non supprimée |
| ÉLEVÉE | faux écart stock bobine | mélange de `quantity` en ML et du stock en KG | utilisation prioritaire de `quantity_in_stock_uom` |
| MOYENNE | audit clients incompatible SQLite | `HAVING` sans agrégation et `GREATEST` non portables | sous-requête + `WHERE` + `CASE` portables |
| ÉLEVÉE | dérive de schéma locale | trois migrations en attente | migrations appliquées ; audit schéma propre |

### Résiduelles

| Gravité | Sujet | Décision |
|---|---|---|
| ÉLEVÉE | parité fonctionnelle MySQL complète non rejouée après le lot | exécuter la séquence stricte avant push |
| MOYENNE | filtres dashboard incomplets par usine/agence/équipe/client/famille | prochain lot fonctionnel |
| MOYENNE | snapshots immuables BOM/gamme à prouver sur tous les cas | ajouter tests de modification post-lancement |
| MOYENNE | tablette atelier et quasi temps réel | audit UX dédié |
| MOYENNE | certificats QR + SHA-256 archivés | compléter preuve documentaire |
| FAIBLE | libellé « Nom d’utilisateur » pour un champ e-mail | corriger lors du lot UX |

## 5. Audits après correction

- Production : propre.
- Base : propre.
- Sécurité : propre en local ; maker-checker volontairement toléré hors production.
- Schéma : propre après migration.
- Bobines/lots : aucune anomalie ; écart 0,0048 KG sous tolérance 0,0100 KG.
- Métier : 19 contrôles réussis, aucune anomalie.
- Synchronisation intermodules en dry-run : aucune correction nécessaire.

## 6. Décision de préparation

**NO-GO staging.** Le lot 1 prouve uniquement la cartographie, l’ouverture des écrans, la suite SQLite locale et les corrections d’audit couvertes. La suite MySQL complète, la parité, les parcours navigateur fonctionnels, les snapshots BOM/gammes et les autres bloqueurs restent à prouver.
