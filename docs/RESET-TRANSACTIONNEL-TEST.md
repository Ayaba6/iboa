# REMISE À ZÉRO DU TRANSACTIONNEL DE TEST — RAPPORT FINAL

**Base** — `iboa_erp` (MySQL 8.4.3) — **Environnement** — développement
**Exécuté le** — 2026-08-04
**Verdict** — `RESET EXÉCUTÉ ET VÉRIFIÉ`

---

## 1. Références

```text
Commit de la commande   b622ea5cb5ddf92b146efba3a366113c674cb5dd
Parent                  d34ad60 (baseline réparée)
Branche                 feat/reset-test-transactions
Worktree                C:\laragon\www\iboa-reset-final
État Git                propre
Push                    aucun

Sauvegarde              iboa_erp_pre_full_reset_20260804_1201.sql
Chemin                  C:\laragon\backups\a3\
Taille                  1 080 868 octets
SHA-256                 475891854257c64770be611180829e6da57619d831f5f75b40b57e2f8a4b939a
Date                    2026-08-04 12:01:16
Contenu                 243 tables, données, routines, triggers, événements

Jeton utilisé           RESET-A3-FULL-TEST-77612c40bcafd16d
Durée du reset          12 secondes
Lignes supprimées       321 (+ 5 orphelins + référentiels)
```

---

## 2. Preuves réunies avant l'exécution

| Condition | Résultat |
|---|---|
| Suite de maintenance | **44 tests / 146 assertions / 0 échec** |
| Suite MySQL complète | **1 285 tests / 4 498 assertions / 0 échec** (242 fichiers, 4 434 s) |
| Commit isolé | 2 fichiers, `git status` vide |
| Sauvegarde restaurée | 243 tables, 2 698 lignes, **0 différence** |
| Essai `--full` sur `iboa_erp_final_restore_check` | réussi, 0 orphelin |
| Audit source recalculé | empreinte `77612c40bcafd16d` |
| Application figée | `php artisan down` actif pendant toute l'opération |

La comparaison de restauration a porté sur **les 243 tables**, avec une requête
générée sous `group_concat_max_len = 100000000`. Une première tentative, plus
tôt dans le chantier, avait produit un faux vert : tronquée à 1 024 octets, elle
ne comparait qu'une seule table.

---

## 3. Contrôles préalables, lus par la commande elle-même

```text
foreign_key_checks    = 1
base                  = iboa_erp
code exécuté          = b622ea5cb5ddf92b146efba3a366113c674cb5dd:6f627746a9d95864
empreinte du rapport  = 77612c40bcafd16d
sauvegarde référencée = 475891854257c64770be611180829e6da57619d831f5f75b40b57e2f8a4b939a
application figée     = oui
```

L'empreinte est **recalculée une seconde fois à l'intérieur de la transaction**,
avant la première suppression. Un écart y vaut `RESET REFUSÉ` et annulation.

---

## 4. Ce qui a été supprimé

| Domaine | Lignes |
|---|---:|
| Ventes | 85 |
| Stock | 84 |
| Comptabilité | 61 |
| Qualité et production aval | 31 |
| Trésorerie | 18 |
| Achats | 16 |
| Paie et RH transactionnel | 6 |
| Immobilisations | 7 |
| CRM | 3 |
| Contrats commerciaux | 4 |
| Historique métier | 6 |
| **Total** | **321** |

### Comptabilité, photographiée avant suppression

```text
écritures        15   dont 14 validées
lignes           39
débit       575 753
crédit      575 753
équilibre   ÉQUILIBRÉ
```

### Référentiels et objets de test

```text
5 clients de test        supprimés
1 fournisseur de test    supprimé (avec son contact, dépendance CASCADE)
1 employé de test        supprimé
TEST-GUIDE-TBC27         supprimé, avec sa nomenclature et sa gamme dédiées
exercice DBG             is_current = 0 puis supprimé
5 orphelins              4 category_warehouse + 1 payroll_items
25 séquences             repositionnées au premier numéro
```

`BOB_TBC_PRE_BEI_0.27` a été **conservé** : c'est un composant réel du
paramétrage. Seuls ses mouvements et stocks de test ont disparu.

---

## 5. Contrôles après exécution

### Transactionnel — tout à zéro

```text
devis, commandes, BP, BL, factures, avoirs, paiements clients        0
DA, commandes fournisseurs, réceptions, FF, retours, paiements       0
OF, consommations, productions, réservations, mouvements, lots       0
écritures, lignes d'écriture, rapprochements, affectations, caisse   0
runs de paie, lignes de bulletin                                     0
immobilisations, amortissements                                      0
CRM (opportunités, activités, contacts), contrats                    0
clients, fournisseurs, employés de test                              0
TEST-GUIDE-TBC27, exercice DBG                                       absents
stock non nul, stock négatif, stock réservé                          0
exercices courants                                                   1
```

### Intégrité — contrôle exhaustif

