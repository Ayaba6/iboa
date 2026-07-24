# Parité des suites SQLite / MySQL — exclusions documentées

> `a3:test-parity` vérifie qu'aucun test n'est exclu SILENCIEUSEMENT d'une des
> deux suites. Les deux configs (`phpunit.xml`, `phpunit.mysql.xml`) pointent
> le même testsuite `tests/` — la couverture est donc identique par défaut.
> Toute exclusion doit figurer ci-dessous, sinon la commande échoue.

## Exclusions actives

| Fichier | Driver exclu | Raison |
|---------|--------------|--------|
| _(aucune)_ | | Les deux suites exécutent l'intégralité des fichiers de test. |

## Écart de COMPTE observé (833 SQLite vs 816 MySQL) — expliqué

L'écart n'est PAS une exclusion : c'est un **artefact temporel de mesure**.
Le run MySQL complet « 816 » a été lancé en arrière-plan (commit `a97017d`)
AVANT l'ajout de : E2eParcoursBMtsTest (6), E2eParcoursDRetourTest (3),
E2eParcoursEProductionTest (5), E2eParcoursFPaieTest (3),
AuditProductionCommandTest (3), DocumentArchiveTest (3), AuditChainIntegrityTest
(3), + renforts (InvoiceColumns). Soit ~26 tests ajoutés APRÈS la mesure.
`816 + 26 ≈ 842` ≈ compte SQLite courant. Un run MySQL complet au HEAD courant
doit matcher le compte SQLite (vérification jointe au rapport R2).

## Preuve de parité (24/07/2026)

Run MySQL complet au commit `4b53bed` (base `iboa_erp_test`, MySQL 8.4.3) :
**839 passed / 2 900 assertions / 0 échec** (380,9 s). SQLite au même
commit : **839 passed**. → **PARITÉ EXACTE, 0 test exclu**. L'écart antérieur
(833 vs 816) était une comparaison de deux mesures à des commits différents.
`a3:test-parity` garantit désormais qu'aucun test ne peut être exclu
silencieusement d'un driver.
