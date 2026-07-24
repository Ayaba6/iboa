# Rapport consolidé — Certification E2E B/D/E/F + suite MySQL

> Produit le 24/07/2026, branche `fix/erp-cdc-prod-compta-rh`.
> Répond à l'ultimatum du 23/07 : parcours complets, MySQL réel, attendus
> indépendants, audit des tests, rapport unique.
> **Formulation** : aucun « risque nul »/« couvert de bout en bout » ci-dessous.

## 1. Anomalies découvertes pendant les parcours réels + causes racines

| # | Anomalie | Cause racine | Gravité | Corrigée |
|---|---|---|---|---|
| 1 | Consommation bobine cassée en MySQL | `stock_lots.status` recevait `epuise`/`partiellement_consomme` hors ENUM → MySQL strict tronque (« Data truncated ») | **CRITIQUE** | ✅ enum aligné (`consomme`/`disponible`) |
| 2 | Chaîne du journal d'audit incohérente | hash calculé sur valeurs pré-persistance ; Carbon se relit au format SQL → hash divergent sur les logs « updated » | ÉLEVÉ | ✅ hash recalculé sur valeurs rechargées (`AuditService`) |
| 3 | Réservation fantôme (résa > stock) | OF clôturé APRÈS livraison → auto-réservation d'un PF déjà parti | ÉLEVÉ | ✅ garde livré/facturé/annulé + plafond reliquat |
| 4 | **Coût des ventes faux** | `snapshotItemCosts` figeait sur `products.weighted_avg_cost` (jamais maintenu, =0) puis prix catalogue → sortie 593 136 au lieu de 48 000 CMP | **CRITIQUE** | ✅ cascade sur CMP réel `product_stocks.avg_cost` |
| 5 | Table `personal_access_tokens` jamais migrée | migration Sanctum absente → API 500 au 1er token | **CRITIQUE** | ✅ migration ajoutée (session antérieure) |
| 6 | FK `audit_logs.user_id` bloquait la suppression de compte | `nullOnDelete()` appelé avant `constrained()` | MOYEN | ✅ migration corrective |

Toutes découvertes par des parcours réels (UI navigateur ou tests MySQL),
aucune par relecture seule. Les 3 CRITIQUES (1, 4, 5) auraient faussé le
stock, la marge comptable ou rendu l'API inutilisable en production.

## 2. Parcours E2E — scénarios exécutés (SQLite ET MySQL)

### B — Vente MTS (`E2eParcoursBMtsTest`, 6 scénarios)
Stock 20 à CMP 6 000. Nominal : réservation 8 → livraison (stock 12, résa
consommée) → sortie **48 000 = 8×6 000 CMP** → facture 80 000 → 2 règlements
30 000+50 000 → payée, solde client 0, caisse 80 000, **6031 débité 48 000**.
Non-nominaux : concurrence (2e commande plafonnée au disponible), livraison
partielle 5/8, refus livraison > stock (0 mouvement), annulation avant sortie
(résa libérée), permissions minimales (lecteur → 403 sur validation BL).

### D — Retour client (`E2eParcoursDRetourTest`, 3 scénarios)
Dispositions mixtes : 2 restock (**vendable +2**), 1 quarantaine (**dépôt QUAR,
non vendable**), 1 rebut (**rien**) ; avoir 20 000 ; imputation → facture
30 000. Remboursement réel : refus sans provision, caisse 20 000→5 000,
**GL D411/C571 équilibrée 15 000**, double remboursement refusé, audit
journalisé. Annulation d'avoir appliqué : stock ressorti + facture restaurée.

### E — Production avec anomalie (`E2eParcoursEProductionTest`, 5 scénarios)
Sur-consommation (12 kg/10) refusée, bobine intacte. Multi-bobines 6+4 kg :
restants exacts 4 et 4, conso 10 kg. Clôture avec écart 3/5 : refus sans
dérogation, acceptée avec `force`. Clôture sans visa chef d'équipe : refusée.
**QC non conforme : livraison BLOQUÉE même avec 5 PF en stock** (jamais
vendable sans décision qualité).

### F — Paie (`E2eParcoursFPaieTest`, 3 volets + `PayrollProfilesTest` 8 profils)
Maker-checker strict : préparateur ne valide pas son run, un autre RH le
valide. Confidentialité inter-salariés : A voit son bulletin (200), 403 sur
celui de B. Immuabilité barème : run validé garde son net figé (snapshot)
après changement SMIG/CNSS. Profils : HS, absence, prêt, avance, plafond CNSS
(44 000 exact), charges famille (−12 % IUTS).

**Attendus** : tous calculés indépendamment (arithmétique commentée), jamais
par le moteur testé — c'est cette règle qui a révélé le bug #4 (marge).

## 3. Suite MySQL obligatoire

- `phpunit.mysql.xml` : base DÉDIÉE `iboa_erp_test` (jamais la base réelle).
- `composer test:mysql-critical` : 20 suites critiques (stock, bobines,
  réservations, achats, réconciliation SYSCOHADA, allocations, avoirs, paie,
  sécurité, production, révisions, webhooks, API, E2E B/D/E/F + order-to-cash).
