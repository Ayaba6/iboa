# Rapport pré-push — validation au SHA 84ca8e4

Date : 26 juillet 2026
Décision : **VALIDATION TECHNIQUE TRÈS AVANCÉE — NO-GO STAGING ET NO-GO PRODUCTION. PUSH INTERDIT.**

## 1. Identité Git

| Élément | Valeur |
|---|---|
| Branche | `fix/erp-cdc-prod-compta-rh` |
| SHA distant | `3af880ebbe74d7c444ea8ec0b88bf2427e82b676` |
| SHA applicatif vérifié | `84ca8e41f642c240292a9f3562fb462cb7ebabd5` |
| Avance | 20 commits |
| Diff global | 122 fichiers, +9 359 / -2 032 |
| Arbre avant rapport | propre |
| `git show --check` | 20/20 commits propres |
| Fichiers secrets/temporaires ajoutés | aucun détecté |
| Clés privées/tokens usuels détectés | aucun détecté |
| Chemin absolu Windows ajouté | aucun détecté |

### Revue des 20 commits

| SHA | Objet | Fichiers | Tests concernés | Migration | Risque / rollback |
|---|---|---:|---|---|---|
| `edd32f6` | Annulation/remboursement d'avoir et maker-checker | 4 | E2E retours | non | financier, extourne testée |
| `99a5c59` | Sortie explicite de quarantaine | 2 | QuarantineRelease | non | stock/qualité |
| `b61f54b` | Surconsommation valorisée, rebut et dérogation | 4 | variance, dérogation | non | production/comptabilité |
| `651d33b` | Concurrence déterministe lot 1 | 1 | ConcurrencyGuards | non | tests seuls |
| `c41a213` | Concurrence webhook/clôture caisse | 1 | ConcurrencyGuards | non | tests seuls |
| `9d7b98d` | Concurrence révision devis | 1 | ConcurrencyGuards | non | tests seuls |
| `6976609` | Audit bobines et règles de gestion | 15 | 5 fichiers ciblés | non | stock ; correction réversible tracée |
| `f558e4d` | Snapshots BOM/gamme et clôture atomique | 7 | OF, clôture | oui, additive | production ; rollback de colonne destructif |
| `8af2b29` | Isolement/libération des produits finis | 9 | qualité/stock PF | oui, additive | stock/qualité |
| `13a4875` | Régularisation du coût complet sans réécriture | 9 | comptabilité production | 3 | valorisation/GL |
| `4f603de` | Allocation et COGS multi-lots | 12 | allocation, stock, réceptions | oui | stock/COGS |
| `9ee3605` | Prix plancher et dérogation | 14 | ventes + unité | oui | blocage commercial |
| `e46635a` | Stocks sans valeur et fiabilité des audits | 41 | audits, stock, production | 4 | transversal ; historique conservé |
| `1ba018c` | Retours valorisés depuis allocations historiques | 8 | retours/avoirs | oui | stock/GL |
| `a6a4213` | Cycle de vie des dérogations | 4 | SalesFloorWaiver | oui | commercial/maker-checker |
| `ce68fa5` | Livraison réelle avant retour | 1 | VentesAcceptance | non | tests seuls |
| `84d3115` | Nettoyage documentaire | 2 | aucun | non | nul |
| `a9b5f67` | Audit allocations et concurrence multi-lots | 5 | audit allocations, stock | non | outils de preuve |
| `4e188d4` | SHA dans le rapport concurrence bobines | 1 | script concurrence | non | preuve seule |
| `84ca8e4` | Refus de revalidation d'un BL annulé | 1 | script concurrence multi-lots | non | preuve seule |

Entre `84d3115` et `84ca8e4`, trois commits ont ajouté l'audit des allocations, le script multi-processus et ses gardes. Le passage à 906 tests provient des nouveaux tests d'audit/allocation collectés dans l'arbre courant ; aucune exclusion n'est appliquée.

## 2. Tests

