# AUDIT GLOBAL ERP A3

Reference de travail ouverte le 24/07/2026 pour l'ERP `A3 ERP` situe dans `C:\laragon\www\iboa`.

## Identite projet

- Depot : `https://github.com/rrodyz/iboa.git`
- Branche : `fix/erp-cdc-prod-compta-rh`
- SHA : `9d7b98dfd308a7aac11141263f6f60647a3d203e`
- Environnement : `local`
- PHP / Laravel / MySQL : `8.2.28` / `12.64.0` / `8.4.3`
- Base active : `iboa_erp`
- Routes / controleurs / services / modeles / migrations / tests : `1099 / 159 / 94 / 180 / 335 / 200`
- Roles / permissions : `21 / 161`

## Etat courant du lot 24/07/2026

- Tests cibles SQLite : `23 passed / 101 assertions`.
- Tests cibles MySQL : `23 passed / 101 assertions`.
- Suite complete SQLite : `879 passed / 3064 assertions / 1457.02 s / exit 0`.
- Suite complete MySQL : `879 passed / 3064 assertions / 216.45 s / exit 0`.
- Concurrence deterministe SQLite : `9 passed / 27 assertions`.
- Concurrence deterministe MySQL : `9 passed / 27 assertions`.
- Parite SQLite/MySQL : `200 tests`, `0 exclusion`, `PARITE OK`.
- `a3:audit-security` : `exit 0`, lecture seule, aucune anomalie detectee en local.
- `a3:audit-schema` : `exit 0`, lecture seule, aucune derive detectee.
- `erp:check-accounting` : `exit 0`, lecture seule, aucune anomalie detectee.
- `erp:check-stock` : `exit 0`, lecture seule, aucune anomalie detectee apres correction du calcul en unite de stock.
- `stock:audit-coil-lots --report=storage/logs/validation-20260724/audit-coil-lots-report.json` : `exit 0`, `1 groupe`, `0 anomalie`, `0 correction candidate`.

## Corrections de ce lot

- `app/Console/Commands/Erp/CheckStock.php` : le theorique stock utilise maintenant `quantity_in_stock_uom` quand elle existe.
- `app/Console/Commands/AuditCoilLots.php` : commande durcie avec `--run-id`, `--revert-run`, `--report`, confirmation explicite, rejeu idempotent, rapport JSON avant/apres et avertissement comptable.
- `app/Models/Product.php` : helper `isCoilManaged()` borne a un flag metier explicite et helper `hasLegacyCoilHistory()` pour l'audit/migration.
- `app/Models/ItemCategory.php` et `app/Http/Controllers/ItemCategoryController.php` : blocage du basculement `coil_managed` apres historique physique.
- `app/Modules/Production/Services/CoilReceptionService.php` : creation de bobines reservee aux articles explicitement geres en bobines.
- `app/Modules/Production/Services/ProductionStockService.php` : un composant bobine deja consomme physiquement n'est plus backflushe en reliquat.
- Tests ajoutes ou renforces : `AuditCoilLotsCommandTest`, `CoilManagementRulesTest`, `CoilBackflushCumulativeTest`, `StockAuditCommandTest`, `CoilStockSyncTest`.

## Controle de la base locale et regularisation

- Correction deja appliquee avant ce lot renforce sur la base locale `iboa_erp` : `php artisan stock:audit-coil-lots --product=1 --warehouse=2 --fix`.
- Sauvegarde prealable dediee a cette correction : `NON FOURNIE`. Aucun dump du `24/07/2026` n'a ete retrouve avant ce `--fix`.
- Mouvement de regularisation retrouve : `stock_movements.id=76`, type `ajustement`, `quantity_in_stock_uom=3.0100`, `idempotency_key=opening-reconciliation:1:2:20260724`, cree le `24/07/2026 12:11:37`.
- Effet constate : `product_stocks` est passe de `4.9948 kg` a `8.0048 kg`, tandis que bobines et lots restaient a `8.0000 kg`.
- Ecritures comptables automatiques liees a cette regularisation : `0`. Aucune piece GL n'a ete postee automatiquement.
- Etat local apres correction et audit global bobines : `A=8.0000`, `B=8.0000`, `C=8.0048`, `D=8.0048`, ecart sous seuil d'anomalie `0.01`.

## Registre detaille

Le registre detaille reste maintenu dans `docs/AUDIT-GLOBAL-ERP-A3.md`, sections `0 bis` et `0 ter` pour les mesures du 24/07/2026.

## Decision provisoire

- Production : `NO-GO` maintenu.
- Motifs restants : parcours navigateur critiques non reexecutes dans ce lot, script multi-processus OS reel ajoute mais non prouve runnable localement, regularisation stock deja appliquee sans sauvegarde dediee prealable, reconciliation valorisation/GL globale non reproouvee.
