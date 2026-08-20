# BUG-A3-MASTER-DATA-SHEET-021

**Titre** — Les caractéristiques structurées de plusieurs articles de tôle contredisent leur désignation commerciale.

**Priorité** — P1, bloquant l'activation du profil `sheet_metal` en production.

**Ouvert le** — 2026-08-03, à l'occasion de l'audit `a3:audit-mto-profiles` du lot `BUG-A3-MTO-TECH-010`.

**Bloque** — `TASK-A3-MTO-PROFILE-DATA-020` (affectation du profil de spécifications aux articles réels).

---

## Constat

L'audit des candidats au profil `sheet_metal` a comparé, pour chaque article de
tôle bac, l'épaisseur annoncée par la désignation commerciale et l'épaisseur
enregistrée dans `products.thickness`. Trois articles sur cinq divergent.

| Code | Désignation annonce | `products.thickness` | Statut |
|---|---|---|---|
| `PFN_TBC_BEI_0.27` | 27/100 → 0,27 mm | **0,25** | conflit |
| `PFN_TBC_ORA_0.27` | 27/100 → 0,27 mm | **0,30** | conflit |
| `PFN_TBC_ORA_0.30` | 30/100 → 0,30 mm | **0,25** | conflit |
| `PFN_TBC_BEI_0.30` | 30/100 → 0,30 mm | 0,30 | cohérent |
| `TEST-GUIDE-TBC27` | 27/100 → 0,27 mm | *(absente)* | incomplet |

Les trois écarts ne sont pas des arrondis : `PFN_TBC_ORA_0.27` porte une
épaisseur SUPÉRIEURE de trois centièmes à ce que son nom annonce, et
`PFN_TBC_ORA_0.30` une épaisseur inférieure de cinq centièmes. Sur de la tôle
bac, cinq centièmes séparent deux références commerciales distinctes.

## Ce que le logiciel ne fera pas

Aucune des deux sources ne prime sur l'autre. La désignation peut dater d'avant
un changement de fournisseur ; la valeur structurée peut avoir été saisie à la
va-vite. Le code se contente donc de signaler :

```text
CONFLIT DE DONNÉES
```

Il est **interdit** de :

- corriger `products.thickness` depuis la désignation ou depuis le code article ;
- corriger la désignation depuis `products.thickness` ;
- préremplir une ligne de vente ou un ordre de fabrication à partir de l'une ou
  l'autre tant que le conflit n'est pas tranché ;
- classer automatiquement ces articles en `sheet_metal`.

La valeur juste doit venir d'une source métier autorisée : fiche technique
fournisseur, nomenclature validée, fiche article approuvée, responsable
production, responsable qualité, catalogue industriel, ou historique de
fabrication vérifié.

## Pourquoi c'est bloquant

Activer `sheet_metal` sur la catégorie `PF_TOLE_MTO` ferait préremplir
l'épaisseur structurée sur chaque nouvelle ligne de devis. Le commercial verrait
une valeur déjà remplie, la croirait fiable, et confirmerait une épaisseur
fausse sur trois références.

Un champ vide se voit et se demande. Un champ prérempli avec une valeur erronée
ne se voit pas.

## Article à statuer séparément — `TEST-GUIDE-TBC27`

Il n'est pas dormant. Son activité réelle :

| | |
|---|---|
| `is_active` / `is_sellable` | 1 / 1 |
| Devis | 4 |
| Commandes | CMD-2026-001 (annulée), 002 (facturée), 004 (facturée), 005 (brouillon) |
| Factures | FA-2026-001 et FA-2026-002, toutes deux **payées** |
| Ordre de fabrication | OF-2026-0001 (annulé) |
| Mouvements de stock | 4 |
| Client | `CLIENT TEST GUIDE SARL` sur la totalité des pièces |

**Classement : article de démonstration.** Les pièces sont réelles au sens
comptable, mais toutes rattachées à un client de test. L'article occupe les
premiers numéros de la séquence documentaire de l'exercice.

### Ce que la désactivation fait vraiment — vérifié, non supposé

