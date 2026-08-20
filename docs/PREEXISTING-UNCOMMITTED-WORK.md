# Travail antérieur non commité — inventaire

**Date du relevé :** 2026-08-02
**Dépôt :** `C:\laragon\www\iboa`
**Branche :** `release/preproduction-hardening`
**HEAD au relevé :** `80c1bfd` *test(sales): re-found the KPI fixture on the corrected conversion denominator*

Ce document est un **constat**, pas un plan. Il recense ce que l'arbre de travail
contient et qui n'appartient à aucun commit. Il existe parce que la reconstruction
du lot `BUG-A3-SALES-LINE-IMMUTABLE-012` a été rendue impossible dans cet arbre :
le lot y était mélangé à d'autres travaux dans les mêmes fichiers, parfois dans le
même bloc de diff. Aucune modification n'a été supprimée, réinitialisée ou remisée.

## 1. Volume

| Catégorie | Fichiers |
|---|---|
| Modifiés, non indexés | 173 |
| Indexés | 0 |
| Non suivis (hors `.gitignore`) | 77 |
| **Total hors index** | **250** |

Sauvegardes réalisées avant toute manipulation, hors dépôt :

- `../a3-working-tree-full.patch` — 13 919 lignes, 173 fichiers, +3 872 / −1 965
- `../a3-index-full.patch`
- `../a3-untracked-files.txt` — 77 chemins

Ces trois fichiers permettent de reconstituer l'état exact du 2026-08-02.

## 2. Lots identifiables

Les marqueurs de commentaire présents dans le diff permettent d'attribuer la
plupart des modifications à un lot. Les compteurs ci-dessous portent sur les
occurrences du marqueur, pas sur les fichiers.

| Marqueur | Occurrences | Objet |
|---|---:|---|
| `[UI — doublon retiré]` | 21 | Suppression de champs de saisie faisant doublon (dont `default_tax_label`), dérivation par service partagé |
| `[Ventes §17]` | 12 | Motif d'annulation obligatoire et tracé sur les documents de vente |
| `[BUG-A3-SALES-LINE-IMMUTABLE-012]` | 5 | Identité stable des lignes de commande |
| `[Ventes §14]` | 2 | — |
| `[BUG-A3-MTO-TECH-010]` | 2 | Caractéristiques MTO portées par la ligne |
| `[Ventes §4]`, `[Ventes §18]` | 1 chacun | — |
| `[ARCH-S2-02]` | 1 | Verrou de commande, préexistant à HEAD |

### 2.1 Périmètres par fichier

**Doublons de saisie / dérivation partagée** (13 fichiers)
`app/Http/Controllers/Sales/InvoiceController.php`, `app/Services/InvoiceService.php`,
`app/Services/OrderService.php`, `app/Services/SupplierInvoiceService.php`,
`resources/views/achats/{commandes/_form,demandes-achat/create,factures-fournisseurs/_form,retours-fournisseurs/create}.blade.php`,
`resources/views/components/audit/timeline.blade.php`,
`resources/views/ventes/{commandes/_form,devis/_form,factures/_form,factures/show}.blade.php`

**Motif d'annulation obligatoire** (11 fichiers)
`app/Http/Controllers/Sales/{BonPreparationController,InvoiceController,OrderController}.php`,
`app/Services/{BonPreparationService,OrderService}.php`,
`resources/views/ventes/{avoirs/show,bons-livraison/show,bons-preparation/show,commandes/show,devis/show,factures/show}.blade.php`

**Services nouveaux non suivis, dépendances de ces lots**
`app/Services/Sales/SalesLineDefaultsService.php`, `app/Services/Sales/SalesTaxLabelService.php`

**Sous-module Préparations de vente** — entièrement non suivi, 4 modèles, 1 contrôleur,
1 service, 3 vues, 1 document, 4 tests, 1 worker de course :
`app/Models/SalesPicking{,Allocation,Control,Item}.php`,
`app/Http/Controllers/Sales/SalesPickingController.php`,
`app/Services/Sales/SalesPickingService.php`,
`resources/views/ventes/preparations/{index,create,show}.blade.php`,
`docs/MACHINE-ETATS-PREPARATIONS.md`,
`tests/Feature/{SalesPickingWorkflow,SalesPickingUi,SalesPickingToDelivery,MySqlPickingConcurrency}Test.php`,
`tests/Support/picking_race_worker.php`.
Ce sous-module est **référencé** par `app/Services/DeliveryNoteService.php` et
`routes/web.php`, eux-mêmes modifiés et non commités : les deux ne peuvent pas
être séparés.

