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

## 3bis. Absence d'information ≠ acceptation (règle de sûreté)

`absence de ventilation ≠ acceptation`. Aucune règle ne convertit silencieusement
une ligne « non ventilée » en « accepté ».

- **Nouvelle réception** — décision EXPLICITE et traçable, jamais de fallback :
  - ventilation fournie → `disposition_origin = saisie`, `reconstruction_confidence = CERTIFIED` ;
  - article soumis à contrôle qualité (`products.controle_qualite` ou
    `item_categories.qc_required`) sans ventilation → **refus** (ventilation obligatoire) ;
  - article sans QC obligatoire → décision explicite `no_quality_required`
    (`accepted = received`, CERTIFIED).
- **Ligne historique** — `accepted_quantity`/`quarantine_quantity` = **NULL (inconnu)**,
  `disposition_origin = legacy_unclassified`, `reconstruction_confidence = RECONSTRUCTED`,
  valeur calculée isolée dans `reconstructed_quantity` (informative, jamais preuve qualité).
- **Colonnes nullables** : NULL = inconnu, 0 = explicitement nul — on ne transforme
  pas une valeur inconnue en zéro.
- **Niveau de confiance** : `CERTIFIED` (preuve : contrôle/user/date/décision/dépôt/mouvement)
  · `RECONSTRUCTED` (calculé sans preuve) · `UNKNOWN` (indéterminable). Affiché en
  audit/rapports ; A2/A3 ne facturera jamais automatiquement une acceptation
  RECONSTRUCTED/UNKNOWN sans règle (blocage/validation/attestation/dérogation).
- **Replay** (`ReplayReceptionStockSync`) : lit une disposition DÉJÀ déterminée ; une
  ligne à disposition inconnue (accepté NULL) ne crée **aucun** stock vendable et est
  signalée (audit `a3:audit-receptions`).

## 4. Routage stock par disposition (implémenté)

| Disposition | Effet stock |
|---|---|
| accepté | entrée au **dépôt utilisable** (disponible, réservable, consommable) |
| quarantaine | entrée au **DÉPÔT QUAR** (visible, **non** disponible/réservable/consommable, hors MRP et hors optimisation de découpe) |
| refusé à quai | **aucune** entrée en stock vendable |
| libéré après contrôle | transfert atomique quarantaine → dépôt utilisable (à venir, décision qualité) |

## 5. Schéma (état actuel)

- `reception_items` : `received_quantity`, `accepted_quantity` (**nullable**),
  `quarantine_quantity` (**nullable**), `rejected_quantity` (= refusé),
  `disposition_origin` (`saisie` | `no_quality_required` | `legacy_unclassified`),
  `reconstructed_quantity` (nullable, informatif), `reconstruction_confidence`
  (`CERTIFIED` | `RECONSTRUCTED` | `UNKNOWN`), `reconstructed_at`.
- `purchase_order_items` : `received_quantity`, `accepted_quantity` (cache **nullable**),
  `invoiced_quantity`.
- Historique : `accepted`/`quarantine` remis à **NULL** (inconnu), valeur calculée
  isolée dans `reconstructed_quantity`, origine `legacy_unclassified`, confiance
  `RECONSTRUCTED` — la migration n'a **modifié aucun stock** (colonnes uniquement).

## 6. À venir dans le lot Réceptions

Décisions qualité (quarantaine → libéré/refusé/retourné), valorisation provisoire
+ frais accessoires, annulation technique, allocations de facture par réception,
concurrence multi-processus, audit de réconciliation (agrégat ligne BC ≠ somme des
documents), puis rapprochement 3 voies (A2/A3).
