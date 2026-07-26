# Modèle détaillé des quantités — Réceptions fournisseurs (A3)

> Source de vérité centralisée des définitions. Aucune quantité ne doit être
> représentée par un seul champ `received_quantity`. La vérité provient des
> documents détaillés (lignes de réception, décisions qualité, retours,
> annulations, allocations de facture, avoirs) ; les agrégats sur la ligne de
> commande sont un **cache réconcilié**, jamais la seule vérité.

## 1. Quantités par ligne de commande fournisseur

| Quantité | Définition |
|---|---|
| commandée | `purchase_order_items.quantity` |
| physique reçue | Σ `reception_items.received_quantity` des réceptions validées − annulations techniques |
| en attente de contrôle / quarantaine | Σ `reception_items.quarantine_quantity` − libérées − refusées ultérieures − retournées |
| acceptée et libérée | Σ `reception_items.accepted_quantity` (+ libérations qualité) − retours validés |
| refusée | Σ `reception_items.rejected_quantity` (refusé à quai) + refus après quarantaine |
| retournée | Σ retours fournisseurs validés portant sur la ligne |
| annulée techniquement | Σ contre-mouvements d'annulation de réception |
| déjà facturée | Σ allocations de facture comptabilisables (table d'allocation, à venir) |
| créditée par avoir | Σ avoirs fournisseurs liés |
| encore à recevoir | `commandée − physique reçue net` |
| encore facturable | défini §3 selon la politique d'achat |

## 2. Invariants

**À la réception (par ligne) :**
```
received_quantity = accepted_quantity + quarantine_quantity + rejected_quantity
```
Imposé par `PurchaseReceptionService::validate()` — toute ventilation dont la somme
diffère du reçu est refusée (transaction annulée).

**Après décision qualité (par lot en quarantaine) :**
```
quarantaine initiale = libéré + refusé ultérieur + retourné + quarantaine restante
```
Aucune quantité ne disparaît ni n'est comptée deux fois.

**Formules de base :**
```
reçu physique net   = Σ réceptions validées − réceptions annulées techniquement
accepté net         = Σ accepté/libéré − retours validés − annulations techniques
en quarantaine      = Σ reçu en attente − libéré − refusé − retourné
encore à recevoir   = commandée − reçu physique net
```

## 3. Quand une quantité devient FACTURABLE (par type d'achat)

- **Matière/marchandise soumise à qualité (défaut)** :
  ```
  facturable = accepté et libéré − déjà facturé net
  ```
  La **quarantaine n'est PAS facturable** (sauf règle contractuelle explicite et approuvée).
- **Matière sans contrôle qualité obligatoire** : la validation quantitative de la
  réception vaut acceptation.
- **Service** : facturation sur attestation de service fait / jalon validé / PV /
  validation du demandeur (pas de réception physique).
- **Immobilisation** : selon réception / installation / mise en service / jalon.

Aucune règle unique n'impose une réception physique à toutes les dépenses.

## 4. Routage stock par disposition (implémenté)

| Disposition | Effet stock |
|---|---|
| accepté | entrée au **dépôt utilisable** (disponible, réservable, consommable) |
| quarantaine | entrée au **DÉPÔT QUAR** (visible, **non** disponible/réservable/consommable, hors MRP et hors optimisation de découpe) |
| refusé à quai | **aucune** entrée en stock vendable |
| libéré après contrôle | transfert atomique quarantaine → dépôt utilisable (à venir, décision qualité) |

## 5. Schéma (état actuel)

- `reception_items` : `received_quantity`, `accepted_quantity`, `quarantine_quantity`,
  `rejected_quantity` (= refusé), `disposition_origin` (`saisie` | `reconstruite`).
- `purchase_order_items` : `received_quantity`, `accepted_quantity` (cache),
  `invoiced_quantity`.
- Backfill historique : `accepted = received − refused`, `quarantine = 0`, origine
  **`reconstruite` (non certifiée)** — cf. #14 : on n'invente pas de décision qualité.

## 6. À venir dans le lot Réceptions

Décisions qualité (quarantaine → libéré/refusé/retourné), valorisation provisoire
+ frais accessoires, annulation technique, allocations de facture par réception,
concurrence multi-processus, audit de réconciliation (agrégat ligne BC ≠ somme des
documents), puis rapprochement 3 voies (A2/A3).