- **Séquence pré-push imposée** : SQLite verte → MySQL critique verte →
  3 audits → push. Plus aucun push dans le même bloc que la vérification.

## 4. Résultats de vérification (au dernier commit, `74b5cf3`)

| Vérification | Commande | Environnement | Résultat |
|---|---|---|---|
| Suite complète | `pest --parallel` | SQLite :memory: | 833 passed / 2 871 assertions / 0 échec |
| Suite complète MySQL | `pest -c phpunit.mysql.xml` | MySQL 8.4.3 `iboa_erp_test` | 816 passed / 0 échec (751 s) |
| Critique MySQL | `composer test:mysql-critical` | MySQL 8.4.3 | 152 passed / 599 assertions / 0 échec |
| Audit base | `a3:audit-database` | MySQL `iboa_erp` | 10 sections, 0 anomalie (~2,4 s) |
| Audit sécurité | `a3:audit-security` | MySQL `iboa_erp` | 8 sections (dont chaîne journal), 0 anomalie (~1,9 s) |
| Audit schéma | `a3:audit-schema` | MySQL `iboa_erp` | 4 sections, 0 dérive (~2,4 s) |

## 5. Audit des tests eux-mêmes

Scan des 179 fichiers. Faux positifs écartés : `assertSee`, `assertExitCode`,
`assertForbidden`, `Notification::assertSentTo` SONT des assertions réelles.
**Seul `InvoiceColumnsConfigTest` était réellement faible** (rendait un PDF
sans vérifier que la config déterminait les colonnes) → renforcé : la
résolution des colonnes est désormais assertée (minimal = 3 exactes sans
référence ; full = référence en tête ; défaut = 8 colonnes). 4 → 13 assertions.
Le bug #4 confirme que la règle « attendus indépendants » a été appliquée et
qu'elle attrape les défauts que le moteur seul masque.

## 6. Écritures comptables et mouvements vérifiés

- Vente MTS : D 6031 / C 311x au CMP réel (bug #4).
- Retour : avoir D 7085 / C 411 ; remboursement D 411 / C 571 équilibré.
- Réconciliation (test dédié) : GL = soldes comptes, balance équilibrée,
  auxiliaire 411 = restes à payer, résultat = ventes − avoirs.
- Perte transit : D 6097 / C 3111.
- Mouvements stock : sortie BL au CMP, retour restock/quarantaine ciblé,
  rebut = aucun mouvement, réservations plafonnées au disponible.

## 7. Permissions vérifiées

161 permissions / 21 rôles. Séparation effective (retraits sur
caissier/commercial/acheteur/magasinier). Maker-checker 7 points fail-closed.
Bénéficiaire (prêts/avances). Confidentialité inter-salariés (portail).
`a3:audit-security` : aucun conflit, aucun compte sans rôle, chaîne du journal
intègre.

## 8. Risques résiduels (formulation rigoureuse)

- **Concurrence multi-processus réelle** : PROUVÉE le 24/07 —
  `scripts/concurrency-test.sh` lance 5 processus OS parallèles (verrous
  InnoDB) se disputant un stock de 10, chacun réservant 3 (demande 15) :
  résultat **3 OK + 2 refus, réservé 9 ≤ 10, aucune survente**. Le
  `lockForUpdate` sérialise correctement. Reste non couvert : charge
  soutenue (centaines de req/s) et contention sur d'autres agrégats.
- **Volumes** : mesurés jusqu'à 1 000 écritures / 300 factures / 100 salariés ;
  au-delà non mesuré.
- **UI navigateur** : parcours A et C cliqués intégralement ; B/D/E/F prouvés
  au niveau service+HTTP, pas cliqués écran par écran.
- **Exploitation** (backup/restore, queues, headers) : NON couverte (Phase 2.7).
- **Reproductibilité PDF** (archivage + hash) : socle conçu, NON implémenté.

## 9. Fonctionnalités encore incomplètes

- Sélecteur de lot dans l'écran de préparation BL (back-end livré, UI à faire).
- Connecteur d'intégration fiscale (socle `fiscal_transmissions` prêt, aucun
  flux branché — l'administration burkinabè n'expose pas encore de flux).
- Seuils d'approbation par agence/dépôt (mono-site actuellement).

## 10. Décision de préparation à la production

**NON PRÊT pour un GO production inconditionnel.** Les chaînes commerciales
(vente MTO/MTS, achat, retour, production, paie) et leur comptabilité sont
prouvées sur MySQL réel avec attendus indépendants ; les 3 anomalies critiques
sont corrigées et couvertes par des tests de non-régression MySQL. Restent
bloquants pour un GO : **Phase 2.7 exploitation** (sauvegarde/restauration
documentée, durcissement) et **Phase 2.8 reproductibilité documentaire**. La
concurrence réelle et les volumes de production méritent un test de charge
avant bascule. Recommandation : **GO STAGING** (recette réelle), GO PRODUCTION
conditionné à 2.7 + 2.8 + un test de charge.
