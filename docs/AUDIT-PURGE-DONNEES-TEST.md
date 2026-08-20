# AUDIT DES DONNÉES DE TEST — PHASE 1 : AUDIT SANS SUPPRESSION

**Base auditée** — `iboa_erp` (MySQL 8.4.3, 127.0.0.1:3306)
**Date** — 2026-08-04
**Mode** — lecture seule stricte. Aucune écriture, aucune suppression, aucune transaction modifiante.
**Verdict** — `NO-GO PURGE`

---

## 1. Environnement

```text
APP_ENV      local
Base         iboa_erp
Branche      release/preproduction-hardening
Hash         80c1bfd505fd55d141659a7cbda5d4280c0056d5
Git          246 entrées non commitées
```

Le nom de la base ne contient **ni `test`, ni `testing`, ni `qa`, ni `ci`**. Toute
opération destructive y est donc refusée par la règle d'engagement, quel que soit
le contenu constaté plus bas.

---

## 2. Constat déterminant — il n'existe aucun tiers réel

| Référentiel | Lignes | Portant un marqueur de test | Sans marqueur |
|---|---:|---:|---:|
| `clients` | 5 | 5 | **0** |
| `suppliers` | 1 | 1 | **0** |
| `employees` | 1 | 1 (`EMP-TEST-001`) | **0** |

```text
CLI-TEST-GUIDE    CLIENT TEST GUIDE SARL     is_active = 1
CLT-TEST-COMPTANT                            is_active = 1
CLT-TEST-ACOMPTE                             is_active = 1
CLT-TEST-CREDIT                              is_active = 1
CLT-TEST-EXONERE                             is_active = 1
FOUR-00003        Fournisseur Test SARL      is_active = 1
EMP-TEST-001      OUEDRAOGO Issouf           actif
```

Une seule société existe (`id 1`, OA METAL INDUSTRIE). **Il n'y a donc aucune
séparation entre un périmètre de test et un périmètre réel** : les jeux d'essai
ont été saisis dans l'entité de production elle-même.

Ce constat ne dit pas « toute la base est une base de test ». Il dit précisément
ceci : *aucune écriture transactionnelle constatée n'est rattachée à un tiers
réel*. Le paramétrage et les référentiels, eux, sont bien réels et restent hors
périmètre de purge.

---

## 3. Inventaire transactionnel, croisé avec le tiers

Chaque document a été rattaché à son tiers ; aucun classement ne repose sur le
seul libellé.

### Ventes — 100 % rattachés à un client de test

| Document | N° | Statut | TTC | Client |
|---|---|---|---:|---|
| Devis | DEV-2026-00001 → 00004 | converti / brouillon | 61 360 | `CLI-TEST-GUIDE` |
| Devis | DEV-2026-00005, 00006 | converti | 472 000 | `CLT-TEST-COMPTANT` |
| Commande | CMD-2026-001 → 005 | annulé / facturé / brouillon | 127 440 | `CLI-TEST-GUIDE` |
| Commande | CMD-2026-006, 007 | confirmé / en préparation | 472 000 | `CLT-TEST-COMPTANT` |
| Facture | FA-2026-001, FA-2026-002 | **payée** | 23 600 | `CLI-TEST-GUIDE` |
| Avoir | AV-2026-001 | validé | 4 720 | `CLI-TEST-GUIDE` |
| Bon de livraison | BL-2026-001, 002 | validé | — | `CLI-TEST-GUIDE` |
| Bon de préparation | 12 pièces | chargé / en attente | — | *voir §5* |

`CMD-2026-001`, `003` et `005` portent déjà un `deleted_at` au 2026-07-29 :
une suppression **logique** a donc déjà été pratiquée sur ce périmètre.

### Achats — 100 % rattachés au fournisseur de test

| Document | N° | Statut | TTC |
|---|---|---|---:|
| Demande d'achat | DA-2026-001, 002 | converti | — |
| Commande d'achat | CA-2026-001 | facturé | **0** |
| Commande d'achat | CA-2026-002 | facturé | 17 700 |
| Facture fournisseur | FF-2026-001, 002 | payée | 35 400 |

`CA-2026-001` porte un TTC nul tout en étant au statut « facturé ». Anomalie à
instruire séparément ; elle ne relève pas de la purge.

`goods_receipts` : **table absente du schéma.**

### Production

| N° | Statut | Commande | Article |
|---|---|---|---|
| OF-2026-0001 | annulé | 14 | `TEST-GUIDE-TBC27` |
| OF-2026-0003 | annulé | 16 | `PFN_TBC_ORA_0.27` |
| OF-2026-0007 | **lancé** | 19 | `PFN_TBC_BEI_0.27` |
| OF-2026-0008 | brouillon | 20 | `PFN_TBC_BEI_0.27` |

`OF-2026-0007` est **lancé** : il a consommé de la matière et engagé du stock.
Sa suppression ne peut pas être un simple `DELETE`.

