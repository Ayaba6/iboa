# Purge des quatre ordres de fabrication de juillet 2026

| | |
|---|---|
| Date | 2026-08-01 |
| Base | `iboa_erp` (MySQL 8.4.3) |
| Décidé par | l'utilisateur, arbitrage explicite après présentation du périmètre de destruction |
| Exécuté par | Claude Code, option « Purger en base après sauvegarde dédiée » |
| Sauvegarde préalable | `storage/backups/iboa_erp-avant-purge-of-juillet-20260801-220020.sql` |
| SHA-256 | `8925854a774a7fc9e54ea4d7d6abc4282665ad26983e79e6c0c52be94b576a10` |

## 1. Motif

Quatre OF lancés en juillet 2026 ne portaient **aucune opération d'atelier**
alors que leur nomenclature (BOM 9) déclare une gamme de 4 opérations. Ils sont
antérieurs à `ProductionSnapshotService` : leur `routing_snapshot` est absent,
donc leur temps et leur coût de production n'avaient aucune base opératoire.

Le seul OF postérieur au service, `OF-2026-0007` (lancé le 01/08), porte bien
son snapshot et ses 4 opérations : **le code fonctionne**, c'était la donnée de
juillet qui était incomplète.

## 2. Objets supprimés

| Table | Lignes |
|---|---|
| `production_orders` | 4 (ids 16, 18, 19, 20) |
| `production_consumptions` | 4 *(cascade)* |
| `production_outputs` | 4 *(cascade)* |
| `production_quality_controls` | 4 *(cascade)* |
| `production_wastes` | 1 *(cascade)* |
| `production_costs` | 3 *(cascade)* |
| `stock_movements` | 8 (71, 72, 77, 78, 79, 80, 82, 83) |
| `journal_entries` | 4 (59, 61, 62, 63) |
| `journal_entry_lines` | 8 |

OF concernés : `OF-2026-0002` (terminé), `OF-2026-0004` (en cours),
`OF-2026-0005` (terminé), `OF-2026-0006` (terminé).

Exécution en une transaction unique. `stock_valuation_adjustments`
(`ON DELETE RESTRICT`) ne référençait aucun de ces OF — aucun blocage.

## 3. Garde appliquée avant écriture

Les quatre écritures comptables ont été vérifiées **équilibrées entre elles**
(débit 43 632 = crédit 43 632) avant suppression. Une écriture déséquilibrée
aurait faussé le grand livre : la purge aurait été interrompue.

Grand livre avant : 519 385 / 519 385 — après : **475 753 / 475 753, équilibré**.

## 4. Ce que la purge a contourné, et qu'il faut savoir

`ProductionService::cancel()` **refuse** cette opération, sur deux gardes :

- trois de ces OF sont `termine` — « OF déjà clôturé, annulation impossible » ;
- tous portent des consommations et déclarations vivantes — « la matière est
  physiquement engagée ».

La suppression SQL a donc contourné les règles métier de l'application. C'était
la nature même de l'option retenue, présentée comme telle avant décision.

## 5. Incohérences assumées et subsistantes

### 5.1 Stock non justifié par un mouvement

| Objet | État | Constat |
|---|---|---|
| Bobine #4 `BOB-REC-2026-001-01` | initial 10,00 kg / restant 2,50 kg | 7,5 kg consommés, **plus aucune consommation en base** |
| Bobine #5 `BOB-REC-2026-002-01` | initial 2,00 kg / restant 0,25 kg | 1,75 kg consommés, **plus aucune consommation en base** |
| Produit 28, dépôt 3 | 2,0000 | quantité conservée, mouvement d'origine supprimé |

Les soldes physiques n'ont pas été touchés : ils restent conformes à la matière
réellement transformée. C'est leur **justification documentaire** qui a disparu.

### 5.2 Commandes facturées sans ordre de fabrication

| Commande | Statut | TTC | OF restants |
|---|---|---|---|
| `CMD-2026-002` | `facture` | 18 880 | **0** |
| `CMD-2026-004` | `facture` | 4 720 | **0** |

Deux ventes facturées n'ont plus d'OF rattaché. Les fiches restent consultables
(HTTP 200 vérifié), mais la traçabilité vente → production est rompue.

### 5.3 Réservations déliées

`stock_reservations.production_order_id` est `ON DELETE SET NULL` : 3 lignes
portent aujourd'hui `NULL`. Le comptage antérieur n'ayant pas été relevé, une
partie de ces 3 lignes peut préexister à la purge — la part imputable n'est pas
établie.

## 6. Orphelins PRÉEXISTANTS — non causés par cette purge

`production_wastes` (2 lignes) et `production_costs` (7 lignes) référencent des
OF inexistants : ids **2, 5, 6, 7, 11, 13, 14**. Aucun n'appartient aux quatre
OF supprimés ici (16, 18, 19, 20). Ces orphelins résultent de suppressions
antérieures à la présente opération, effectuées avant la pose des contraintes
`ON DELETE CASCADE` ou en les contournant. À traiter séparément.

## 7. Vérifications après purge

| Écran | Statut |
|---|---|
| `/dashboard` | 200 |
| `/production/orders` | 200 |
| `/ventes/commandes` | 200 |
| `/stocks` | 200 |
| Fiche `CMD-2026-002` | 200 |
| Fiche `CMD-2026-004` | 200 |

Aucune consommation, sortie ou contrôle qualité orphelin issu de cette purge :
les cascades ont fonctionné.

## 8. Restauration

Restaurer intégralement le dump du §1 est la seule façon de revenir en arrière.
Il n'existe pas de retour partiel : les identifiants supprimés ne sont pas
réattribuables et les cascades ont emporté les lignes filles.
