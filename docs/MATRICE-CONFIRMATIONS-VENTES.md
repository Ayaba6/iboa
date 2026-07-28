# Matrice des confirmations — module Ventes

**Périmètre** : `resources/views/ventes/**` · **Généré depuis le code**, pas rédigé à la main.

## Résultat de la recherche des confirmations natives

```text
window.confirm                : 0 dans le module Ventes
onclick="return confirm       : 0 dans le module Ventes
onsubmit="return confirm      : 0 dans le module Ventes
confirmation native métier restante (Ventes) : 0
```

**Hors Ventes, le compte n'est pas à zéro** : 105 occurrences dans 73 fichiers —
rh 18, achats 12, settings 7, comptabilité 5, stocks 4, production 4, warehouses 2,
suppliers 2, products 2, clients 2, et 15 modules à 1 occurrence.
Aucune n'a été traitée par ce lot.

## Les 36 actions passées en modale applicative

Le chiffre annoncé précédemment était 32 ; le décompte réel du code est **36**.
La colonne « permission serveur » est le middleware **effectivement** appliqué à la
route, extrait de `route:list`, pas la directive `@can` de la vue.

| # | Écran | Action (route) | Permission serveur | Modale applicative |
|---:|---|---|---|---|
| 1 | `resources/views/ventes\avoirs\show.blade.php:67` | `ventes.avoirs.submit` | `sales.submit` | Soumettre cet avoir à la validation interne ? |
| 2 | `resources/views/ventes\avoirs\show.blade.php:79` | `ventes.avoirs.destroy` | `credit_notes.view` | Supprimer définitivement cet avoir ? |
| 3 | `resources/views/ventes\avoirs\show.blade.php:100` | `ventes.avoirs.validate-internal` | `sales.validate` | Valider cet avoir ? Cette action génère une écriture comptable. |
| 4 | `resources/views/ventes\avoirs\show.blade.php:151` | `ventes.avoirs.apply` | `credit_notes.create` | Appliquer cet avoir sur la facture liée ? |
| 5 | `resources/views/ventes\avoirs\show.blade.php:167` | `ventes.avoirs.replacement` | `credit_notes.create` | Générer un bon de livraison de remplacement (brouillon) pour ce client |
| 6 | `resources/views/ventes\bons-livraison\index.blade.php:144` | `ventes.bons-livraison.validate` | `deliveries.validate` | Valider le BL … ? Le stock sera décrémenté. |
| 7 | `resources/views/ventes\bons-livraison\show.blade.php:68` | `ventes.bons-livraison.submit` | `sales.submit` | Soumettre ce bon de livraison à la validation interne ? |
| 8 | `resources/views/ventes\bons-livraison\show.blade.php:91` | `ventes.bons-livraison.validate-internal` | `sales.validate` | Valider ce bon de livraison ? Le stock sera décrémenté. |
| 9 | `resources/views/ventes\bons-livraison\show.blade.php:142` | `ventes.bons-livraison.invoice` | `deliveries.validate` | Créer une facture depuis ce bon de livraison ? |
| 10 | `resources/views/ventes\bons-preparation\index.blade.php:158` | `ventes.bons-preparation.start-loading` | `bon_preparations.update` | Démarrer le chargement du BP … ? |
| 11 | `resources/views/ventes\bons-preparation\index.blade.php:170` | `ventes.bons-preparation.finish-loading` | `bon_preparations.update` | Terminer le chargement du BP … ? |
| 12 | `resources/views/ventes\commandes\index.blade.php:150` | `ventes.commandes.destroy` | `orders.view` | Supprimer la commande … ? |
| 13 | `resources/views/ventes\commandes\show.blade.php:52` | `ventes.commandes.submit` | `sales.submit` | Soumettre cette commande à la validation interne ? |
| 14 | `resources/views/ventes\commandes\show.blade.php:65` | `ventes.commandes.destroy` | `orders.view` | Supprimer définitivement cette commande brouillon ? |
| 15 | `resources/views/ventes\commandes\show.blade.php:82` | `ventes.commandes.validate-internal` | `sales.validate` | Valider cette commande ? |
| 16 | `resources/views/ventes\commandes\show.blade.php:186` | `ventes.commandes.revoke-production` | `production.approve_financial` | Révoquer l'approbation de production de cette commande ? |
| 17 | `resources/views/ventes\commandes\show.blade.php:230` | `ventes.commandes.delivery-note` | `sales.transform` | Créer un bon de livraison depuis cette commande ? |
| 18 | `resources/views/ventes\commandes\show.blade.php:252` | `ventes.commandes.cancel` | `orders.validate` | Annuler cette commande ? |
| 19 | `resources/views/ventes\commandes\show.blade.php:267` | `ventes.commandes.delivery-note` | `sales.transform` | Créer un bon de livraison complémentaire ? |
| 20 | `resources/views/ventes\commandes\show.blade.php:278` | `ventes.commandes.invoice` | `sales.transform` | Créer une facture depuis cette commande ? |
| 21 | `resources/views/ventes\commandes\show.blade.php:293` | `ventes.commandes.invoice` | `sales.transform` | Créer une facture depuis cette commande ? |
| 22 | `resources/views/ventes\commandes\show.blade.php:309` | `ventes.commandes.reopen` | `orders.reopen` | Réouvrir cette commande annulée ? Elle repassera en brouillon. |
| 23 | `resources/views/ventes\contrats\index.blade.php:88` | `ventes.contrats.destroy` | `orders.create` | Supprimer le contrat … ? |
| 24 | `resources/views/ventes\devis\index.blade.php:179` | `ventes.devis.convert` | `sales.transform` | Convertir ce devis en commande ? |
| 25 | `resources/views/ventes\devis\index.blade.php:195` | `ventes.devis.destroy` | `quotes.view` | Supprimer le devis … ? |
| 26 | `resources/views/ventes\devis\show.blade.php:109` | `ventes.devis.submit` | `sales.submit` | Soumettre ce devis à la validation interne ? |
| 27 | `resources/views/ventes\devis\show.blade.php:123` | `ventes.devis.destroy` | `quotes.view` | Supprimer définitivement ce devis brouillon ? |
| 28 | `resources/views/ventes\devis\show.blade.php:146` | `ventes.devis.validate-internal` | `sales.validate` | Valider ce devis ? |
| 29 | `resources/views/ventes\devis\show.blade.php:212` | `ventes.devis.convert` | `sales.transform` | Transformer ce devis en commande ? |
| 30 | `resources/views/ventes\devis\show.blade.php:270` | `ventes.devis.destroy` | `quotes.view` | Supprimer définitivement ce devis ? |
| 31 | `resources/views/ventes\factures\index.blade.php:214` | `ventes.factures.validate` | `invoices.validate` | Valider la facture … ? |
| 32 | `resources/views/ventes\factures\show.blade.php:100` | `ventes.factures.submit` | `sales.submit` | Soumettre cette facture à la validation interne ? |
| 33 | `resources/views/ventes\factures\show.blade.php:123` | `ventes.factures.validate-internal` | `sales.validate` | Valider cette facture ? Elle sera émise et ne pourra plus être modifié |
| 34 | `resources/views/ventes\factures\show.blade.php:180` | `ventes.factures.send-email` | `invoices.send` | Envoyer la facture à … ? |
| 35 | `resources/views/ventes\factures\show.blade.php:249` | `ventes.factures.convert-proforma` | `invoices.validate` | Convertir cette proforma en facture standard ?\n\nUne nouvelle facture |
| 36 | `resources/views/ventes\factures\show.blade.php:836` | `ventes.factures.schedules.destroy-all` | `invoices.delete` | Supprimer tout l'échéancier ? |

