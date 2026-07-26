# Rapport de tests Production

Date : 25 juillet 2026
SHA de base : `6976609cd6320cce9a99bb16703c1d3e107524cf` ; correctifs courants non commités.

| Vérification | Résultat |
|---|---|
| Syntaxe PHP fichiers du lot | OK |
| Tests ciblés initiaux Production/Qualité/Paie/stock | 17 réussis, 54 assertions |
| Suite SQLite complète après correctifs | 884 réussis, 3 074 assertions, 285,64 s |
| Nouveaux tests audit unités SQLite | 2 réussis, 2 assertions |
| Nouveaux tests audit unités MySQL | 2 réussis, 2 assertions, 175,85 s |
| Audit Production | propre |
| Audit base | propre |
| Audit sécurité | propre en local |
| Audit schéma | propre après 3 migrations |
| Audit bobines/lots | 0 anomalie ; 0,0048 KG sous tolérance |
| Audit métier | 19 contrôles réussis |
| Synchronisation dry-run | aucune correction |
| Parcours navigateur | ouverture 24/24 prouvée ; actions métier E2E non prouvées |

## Tests ajoutés

`tests/Feature/AuditBusinessUnitsTest.php` prouve :

- exclusion des écritures brouillon du rapprochement des soldes ;
- comparaison stock/mouvements dans l’unité de stock (`quantity_in_stock_uom`) ;
- exécution identique SQLite/MySQL.

## Reste obligatoire avant push

Suite SQLite complète après correctifs, suite MySQL complète, commande de parité, tests de concurrence, audits base/sécurité/schéma/stock/comptabilité, `git diff --check`, puis arbre Git propre. Aucun push n’a été effectué.

## Requalification

Statut MySQL complet : **NON EXÉCUTÉ**. Synchronisation intermodules : **PARTIELLEMENT PROUVÉE**. Snapshots BOM/gammes, certificats QR/SHA et mode tablette : **INCOMPLETS / NON PROUVÉS**. Décision actuelle : **NO-GO staging et ERP global**.