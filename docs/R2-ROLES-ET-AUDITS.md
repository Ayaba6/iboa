# R2 §5 + §7 — Matrice réelle des rôles, maker-checker, sections d'audit

> Extrait de la base réelle le 24/07/2026. Aucun chiffre estimé.

## Bases de données (clarification R2 §5)

| Base | Rôle | APP_ENV |
|---|---|---|
| `iboa_erp` | **base LOCALE de développement** (Laragon, poste dev) — sert aux 5 commandes d'audit en lecture seule | local |
| `iboa_erp_test` | base DÉDIÉE aux tests MySQL (`phpunit.mysql.xml`), reconstruite par `migrate:fresh` | testing |

**`iboa_erp` n'est PAS une base de production** : c'est la base de dev locale.
Aucune base de production n'existe encore (l'ERP n'est pas déployé). Les audits
sont en lecture seule (aucun `UPDATE`/`DELETE`). Les régularisations de données
(ex. réservation fantôme) ont été faites explicitement sur cette base de dev,
jamais automatiquement. **Aucun test destructif ni régularisation ne s'exécute
sur une base de production** — il n'y en a pas, et la règle est posée.

## §7 — Matrice effective des 21 rôles (après migration de séparation)

Colonnes = a-t-il au moins une permission du verbe. `X` = oui.
0 permission directe hors rôle. 0 utilisateur actif sans rôle. 1 super_admin actif.

| Rôle (users) | créer | valider | annuler | payer | rembourser | clôturer | rouvrir | nb perms |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|--:|
| super_admin (1) | X | X | X | X | X | X | X | 161 |
| directeur (1) | X | X | X | X | X | X | X | 159 |
| daf (1) | X | X | X | X | X | X | X | 55 |
| comptable (1) | X | X | X | X | X | X | X | 43 |
| directeur_usine (1) | X | X | X | . | . | . | . | 44 |
| responsable_commercial (1) | X | X | . | . | . | . | X | 35 |
| commercial (1) | X | . | . | . | . | . | . | 29 |
| acheteur (1) | X | X | . | . | . | . | . | 24 |
| magasinier (1) | X | X | . | . | . | . | . | 23 |
| responsable_stock (1) | X | X | X | . | . | . | . | 23 |
| chef_production (1) | X | X | X | . | . | . | . | 23 |
| caissier (1) | X | X | . | X | . | . | . | 13 |
| drh (1) | . | X | . | X | . | X | . | 15 |
| rh_manager (1) | . | X | . | X | . | . | . | 12 |
| responsable_qualite (1) | . | . | . | . | . | . | . | 14 |
| lecture_seule (1) | . | . | . | . | . | . | . | 22 |
| rh_agent (1) | . | . | . | . | . | . | . | 9 |
| chef_atelier (0) | . | X | . | . | . | . | . | 8 |
| technicien_maintenance (1) | . | . | . | . | . | . | . | 6 |
| operateur_production (1) | . | . | . | . | . | . | . | 4 |
| employe (1) | . | . | . | . | . | . | . | 1 |

### Conflits création↔validation détectés (analyse par opération concrète)

| Rôle | Conflit | Statut |
|---|---|---|
| magasinier, responsable_stock | `receptions.create` + `receptions.validate` | **COUVERT par maker-checker** : `reception.validate` est l'un des 7 points MC (fail-closed prod) — la MÊME personne ne peut valider sa propre réception. Cumul au niveau rôle acceptable (constat physique), altérité imposée à l'exécution. |
| comptable, daf, directeur | cumul créer+valider+annuler+… | **Rôles de CONTRÔLE assumés** : le cumul y est intentionnel (direction/supervision). L'altérité individuelle est imposée par le maker-checker sur les points financiers (décaissement, avoir, écriture, paie). |
| caissier | `payments.create` + `payments.confirm` (permissions) | **PAS un conflit à deux étapes** : vérifié dans le code, il n'existe aucune action `confirm` distincte — un encaissement caissier se saisit en UNE étape avec statut `confirme` (normal pour une caisse). Le contrôle financier réel sur la caisse est la **clôture journalière** (`cash_closure.validate`, point MC 6) : le caissier ne valide pas sa propre clôture. Et l'ANNULATION d'encaissement (`treasury.cancel`) lui a été retirée. Conflit réel : aucun. |