## Défauts de granularité constatés

Quatre suppressions étaient couvertes par une permission de **consultation** :

| Action | Permission avant | Compensation contrôleur | Verdict |
|---|---|---|---|
| `ventes.avoirs.destroy` | `credit_notes.view` | `authorize('delete', …)` | couvert par la politique |
| `ventes.commandes.destroy` | `orders.view` | `authorize('delete', …)` | couvert par la politique |
| `ventes.devis.destroy` | `quotes.view` | politique de devis | couvert par la politique |
| `ventes.factures.schedules.destroy-all` | `invoices.view` | **aucune** | **DÉFAUT — corrigé** |

`ClientPaymentScheduleController::destroyAll()` n'effectue aucune vérification de
droit : une permission de lecture suffisait à effacer l'échéancier complet d'une
facture. Routes désormais séparées :

```text
ventes.factures.schedules.store         invoices.create
ventes.factures.schedules.store-custom  invoices.create
ventes.factures.schedules.destroy-all   invoices.delete
```

Même défaut sur la route sœur en Trésorerie, corrigé de la même façon :

```text
tresorerie.schedules-clients.destroy    treasury.view + payments.view + invoices.delete
```

## Ce qui reste à prouver

La matrice établit l'écran, l'action, la permission serveur et la modale.
**Elle ne prouve pas le comportement navigateur** : les 36 modales n'ont pas été
cliquées dans une session authentifiée. Le §8 (parcours A à E) reste à exécuter.