| Contrôle | SQLite | MySQL | SHA | Exit |
|---|---:|---:|---|---:|
| Tests collectés | 906 | 906 | `84ca8e4` | 0 |
| Suite complète | 906 / 3 191 assertions | 906 / 3 192 assertions | `84ca8e4` | 0 / 0 |
| Durée | 491,29 s | 710,74 s | `84ca8e4` | 0 / 0 |
| Parité des identifiants | 906 | 906 | `84ca8e4` | 0 |
| Exclusions | 0 | 0 | `84ca8e4` | 0 |
| Concurrence applicative déterministe | 9 / 27 assertions | 9 / 27 assertions | `84ca8e4` | 0 |

La suite MySQL a été exécutée une seule fois de bout en bout avec un délai de 30 minutes. Résultat final : `906 passed (3192 assertions)`, durée `710.74s`, code global `0`. Pest ne rapporte aucun échec, erreur, skipped, incomplete ou risky.

## 3. Concurrence multi-lots réelle

Commande : `php scripts/delivery-lot-concurrency-test.php`.

| Scénario | Modèle | Résultat |
|---|---|---|
| Dernier reliquat | 2 processus PHP / 2 PDO | un validé, un rollback stock insuffisant |
| FIFO multi-lots | 2 processus PHP / 2 PDO | 10 unités, 3 allocations, COGS 2 050 exact |
| Validation contre annulation | 2 processus PHP / 2 PDO | annulation gagnante, revalidation refusée |
| Retour contre livraison | 2 processus PHP / 2 PDO | retour 2 en quarantaine, coût 600 conservé |
| Deadlock et retry | 2 processus PHP / 2 PDO | deadlock MySQL réel, un worker en 2 tentatives, 2 commits |
| Erreur après allocation | 1 processus PHP | rollback intégral, aucun mouvement partiel |

Résultat : 6/6 scénarios, code 0, rapport JSON portant le SHA complet. Limite : le script emploie un schéma MySQL dédié reproduisant les invariants ; les 9 tests Laravel complètent la preuve applicative mais ne sont pas des processus OS indépendants.

## 4. Audits chiffrés

Base : `iboa_erp`, connexion MySQL locale.

| Audit | Commande | Contrôles / périmètre | Anomalies | Avertissements | Exit |
|---|---|---:|---:|---:|---:|
| Sécurité | `a3:audit-security` | maker-checker, rôles, comptes, permissions, audit | 0 | 1 : maker-checker inactif en local | 0 |
| Schéma | `a3:audit-schema` | 4 familles | 0 | 0 | 0 |
| Production | `a3:audit-production` | environnement, sécurité, exploitation | 0 bloquante | `APP_DEBUG=true` hors production | 0 |
| Métier global | `audit:business` | 19 | 0 | 0 | 0 |
| Stock | `erp:check-stock` | 5 | 0 | 0 | 0 |
| Comptabilité | `erp:check-accounting` | 6 | 0 | 0 | 0 |
| Bobines/lots | `stock:audit-coil-lots --dry-run` | 1 groupe, 2 bobines, 2 lots, 8 mouvements | 0 | 2 mouvements sans UOM | 0 |
| Stocks non valorisés | `a3:audit-unvalued-stock` | lots et bobines physiques | 2 objets liés | stock bloqué visible | 1 attendu |
| Allocations COGS | `a3:audit-delivery-allocations` | 2 lignes BL, 0 allocation historique | 0 | 2 BL historiques sans allocation prouvée | 0 |
| Réservations | inclus dans `erp:check-stock` et `audit:business` | sur-réservation + cohérence | 0 | 0 | 0 |

## 5. Exceptions historiques

### Écart quantitatif sous tolérance

| Champ | Valeur |
|---|---|
| Article | `BOB_TBC_PRE_BEI_0.27` — Bobine prélaquée beige 27/100 |
| Dépôt | `DEP-MP` |
| Bobines / lots | 2 / 2 |
| Physique bobines/lots | 2,7500 KG |
| Stock/mouvements | 2,7548 KG |
| Écart | 0,0048 KG |
| Seuil | 0,0100 KG absolu |
| Valeur théorique estimée | 17,0097 XOF |
| Cause probable | historique de conversion et régularisation antérieure |
| Traitement | aucune correction candidate ; surveillance |
| Validation métier/comptable | non fournie |
| Responsable | non affecté |
| Statut | exception maîtrisée quantitativement, validation formelle manquante |