**Ordonnancement / propositions de transfert** — non suivi :
`app/Modules/Production/Controllers/ProductionScheduleController.php`,
`app/Modules/Production/Services/{ProductionSchedulingService,TransferProposalService}.php`,
`resources/views/production/{schedule/index,mrp/transfers}.blade.php`.

**Commandes d'audit et jeux de test** — non suivi, 9 fichiers :
`app/Console/Commands/{AuditArticleClassification,AuditOrphanDocuments,AuditPickings,PrepareOrderTestClients,PrepareProductionTestData}.php`,
`app/Services/TestData/*.php` (4 fichiers).

**Tests non suivis** : 44 fichiers sous `tests/`, dont 26 directement sous
`tests/Feature/`. Ils couvrent des comportements dont le code de production est
lui-même non commité — ils ne sont pas transposables seuls.

**Mis en attente délibérément** (hors de tout lot en cours) :
`database/migrations/_en_attente_mto/*` (2), `tests/_en_attente_mto/*` (1),
`app/Support/MtoCharacteristics.php`.

## 3. Le cas `app/Services/OrderService.php`

C'est le fichier qui a rendu le découpage impossible. Il porte **11 hunks**
appartenant à **trois lots distincts**, dont un hunk qui les mélange.

| # | Ancre | Lot | Contenu |
|---:|---|---|---|
| 1 | `+41` | *aucun* | Ligne vide ajoutée — bruit |
| 2 | `+57,7` | Doublons de saisie | `SalesTaxLabelService::derive()` dans `create()` |
| 3 | `+125,9` | **012** | Appel à `OrderItemSynchronizer::sync()` en remplacement de `delete()` + `syncItems()` |
| 4 | `+232,17` | Motif d'annulation | Signature `cancel(Order $order, string $motif)`, gardes motif vide + `Auth::check()` |
| 5 | `+250,23` | Motif d'annulation | Suite du même bloc |
| 6 | `+285,5` | Motif d'annulation | `cancelDocument('annule', $motif)` remplace `update(['status' => 'annule'])` |
| 7 | `+369,19` | **012** | `syncItems()` délègue à `buildItemValues()` ; en-tête de la méthode extraite |
| 8 | `+389` | **012** | `continue` devient `return null` |
| 9 | `+423,6` | **mixte** | `SalesLineDefaultsService::apply()` **et** `$order->items()->create([` devient `return [` |
| 10 | `+432` | Doublons de saisie | Champ `'unit_cost'` ajouté au tableau |
| 11 | `+444` | **012** | `]);` devient `];` |

Le hunk 9 place à deux lignes d'écart un appel appartenant au lot « doublons de
saisie » et la transformation en `return` appartenant au lot 012. Aucun filtre
par hunk ne peut les séparer. Deux tentatives de filtrage ont produit un fichier
syntaxiquement valide mais fonctionnellement tronqué : `buildItemValues()` y
retournait sans avoir appliqué les valeurs par défaut. L'index a été réinitialisé
après chaque tentative ; aucune de ces versions n'a été commitée.

Le hunk 4 modifie de plus la **signature publique** de `cancel()`. Tout appelant
non mis à jour dans le même commit casse — `CommercialWorkflowService::cancel()`
en particulier.

## 3 bis. Constat relevé au passage — `tests/Support/picking_race_worker.php`

Ce worker, non suivi, écrit **hors transaction** dans un processus qui ne lit pas
la configuration de PHPUnit : il dépend de l'environnement dont il hérite. Il ne
vérifie pas que la base qu'il ouvre est bien une base de test. Si cet héritage
venait à ne plus fonctionner, la course écrirait sur des données réelles sans
qu'aucun message ne le signale.

Le fichier de test parent contrôle bien `config(...database)` contient `test`,
mais ce contrôle porte sur le processus **parent**, pas sur celui du worker.