### Comptabilité — 15 écritures, dont 14 **validées**

Toutes les écritures référencent un document de test. Une seule exception :

```text
OD-2026-0001   PAIE-2026-7   Paie de Juillet 2026 — 1 employé(s)   brouillon   301 600
```

Ce run de paie porte sur l'unique employé, lui-même marqué test.

**Point bloquant SYSCOHADA.** Quatorze écritures sont au statut `valide`. Une
écriture comptable validée ne se supprime pas : elle **s'extourne**. La table
possède déjà `reverses_entry_id` et `reversed_by_entry_id`. Toute purge qui
effacerait ces écritures détruirait la piste d'audit au lieu de la corriger.

### Trésorerie

```text
ENC-2026-001  18 880   client 16   confirmé
ENC-2026-002   4 720   client 16   confirmé
ENC-2026-003 100 000   client 20   confirmé
DEC-2026-001  17 700   fourn. 9    confirmé
DEC-2026-002  17 700   fourn. 9    confirmé
```

Ces règlements sont **confirmés** et rapprochés d'écritures validées. `clients`
et `suppliers` sont protégés par des contraintes `RESTRICT` posées depuis
`client_payments`, `invoices`, `credit_notes`, `supplier_payments`,
`supplier_invoices`, `purchase_orders` et `supplier_returns` : **aucun tiers ne
peut être supprimé tant que ses règlements existent.** La base refusera d'
elle-même, et c'est le comportement voulu.

### Stock — 37 mouvements, 31 lots

Deux dépôts sont explicitement de test et concentrent l'essentiel des quantités :

```text
DEP-MP-TEST   Dépôt Matières Premières — TEST
DEP-PF-TEST   Dépôt Produits Finis — TEST
```

Mais **deux reliquats subsistent dans des dépôts RÉELS** :

| Article | Quantité | Dépôt |
|---|---:|---|
| `BOB_TBC_PRE_BEI_0.27` | 2,7548 | `DEP-MP` |
| `TEST-GUIDE-TBC27` | 2,0000 | `DEP-PF` |

Ce sont des résidus de consommation et de fabrication de test posés dans le stock
d'exploitation. Ils faussent la valorisation réelle et doivent être régularisés
par un **mouvement d'ajustement tracé**, jamais par une mise à zéro directe.

Aucun `product_stocks` ni `stock_movements` orphelin d'article.

---

## 4. Ce qui n'est PAS de test et sort du périmètre

- **Articles** — 28 lignes, dont une seule marquée test (`TEST-GUIDE-TBC27`,
  statut à trancher par [BUG-A3-MASTER-DATA-SHEET-021](BUG-A3-MASTER-DATA-SHEET-021.md)).
  Les 27 autres sont le référentiel produit réel.
- **Dépôts** — 12, dont 2 marqués test. Aucun ne sera supprimé (interdit §1).
- **Utilisateurs** — 21 comptes. Les 20 premiers sont les rôles d'exploitation.
  Le 21ᵉ, `Aurore Barbier / salmon.arnaude@example.org`, porte la signature d'une
  factory Faker. **Signalé, non supprimé** — la suppression d'utilisateurs est
  interdite §1.
- Comptes, journaux, taxes, banques, caisses, permissions : paramétrage réel.

---

## 5. Anomalies découvertes pendant l'audit

Ces points sont des découvertes de l'audit, pas des candidats à la purge.

### 5.1 Neuf bons de préparation violent une contrainte de clé étrangère

```text
BP-2026-0024 → order_id 1     BP-2026-0029 → order_id 8
BP-2026-0025 → order_id 2     BP-2026-0030 → order_id 9
BP-2026-0026 → order_id 5     BP-2026-0032 → order_id 11
BP-2026-0027 → order_id 6     BP-2026-0033 → order_id 13
BP-2026-0028 → order_id 7
```

Aucune de ces commandes n'existe, ni physiquement ni en suppression logique. Or
`bon_preparations_order_id_foreign` est déclarée **`ON DELETE CASCADE`** : la
suppression d'une commande aurait emporté son bon de préparation. Ces orphelins
ne peuvent donc pas provenir d'un `DELETE` contraintes actives.

La cause reste à instruire — elle n'est pas déductible de l'état constaté. Les
hypothèses compatibles sont l'insertion sous `FOREIGN_KEY_CHECKS = 0` ou une
recréation de table. **Je ne tranche pas.** Le fait vérifié est celui-ci : la
base contient neuf violations actives d'une contrainte déclarée, et
`foreign_key_checks` vaut bien `1` en session comme en global.

### 5.2 Les séquences documentaires sont en avance sur les documents

| Type | Dernier n° attribué | Documents présents | Écart |
|---|---:|---:|---:|
| `bon_preparation` | 38 | 12 | **26** |
| `ecriture_comptable` | 18 | 15 | 3 |
| `ordre_fabrication` | 8 | 4 | 4 |
| `devis` | 6 | 6 | 0 |
| `commande` | 7 | 7 | 0 |

