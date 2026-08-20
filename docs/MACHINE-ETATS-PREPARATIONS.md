# Bons de préparation — machine d'états et matrice de permissions

**Module** : Ventes · **Sous-module** : Bons de préparation quantifiés
**Statut** : correction active · NO-GO staging · NO-GO production

Ce document décrit ce qui est **implémenté et testé**, pas ce qui est prévu.
Chaque transition citée est couverte par au moins un test nommé.

---

## 1. Deux documents distincts, jamais fusionnés

| | `bon_preparations` | `sales_pickings` |
|---|---|---|
| Nature | bon de **chargement** historique | bon de **préparation** quantifié |
| Lignes | aucune | `sales_picking_items` |
| Quantités | aucune | 9 colonnes séparées |
| Allocations | aucune | lot / bobine / dépôt / emplacement |
| Statut | `LEGACY_UNQUANTIFIED` | machine à 8 états |
| Menu | « Chargements » | « Préparations » |

Les documents historiques **ne sont pas migrés** vers le nouveau modèle : ils
n'ont pas de lignes, et leur en fabriquer depuis la commande produirait un
historique faux. Ils restent consultables tels quels.

---

## 2. Machine d'états

```text
brouillon ──lancer──────────> en_preparation
                                   │
                    prélèvements    │
                                   ▼
              partiellement_prepare ──(tout prélevé)──> prepare
                                   │                       │
                                   └───── contrôler ───────┤
                                                           ▼
                                                       controle
                                                           │
                                                       valider
                                                           ▼
                                                        valide   (FINAL)

tout état non final ── annuler (motif obligatoire) ──> annule   (FINAL)
```

### Transitions

| Transition | Acteur | Permission | Motif | Verrou | Effet | Réversible |
|---|---|---|---|---|---|---|
| créer | préparateur | `bon_preparations.update` | — | commande | fige le reliquat dans la ligne | par annulation |
| lancer | préparateur | `bon_preparations.update` | — | bon | horodate `started_by/at` | par annulation |
| allouer | préparateur | `bon_preparations.update` | — | bon + lot/bobine | fige le coût historique | par annulation |
| prélever | préparateur | `bon_preparations.update` | **si écart** | bon + allocation | écart tracé et motivé | non |
| contrôler | contrôleur ≠ préparateur | `bon_preparations.control` | — | bon | `qty_controlled` = `qty_picked` | invalidé par toute modification |
| valider | validateur ≠ contrôleur | `bon_preparations.validate` | — | bon | `qty_validated` figé, document gelé | **non** |
| annuler | préparateur | `bon_preparations.update` | **obligatoire** | bon | libère réservations, annule allocations | **non** |

### Règles non négociables

- Un bon **validé** ou **annulé** est figé : la garde est au niveau **modèle**
  (`SalesPicking::updating`), pas seulement dans le service.
- Un bon **ne se supprime jamais** (`SalesPicking::deleting` lève une exception).
- Une modification après contrôle **invalide** le contrôle avec motif et fait
  repartir le bon en `partiellement_prepare` — elle n'est pas interdite : il faut
  pouvoir corriger une erreur détectée au contrôle.
- Un bon dont le contrôle est tombé **ne peut plus être validé**.

---

## 3. Modèle des quantités

Neuf colonnes **séparées** par ligne. Jamais un champ unique pour représenter
réservation, prélèvement et validation.

```text
qty_ordered               commandé (snapshot)
qty_previously_delivered  déjà livré au moment de la création
qty_cancelled             annulé sur la commande
qty_remaining_snapshot    reliquat FIGÉ que ce bon s'engage à préparer
qty_reserved              réservé
qty_allocated             alloué à des lots/bobines
qty_picked                réellement prélevé
qty_controlled            contrôlé
qty_validated             validé — source du bon de livraison
variance_qty              écart alloué − prélevé
variance_reason           motif de l'écart (obligatoire si écart)
```

### Invariant central

```text
qty_validated ≤ qty_controlled ≤ qty_picked ≤ qty_allocated ≤ qty_remaining_snapshot
```

Vérifié à l'écriture par le service **et** a posteriori par
`a3:audit-pickings` (détection 10).

### Reliquat préparable

```text
reliquat = commandé − déjà livré − engagé par les autres bons NON annulés
```

Un bon annulé rend son engagement au reliquat : une nouvelle préparation
redevient possible.

---

## 4. Interdictions d'allocation

Appliquées dans `SalesPickingService::assertAllocatable()`, jamais dans la vue.

| Interdiction | Motif |
|---|---|
| lot non libéré | qualité non prononcée ou refusée |
| lot non valorisé définitivement | fausserait le coût des ventes |
| bobine non libérée | idem |
| **bobine mère divisée** | n'est plus du stock actif — prélever ses filles |
| quantité > disponible | disponible = stock − réservé − **déjà alloué ailleurs** |
| quantité > reliquat de la ligne | le bon ne peut pas dépasser son engagement |
| dépôt ≠ dépôt du bon | une sortie doit être traçable à un dépôt |
| ni lot ni bobine | le stock anonyme n'est pas traçable |

