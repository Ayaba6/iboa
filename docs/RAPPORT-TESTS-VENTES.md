# RAPPORT DE TESTS VENTES

## Référence

SHA initial : `4b876f795914029c0376ec02b7f64092ec6b1424` — branche `fix/erp-cdc-prod-compta-rh` — arbre non propre documenté.

## Baseline fonctionnelle

| Date | Moteur | Tests | Assertions | Échecs | Durée |
|---|---|---:|---:|---:|---:|
| 28/07/2026 | SQLite `:memory:` | 98 | 406 | 0 | 76,77 s |
| 28/07/2026 | MySQL `iboa_erp_test` | 98 | 406 | 0 | 299,44 s |
| 28/07/2026 | Garde MySQL | 1 | 3 | 0 | 311,40 s |

## Lots ajoutés

| Lot | SQLite | MySQL | Résultat |
|---|---|---|---|
| Isolation KPI multi-société | inclus baseline | inclus baseline | vert |
| Modale applicative sans `confirm()` | 5 tests / 90 assertions avec workflow devis | à intégrer prochaine baseline globale | vert SQLite |
| Crédit prévisionnel transactionnel | 15 tests / 80 assertions avec régressions | 8 tests / 67 assertions | vert deux moteurs |
| Régression finale élargie | 101 tests / 489 assertions / 77,26 s | baseline 98 tests + lot crédit ciblé | vert selon périmètres exécutés |

## Couverture démontrée

Devis, révisions, prix plancher, dérogations, commandes, MTO/MTS, réservations, libération, préparation/chargement, livraisons, allocations multi-lots, qualité, factures, coûts figés, avoirs/retours, paiements, marge, permissions et chaîne order-to-cash.

## Couverture manquante avant GO

- véritables courses MySQL à deux connexions pour plafond, stock, préparation, BL, facture, avoir et encaissement ;
- deadlock/retry ;
- parcours navigateur A à G authentifiés ;
- scan préparation ;
- conversion partielle de devis ;
- factures de situation/solde exhaustives ;
- objet RMA complet.