Aucune écriture n'est en suppression logique (`deleted_at` : 0 ligne). Les écarts
correspondent donc à des documents **physiquement absents**. La réinitialisation
des séquences est explicitement soumise à validation (§1) et n'est pas proposée
ici.

### 5.3 Deux exercices portent `is_current = 1`

```text
id 1   « 2026 »   2026-01-01 → 2026-12-31   ouvert   is_current = 1
id 2   « DBG »    2026-01-01 → 2026-12-31   ouvert   is_current = 1
```

L'exercice courant est ambigu. Selon l'ordre de tri de la requête qui le résout,
un document peut être rattaché à l'un ou à l'autre. À instruire avant toute
purge, car le rattachement d'exercice conditionne le périmètre.

Aucun exercice n'est clôturé : la protection « période comptable close » ne joue
donc nulle part, et ne protège rien.

---

## 6. Cartographie des cascades — indispensable avant toute suppression

Supprimer une ligne n'emporte pas qu'elle. Relevé exhaustif des `ON DELETE
CASCADE` sur le périmètre concerné :

| Supprimer… | …emporte silencieusement |
|---|---|
| une **commande** | `order_items`, `bon_preparations` |
| un **devis** | `quote_items` |
| une **facture** | `invoice_items`, `client_payment_schedules` |
| un **avoir** | `credit_note_items` |
| une **écriture** | `journal_entry_lines` |
| un **ordre de fabrication** | **10 tables** : `production_batches`, `_consumptions`, `_costs`, `_order_lines`, `_order_operations`, `_outputs`, `_quality_controls`, `_time_logs`, `_trackings`, `_wastes` |
| un **client** | `client_addresses`, `_contacts`, `_interactions`, `_tax_rates`, `commissions`, `credit_decisions`, `product_price_tiers`, `product_promotions` |
| un **fournisseur** | `supplier_addresses`, `_contacts`, `_purchase_conditions`, `rfq_suppliers` |
| un **article** | `product_attribute_values`, `product_components`, `product_price_tiers`, `product_promotions`, `product_sites`, `product_stocks`, `product_warehouse`, `stock_losses` |

Une purge écrite sans cette carte détruirait des données hors de son périmètre
annoncé, **sans le moindre message**. C'est la raison principale du verdict.

---

## 7. Verdict

```text
NO-GO PURGE
```

Motifs, chacun suffisant à lui seul :

1. **Aucune sauvegarde vérifiée n'a été produite.** La mission l'exige (§3) avant
   toute suppression. Rien n'a été sauvegardé ni restauré à blanc à ce stade.
2. **Le nom de la base ne contient aucun marqueur autorisé** (`test`, `testing`,
   `qa`, `ci`). La règle d'engagement refuse l'opération destructive.
3. **Quatorze écritures comptables sont validées.** Elles s'extournent, elles ne
   se suppriment pas.
4. **Neuf orphelins violent déjà une contrainte** : l'intégrité référentielle est
   rompue avant même la purge. Purger sur un socle incohérent produit un résultat
   incohérent.
5. **L'exercice courant est ambigu** : le périmètre ne peut pas être défini de
   façon déterministe.

---

## 8. Ce que je propose de faire ensuite, dans cet ordre

Rien de destructif dans les trois premières étapes.

```text
1. Instruire les 9 bons de préparation orphelins        (cause, pas symptôme)
2. Trancher l'exercice courant (« 2026 » vs « DBG »)
3. Produire une sauvegarde ET vérifier sa restauration sur une base distincte
4. Décider la STRATÉGIE avec le métier — voir ci-dessous
5. Écrire a3:purge-test-data en mode --dry-run d'abord
6. Rejouer la suite MySQL après purge
```

### La question à trancher par le métier

L'audit établit qu'**aucune donnée transactionnelle réelle n'existe** dans
`iboa_erp`. Deux stratégies opposées deviennent alors possibles, et le choix
n'est pas technique :

| | Purge sélective | Remise à zéro du transactionnel |
|---|---|---|
| Principe | supprimer pièce par pièce, en respectant l'ordre des dépendances | vider tout le transactionnel, conserver intégralement le paramétrage |
| Volume | ~120 lignes réparties sur 25 tables | idem, mais sans arbitrage pièce par pièce |
| Risque | chaque cascade doit être anticipée | périmètre plus lisible, mais irréversible en bloc |
| Séquences | à repositionner au cas par cas | repartent à 1, ce qui rend l'exercice cohérent |
| Écritures validées | extourne obligatoire | même contrainte |

Mon avis : la **remise à zéro du transactionnel avec conservation du
paramétrage** est plus sûre ici, parce qu'elle ne demande pas de trancher pièce
par pièce ce qui est test ou non — question déjà tranchée globalement par le §2.
Mais elle reste irréversible et sort du périmètre que vous avez autorisé.

**Je ne l'exécute pas. Aucune donnée n'a été modifiée. J'attends votre arbitrage.**