## §7 — Les 7 points maker-checker (sites d'appel réels de `assert()`)

Tous fail-closed en production (`config/security.php`, actif par défaut si APP_ENV=production) :

1. `decaissement.approve` — `SupplierPaymentService::approve`
2. `purchase_order.approve` — `PoApprovalService::approve`
3. `credit_note.validate` — `CreditNoteService::validate`
4. `journal_entry.validate` — `JournalEntryService::validate`
5. `payroll_run.validate` — `PayrollService::validate`
6. `cash_closure.validate` — `CashClosureService::validateClosure`
7. `reception.validate` — `ReceptionController::validateReception`

Plus le contrôle **bénéficiaire** (`assertNotBeneficiary`, TOUJOURS actif, sans
exemption) sur prêts et avances sur salaire.

**Limite reconnue (R2 §7)** : 7 points ne couvrent pas encore toutes les
opérations. NON couverts par MC à ce jour : `payments.confirm` (encaissement),
validation d'inventaire, validation d'ajustement de stock, clôture de période
comptable, sortie/cession d'immobilisation. À arbitrer avant GO production.

## §5 — Les 22+ sections d'audit détaillées

### a3:audit-database (`iboa_erp`, lecture seule, exit 1) — 10 sections
1. **Orphelins de clés étrangères** — ~28 paires enfant→parent : `WHERE col NOT NULL AND NOT EXISTS(parent)`. Anomalie = FK cassée.
2. **Doublons de références** — number/code sur 12 tables, insensible casse/espaces : `GROUP BY LOWER(TRIM) HAVING COUNT>1`.
3. **Données mal formées** — emails invalides, espaces parasites sur codes/noms.
4. **Cohérence financière** — écritures déséquilibrées (`ABS(Σdébit−Σcrédit)>0,01`), factures au solde incohérent, « payée » avec reste dû.
5. **Cohérence stocks** — stocks négatifs non autorisés, réservation > stock, réservations fantômes.
6. **Documents sans lignes** — factures/commandes/devis/CF/avoirs validés sans ligne.
7. **Trésorerie ↔ paiements** — encaissement caisse confirmé sans transaction, transaction orpheline.
8. **Bobines et lots** — poids restant > initial, poids négatif, lot à quantité négative.
9. **Dates et périodes** — échéance < émission, écriture validée APRÈS verrouillage de sa période.
10. **Paie** — bulletin sans run.

### a3:audit-security (`iboa_erp`, exit 1) — 8 contrôles
1. Maker-checker désactivé en production (CRITIQUE).
2. Conflits de séparation par rôle (paires create/validate hors rôles de contrôle).
3. Permissions critiques sans détenteur actif OU >10 détenteurs.
4. Super-admins actifs > 2.
5. Utilisateurs actifs sans rôle.
6. Permissions directes hors rôle.
7. Tokens API de comptes désactivés.
8. Intégrité de la chaîne du journal d'audit (`verifyChain`).

### a3:audit-schema (`iboa_erp`, exit 1) — 4 sections
1. Tables d'infrastructure exigées par la config active (Sanctum, queue/cache/session, audit).
2. Chaque modèle Eloquent concret → sa table existe.
3. Migrations enregistrées sans fichier / fichiers jamais exécutés.
4. Colonnes `$fillable` des 11 modèles financiers → colonnes réelles.

### a3:audit-production (env-dépendant, exit 1) — 7 contrôles
1. APP_DEBUG. 2. APP_KEY. 3. QUEUE non-sync en prod. 4. CACHE non-array.
5. Maker-checker actif. 6. HTTPS. 7. Chaîne du journal intègre.
+ avertissements : SESSION driver, tables d'infra.

**Comportement en cas d'anomalie** : les 4 commandes sont LECTURE SEULE (aucun
`UPDATE`/`DELETE`), affichent le détail par section, et sortent en **exit 1**
(intégrables CI/cron/pré-push). Aucune ne corrige automatiquement.

**Limite de la preuve** : chaque section teste la règle qu'elle code ; elle ne
prouve pas l'absence d'anomalies d'un type non modélisé.