### Stock historique non valorisé

| Champ | Valeur |
|---|---|
| Lot | `LOT-REC-2026-001-1` |
| Bobine | `BOB-REC-2026-001-01` |
| Article | `BOB_TBC_PRE_BEI_0.27` |
| Dépôt | `DEP-MP` |
| Quantité | 2,5000 KG |
| Coût / valeur | 0 / indéterminée |
| Cause | coût historique absent |
| Responsable | non affecté |
| Statut | `valorisation_manquante`, blocage actif, audit en échec attendu |

Le lot reste dans l'inventaire physique et les audits. L'allocation/BL bloque le coût nul ; la consommation et la découpe de bobine exigent une valorisation définitive et un coût positif ; la clôture d'OF bloque une consommation non valorisée. La preuve d'exclusion exhaustive du MRP et du consommateur FIFO/CMP générique reste incomplète : `product_stocks` agrège encore le physique et le chemin FIFO générique ne filtre pas explicitement `valuation_status`. Ce point reste bloquant.

Deux BL historiques (identifiants 12 et 13) ont des sorties sans allocation prouvée. Aucun backfill fictif n'est autorisé.

## 6. Migrations historiques

Les sauvegardes disponibles ne sont pas déclarées anonymisées. Deux restaurations locales temporaires ont néanmoins permis une sonde technique ; les bases temporaires ont été supprimées après essai.

| Source | Restauration | Migrations | Audit schéma | Audit métier | Rollback |
|---|---:|---|---|---|---|
| Dump du 12/07, pré-snapshots | 15,17 s | 279 → 347, exit 0 | propre | 2/19 contrôles en anomalie historique | échec |
| Dump du 22/07, avant nettoyage | 29,09 s | 319 → 347, exit 0 | propre | 2/19 contrôles en anomalie historique | échec |

Le rollback échoue dans les deux cas sur `2026_07_23_170000_rename_syscohada_accounts` : tentative de retour `4452 → 4432` en présence d'un compte `4432`, contrainte unique société/code. Il est donc destructif/non sûr sur historique. Les six profils anonymisés demandés, notamment volume, OF clôturés, quarantaines et retours, ne sont pas certifiés.

## 7. Parcours navigateur

L'ERP réel répond, la connexion avec le compte de démonstration fonctionne, et l'écran réel de création de devis a été ouvert. Les trois parcours requis ne sont pas certifiés :

| Parcours | Statut | Motif |
|---|---|---|
| A — refus sous prix plancher | non exécuté de bout en bout | aucune donnée de recette isolée dédiée |
| B — dérogation commerciale | non exécuté de bout en bout | nécessite deux utilisateurs et données isolées |
| C — marge positive complète | non exécuté | cycle long et base locale non isolée |

Les tests automatisés prouvent les gardes serveur et le cycle de dérogation, mais ne remplacent pas la preuve navigateur. Aucune donnée métier de recette n'a été ajoutée à la base réelle lors de cette inspection.

## 8. Décision

### Push

**REFUSÉ.** Les 20 commits sont mécaniquement propres, mais les bloqueurs de certification restent ouverts.

### Staging

**NO-GO.** Bloqueurs : trois parcours navigateur, six jeux historiques anonymisés, rollback historique non sûr, preuve exhaustive MRP/FIFO du stock non valorisé, validations métier/comptable des exceptions.

### Production

**NO-GO.** En plus des bloqueurs staging : recette utilisateur, sauvegarde/restauration, déploiement/rollback opérationnel, supervision, alertes, sécurité, performance, continuité, validations métier et comptable, période pilote.

## 9. Risques résiduels prioritaires

1. Rollback de migration SYSCOHADA en échec sur deux historiques.
2. Parcours navigateur métier non certifiés.
3. Jeux historiques disponibles non anonymisés et couverture des six profils incomplète.
4. Stock non valorisé visible et bloqué sur les principaux flux, mais exclusion MRP/FIFO générique non exhaustive.
5. Maker-checker inactif et `APP_DEBUG=true` dans l'environnement local ; ces réglages sont interdits en production.
6. Exceptions historiques sans responsable ni validation métier/comptable formelle.