Le calcul du disponible retranche les allocations actives des **autres** bons.
Sans cela, deux préparations engageaient le même stock — mesuré à **16 alloués
sur un lot de 10** avant correction.

---

## 5. Matrice de permissions

| Permission | Rôles porteurs | Actions |
|---|---|---|
| `bon_preparations.view` | super_admin, magasinier, responsable_stock, commercial, caissier | consulter liste et fiche |
| `bon_preparations.update` | super_admin, magasinier | créer, lancer, allouer, prélever, annuler |
| `bon_preparations.control` | super_admin, responsable_stock | contrôler |
| `bon_preparations.validate` | super_admin, responsable_stock | valider, créer le BL |

Le **responsable stock** reçoit `control` + `validate` **sans** `update` : il ne
prépare pas ce qu'il contrôle. Le magasinier garde `update` seul.

La séparation est appliquée **deux fois** :
- au niveau **route** (middleware de permission) ;
- au niveau **service** (le préparateur ne peut pas contrôler son propre bon,
  le contrôleur ne peut pas valider ce qu'il vient de contrôler) — même un
  utilisateur détenant les trois permissions est refusé.

---

## 6. Concurrence

Quatre courses multi-processus réelles, MySQL uniquement.

| Course | Scénario | Garantie |
|---|---|---|
| A | deux bons sur le même reliquat | un seul passe |
| B | deux allocations sur le même lot | jamais de surengagement |
| C | modification pendant validation | jamais d'état intermédiaire |
| D | annulation contre validation | une seule transition gagne |

**Exclusion déclarée** : ces tests sont `skipped` sur SQLite avec motif affiché.
Raison technique — `:memory:` n'est pas partageable entre processus et
n'implémente pas `SELECT ... FOR UPDATE`, précisément le mécanisme évalué.

**Isolation** : une course impose de commiter le décor, ce qui annule
l'isolation par test. Sans remède, la suite MySQL passait de 0 à **158 échecs**
en aval. Les fichiers de course forcent donc une reconstruction de la base.

---

## 7. Bon de livraison

Le BL rattaché se construit depuis `qty_validated`, **jamais depuis la
commande**. Chaque ligne porte `sales_picking_item_id`.

- Le lot d'origine n'est repris que si l'allocation est **unique** : une ligne
  préparée sur plusieurs lots ne peut pas être résumée à un seul.
- `assertDeliverable()` refuse le dépassement **à l'écriture**.
- Un BL annulé libère à nouveau le validé.
- La colonne est **nullable** : les BL historiques et le flux direct
  commande → BL restent légitimes. `NULL` signifie « pas de préparation »,
  jamais « préparation inconnue ».

---

## 8. Audit — `a3:audit-pickings`

Lecture seule. N'écrit jamais, ne répare jamais. `exit 1` si anomalie critique.

11 détections + 3 sous-détections. Chacune est **éprouvée par un test qui plante
l'anomalie** — un audit qui rend 0 sur une base vide ne prouve rien.

Deux positions assumées :
- **détection 9** (allocation sans réservation) est **informative** : la règle
  n'est pas tranchée métier, la compter en critique ferait échouer l'audit sur
  une exigence non posée ;
- **détection 11** n'a été activée qu'après le rattachement du BL. Tant qu'il
  n'existait pas, l'audit déclarait **NON APPLICABLE** plutôt que d'annoncer un
  zéro qui aurait laissé croire à une vérification faite.

---

## 9. Couverture de tests

| Fichier | Tests | Objet |
|---|--:|---|
| `SalesPickingWorkflowTest` | 29 | 20 scénarios obligatoires + gardes |
| `MySqlPickingConcurrencyTest` | 4 | courses A à D |
| `AuditPickingsTest` | 14 | chaque détection éprouvée |
| `SalesPickingToDeliveryTest` | 9 | BL depuis le validé |
| `SalesPickingUiTest` | 11 | routes HTTP authentifiées |
| **Total** | **67** | |

---

## 10. Ce qui n'est pas prouvé

- **Comportement navigateur** : aucune page ouverte, aucune modale cliquée,
  aucun JavaScript exécuté. Les tests d'interface appellent les routes.
- **Scan et import** : les actions « Scanner » et « Import » du cahier des
  charges ne sont pas implémentées ; leur idempotence n'est donc pas testée.
- **Emplacements** : la colonne `location_id` existe et est portée par
  l'allocation, mais aucun référentiel d'emplacements ne l'alimente.
- **Impression** : aucun PDF de bon de préparation.
- **Réservations** : l'allocation accepte une réservation existante mais n'en
  crée pas — le flux amont ne les pose pas systématiquement.