Une première rédaction affirmait que désactiver l'article « orphelinerait les
factures payées ». C'était faux, et l'affirmation n'avait pas été éprouvée. Le
contrôle a été mené sur une copie de la base de développement.

| Contrôle | Avant | Après `is_active = 0` |
|---|---|---|
| Factures résolvant l'article | 2 | **2** |
| `product_id` conservé sur les lignes de facture | 2 | **2** |
| Lignes de commande | 4 | **4** |
| Mouvements de stock | 4 | **4** |
| `InvoiceItem->product` résolu par Eloquent | oui | **oui** — nom et facture lisibles |

Trois raisons structurelles l'expliquent :

- `Product::booted()` ne pose **aucun scope global** sur `is_active` ;
- `scopeActive()` et `scopeActif()` sont des scopes NOMMÉS, appliqués
  explicitement par les écrans de sélection, jamais par les relations ;
- les relations `product()` de `InvoiceItem`, `OrderItem` et `QuoteItem` sont
  des `belongsTo` nus, sans filtre, et aucun dépôt de documents ne filtre sur le
  statut de l'article.

**Conséquence.** Désactiver l'article empêche seulement sa réutilisation dans de
nouveaux documents. Rien d'historique n'est masqué ni cassé. Aucune anomalie
`BUG-A3-MASTER-DATA-INACTIVE-HISTORY-022` n'a donc lieu d'être ouverte.

Décision proposée, à confirmer par le métier :

```text
TEST-GUIDE-TBC27
→ conserver les données historiques
→ désactiver pour les nouvelles opérations
→ ne pas supprimer
```

### Profil technique et statut d'exploitation sont deux choses distinctes

Une seconde erreur de raisonnement mérite d'être corrigée : le fait que cet
article serve de démonstration ne dit rien de sa nature technique. Il décrit bien
une tôle bac profilée, et son profil technique est donc `sheet_metal`.

| Dimension | Question | Réponse pour cet article |
|---|---|---|
| Profil technique | l'article est-il décrit comme une tôle bac ? | oui |
| Statut d'exploitation | article réel, de démonstration, de test, obsolète ? | démonstration |

Un article peut porter `mto_specification_profile = sheet_metal` **et** être
inactif. Les deux axes ne s'opposent pas.

**Correction de la conclusion précédente.** J'avais écrit que `PF_TOLE_MTO`
n'était « pas homogène ». C'est inexact : ses cinq articles sont tous des tôles
bac, donc la catégorie est techniquement homogène. Ce qui la rend impropre à une
affectation automatique aujourd'hui, ce sont les trois épaisseurs contradictoires,
les caractéristiques structurées absentes, et le statut d'exploitation de
`TEST-GUIDE-TBC27` restant à trancher — pas une hétérogénéité de profil.

## Ordre de traitement imposé

```text
1. Valider les données techniques des cinq articles
2. Corriger ou compléter les fiches articles
3. Statuer sur le statut d'exploitation de TEST-GUIDE-TBC27
4. Vérifier que PF_TOLE_MTO ne contient que des articles devant hériter du profil
5. Affecter le profil sheet_metal, de préférence au niveau de la CATÉGORIE
6. Tester la création de lignes commerciales
```

## Caractéristiques absentes sur les cinq articles

Au-delà du conflit d'épaisseur, aucun des cinq ne porte de valeur structurée
pour :

```text
color   profil   usable_width   revetement   nb_ondes
```

Chacune reste à statuer : portée par l'article, par la catégorie, fournie par la
nomenclature, saisie obligatoirement par le commercial, choisie dans un
référentiel, ou calculée par un paramétrage validé.

En l'absence de valeur structurée validée, la règle retenue est de **ne pas
préremplir**. La ligne reste modifiable en brouillon ; sa validation bloque tant
que les caractéristiques obligatoires ne sont pas saisies. Rien n'est déduit de
« Beige », « Orange », « 4 ondes », « 27/100 » ou « 30/100 » lus dans une
désignation.

## Vérification

La commande `a3:audit-sheet-master-data` produit ce constat à la demande, en
lecture seule, avec exports JSON et CSV. L'épaisseur extraite d'un libellé y est
présentée comme **indice non contractuel**, jamais comme une valeur.