Non corrigé ici : ce fichier appartient au sous-module Préparations, hors du
lot 012. La garde équivalente a été posée sur
`tests/Support/order_line_race_worker.php`, qui relève du lot 012.

## 3 ter. `BUG-A3-REPO-DANGLING-014` — un commit référence deux classes jamais ajoutées

**Commit fautif :** `6351713` *fix(sales): preserve quote terms during order conversion*
**Gravité :** le commit est inexploitable isolément.

`app/Services/QuoteService.php` a été commité avec deux appels dont les classes
n'ont jamais été ajoutées à l'index :

| Ligne | Appel | Fichier de classe | État |
|---|---|---|---|
| 63 | `SalesTaxLabelService::derive()` | `app/Services/Sales/SalesTaxLabelService.php` | non suivi |
| 490 | `SalesLineDefaultsService::apply()` | `app/Services/Sales/SalesLineDefaultsService.php` | non suivi |

Sur un `checkout` propre, toute création de devis lève
`Target class [App\Services\Sales\SalesTaxLabelService] does not exist.`
Les deux classes appartiennent au lot « doublons de saisie », non commité : le
commit a donc emporté l'appelant sans l'appelé.

Ce défaut est resté invisible dans le dépôt principal parce que les deux fichiers
sont présents sur disque — simplement hors de Git. Aucune suite exécutée dans cet
arbre ne pouvait le détecter. Il n'est apparu qu'au premier `checkout` réellement
propre.

**Étendue mesurée.** Un balayage de toutes les références `App\…` du code suivi,
confrontées à l'index Git, ne remonte aucun autre cas. `app/helpers.php` est bien
suivi — la casse `App\Helpers` du balayage était un faux positif dû à
l'insensibilité à la casse du système de fichiers Windows.

**Non corrigé ici.** Deux issues seulement, et toutes deux dépassent le lot 012 :
ajouter les deux classes fait entrer du code non relu dans l'historique ; retirer
les deux appels revient sur un commit déjà écrit. L'arbitrage revient au
responsable du dépôt.

## 4. Ce que cela implique

Le lot 012 a été reconstruit dans un worktree Git propre
(`C:\laragon\www\iboa-lot012`, branche `fix/sales-stable-order-items`, base
`80c1bfd`), à partir de HEAD et non de cet arbre. Son périmètre réel s'est révélé
plus étroit que ce que l'arbre sale laissait croire : **4 hunks** dans
`OrderService.php` et non 11, **une** relation historique à corriger
(`DeliveryNoteItem::orderItem()`) et non trois — `InvoiceItem::orderItem()` et
`SalesPickingItem` n'existent pas à HEAD, ils appartiennent au travail non commité.

Une conséquence directe : **la suite verte obtenue dans cet arbre ne prouve rien
pour un commit isolé.** Elle valide 250 fichiers ensemble, dont les dépendances
croisées masquent ce qui manquerait à chacun pris seul.

Les 250 fichiers restent en l'état, intacts. Leur découpage en commits est un
travail distinct, à instruire lot par lot, chacun avec sa propre suite MySQL.

## 5. Prérequis d'un worktree exécutable

Un worktree Git ne contient que les fichiers **suivis**. Or la suite ne s'exécute
pas sans plusieurs artefacts ignorés par `.gitignore`. Chacun a été découvert par
une exécution complète qui a échoué en masse, pour un motif sans rapport avec le
code testé :

| Artefact | Symptôme si absent | Échecs observés |
|---|---|---|
| `vendor/` installé sur place | `Call to a member function connection() on null` — l'autoloader fige des chemins absolus, une jonction ne suffit pas | totalité |
| `storage/framework/views` | échec d'écriture des vues compilées | 256 |
| `public/build/manifest.json` | `Vite manifest not found` sur toute vue rendant le layout | 146 |

À quoi s'ajoute une base MySQL dédiée — deux exécutions `pest` simultanées sur la
même base se détruisent mutuellement, `RefreshDatabase` effectuant un
`migrate:fresh`.

Ces trois artefacts sont à recopier depuis le dépôt principal avant toute
exécution dans un worktree. Aucun n'indique un défaut du code.