```text
Orphelins sur les 905 clés étrangères   avant : 37     après : 0
```

Les 37 orphelins préexistants — 9 bons de préparation, 23 lignes de production,
4 `category_warehouse`, 1 `payroll_items` — ont tous disparu.

### Paramétrage conservé

| | |
|---|---:|
| Société | 1 |
| Utilisateurs / rôles / permissions | 21 / 21 / 178 |
| Articles réels | 27 |
| Catégories / familles / marques / unités | 11 / 45 / 3 / 17 |
| Dépôts | 12 |
| Plan comptable / journaux / taxes | 155 / 7 / 4 |
| Devises / modes de règlement / conditions | 1 / 5 / 8 |
| Nomenclatures / lignes / gammes / opérations | 8 / 8 / 8 / 32 |
| Machines / centres de travail / lignes | 11 / 10 / 6 |
| Rubriques de paie / constantes / IUTS / cotisations | 24 / 18 / 6 / 3 |
| Caisses / banques | 3 / 1 |
| Journaux d'audit | 317 |

---

## 6. POST-RESET-VALIDATION-001

Chaîne complète reconstruite sur une base neuve, **conservée en base**.

```text
Entrée de stock   10 unités de PFN_TBC_BEI_0.27
Devis             DEV-2026-00001
Commande          CMD-2026-001        35 400 TTC
Bon de livraison  BL-2026-001         validé
Facture           FA-2026-001         émise, 35 400 TTC
Encaissement      ENC-2026-001
Écritures         3, débit = crédit = 88 800, ÉQUILIBRÉ
Orphelins         0
```

Toutes les séquences repartent bien du premier numéro, et aucune référence ne
heurte l'ancien jeu.

### Trois refus de l'ERP, tous justes

Le scénario a buté sur trois gardes métier avant d'aboutir. Aucune n'était un
défaut :

1. **facturer avant de livrer** — refusé ; la facturation est limitée aux
   quantités livrées ;
2. **livrer sans stock** — refusé ; après la remise à zéro le stock vaut zéro,
   d'où l'entrée initiale ;
3. **encaisser sur une facture en brouillon** — refusé ; une facture non émise
   ne compte pas dans l'encours du client.

---

## 7. Erreurs commises pendant l'opération, et ce qu'elles ont appris

Trois fois, un résultat a semblé mauvais alors que le code était sain. Chaque
fois, la cause venait de la méthode, pas de la commande.

| Symptôme | Cause réelle |
|---|---|
| 114 échecs à la suite complète | `/public/build` est dans `.gitignore` : un worktree neuf n'a aucun asset compilé, et chaque page rendait un 500 (`Vite manifest not found`). Même piège que `vendor/`. |
| 333 échecs au run suivant | j'avais lancé l'essai `--full` **pendant** que la suite tournait dans le même worktree ; le `php artisan down` qu'il exige a fait répondre 503 à toutes les routes (263 occurrences). |
| Comparaison de restauration « sans différence » | `group_concat_max_len` à 1 024 tronquait la requête générée : la comparaison ne portait que sur une table. |

Un quatrième incident a joué en faveur du dispositif : à la première tentative
d'exécution sur copie, la garde de paramétrage a **annulé toute la transaction**
parce que la suppression de la nomenclature dédiée retirait une ligne de
`bills_of_materials` sans que la perte soit déclarée. La déclaration a été
ajoutée plutôt que la garde assouplie.

---

## 8. Zones grises — 26 tables conservées

Aucune n'était citée au périmètre. La commande les affiche et ne les touche pas ;
le défaut est de conserver.

```text
audit_logs (317)   notifications (84)   pay_rubrics (24)   sync_logs (14)
payroll_constants (18)   payroll_allowance_types (16)   leave_types (8)
machine_maintenances (8)   payroll_bareme_brackets (7)   iuts_brackets (6)
payroll_parameter_versions (7)   social_contributions (3)   …
```

---

## 9. Anomalies désormais sans objet

```text
BUG-A3-DATA-ORPHAN-PICKING-023              les 32 orphelins transactionnels ont disparu
BUG-A3-ACCOUNTING-MULTIPLE-CURRENT-FY-024   un seul exercice courant subsiste
```

La remise à zéro a fait disparaître les symptômes. Elle n'a **pas** corrigé leur
cause : rien n'empêche encore qu'un orphelin se reforme, ni qu'un second
exercice soit marqué courant. Ces deux points restent à traiter dans le code.

---

## 10. Ce qui reste ouvert

```text
1. Empêcher la réapparition des orphelins (contrainte ou service)
2. Garantir l'unicité de l'exercice courant au niveau du schéma
3. Arbitrer les zones grises si elles doivent un jour être purgées
4. Décider du sort de POST-RESET-VALIDATION-001 une fois l'ERP confirmé
```

`db:reset-transactional` reste présente et **dangereuse** : elle désactive les
contraintes et emploie `TRUNCATE`. Elle devrait être retirée au profit de
`a3:reset-test-transactions`.
