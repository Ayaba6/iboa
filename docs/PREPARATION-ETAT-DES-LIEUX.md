# Bons de préparation — état des lieux avant refonte

**Module** : Ventes · **Établi en lecture seule**, avant toute écriture de code.
**Statut** : NO-GO staging, NO-GO production.

Ce document dit ce qui existe aujourd'hui et ce qui n'existe pas. Il sert de base
au lot « workflow détaillé des bons de préparation ». Rien n'y est anticipé ni
supposé : tout provient du schéma et du code en place.

---

## 1. Ce qui existe

### Table `bon_preparations` — **en-tête uniquement**

| Colonne | Type | Rôle |
|---|---|---|
| `company_id`, `order_id`, `fiscal_year_id` | FK | rattachement |
| `number` | varchar(50) | numéro de document |
| `payment_mode` | enum `cash` / `credit` | mode de règlement de la commande |
| `status` | enum `en_attente` / `en_cours` / `charge` / `annule` | **4 états** |
| `payment_reference`, `payment_amount`, `client_payment_id`, `payment_recorded_by`, `payment_recorded_at` | — | encaissement comptant |
| `validated_by`, `validated_at`, `loaded_by`, `loaded_at` | — | horodatage des acteurs |
| `notes`, `created_by` | — | — |

### `BonPreparationService` — 4 méthodes, 157 lignes

```text
createForCreditOrder(Order)   création pour une commande à crédit
createForCashOrder(...)       création + encaissement comptant
startLoading(BonPreparation)  en_attente → en_cours
finishLoading(BonPreparation) en_cours   → charge  (+ notification)
```

Les transitions vérifient le statut de départ et rien d'autre.

### Permissions

```text
bon_preparations.view     index, show, pdf
bon_preparations.update   startLoading, finishLoading
```

---

## 2. Ce qui n'existe pas

**Il n'y a aucune table de lignes de préparation.** `bon_preparations` est la
seule table du sous-module : pas de `bon_preparation_items`, pas d'allocations.

Conséquence directe : le document actuel n'est **pas** un bon de préparation au
sens logistique. C'est un **bon de chargement** — il atteste qu'une commande a été
chargée, sans jamais dire *quoi*, *en quelle quantité*, *depuis quel lot*,
*quelle bobine* ni *quel emplacement*.

| Exigence attendue | État |
|---|---|
| Service transactionnel dédié à la réservation/allocation | **absent** — pas de `SalesPickingService` |
| Machine d'états à 8 axes (`BROUILLON` → … → `VALIDÉ` / `ANNULÉ`) | **absent** — 4 états, pas de contrôle ni de validation séparée |
| Modèle des 9 quantités par ligne (commandée, livrée, annulée, restant à préparer, réservée, allouée, prélevée, contrôlée, validée) | **absent** — aucune quantité n'est portée |
| Table `picking_item_allocations` (lot, bobine, dépôt, emplacement, unité, conversion, coût historique, réservation, statut) | **absent** |
| Interdictions d'allocation (quarantaine, bobine non libérée, bobine mère divisée, stock déjà alloué, dépôt incorrect) | **sans objet** — rien n'est alloué |
| Préparation partielle et écart entre prévu et prélevé | **absent** |
| Séparation préparateur / contrôleur / validateur | **absent** — `loaded_by` seul |
| Invalidation du contrôle après modification | **sans objet** |
| Annulation non destructive avec libération des réservations et contre-mouvements | **absent** — statut `annule` sans effet |
| Idempotence par clé durable (création, lancement, validation, annulation, import, scan) | **absent** |
| Audit dédié (allocation > reliquat, lot alloué deux fois, quarantaine allouée, BL > préparé…) | **absent** |

---

## 3. Conséquence sur la chaîne aval

La création du bon de livraison ne s'appuie sur **aucune** donnée de préparation :
les lignes du BL proviennent de la commande, pas de ce qui a réellement été
prélevé. L'invariant demandé —

```text
quantité préparée nette ≤ quantité commandée nette restant à livrer
```

— n'est donc ni calculable ni vérifiable en l'état.

De même, l'exigence « facturation limitée au livré » ne peut pas s'appuyer sur la
préparation tant que celle-ci ne porte pas de quantités.

---

## 4. Nature du travail à venir

Ce n'est pas un durcissement du module existant, c'est une **construction**.
L'en-tête `bon_preparations` et ses 4 états peuvent être conservés comme socle
documentaire (numérotation, rattachement commande, encaissement comptant), mais
le modèle de quantités, les allocations, le contrôle et l'annulation réversible
sont à créer intégralement.

À trancher avant d'écrire la première migration :

1. **Conserver ou remplacer** l'enum `status` actuel. Les 4 valeurs existantes
   (`en_attente`, `en_cours`, `charge`, `annule`) ne recouvrent pas les 8 états
   demandés. Une migration de valeurs existantes sera nécessaire — et le registre
   des documents déjà émis ne doit pas être réécrit.
2. **Sort des bons existants** en base de développement : ils n'ont pas de lignes.
   Ils ne pourront pas être reconstruits en préparations quantifiées. La règle
   « ne jamais inventer de données » impose de les marquer comme non quantifiés
   plutôt que de leur fabriquer des lignes à partir de la commande.
