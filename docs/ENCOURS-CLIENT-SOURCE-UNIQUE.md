# Encours client — source unique de vérité

**Module** : Ventes · **Statut** : NO-GO staging, NO-GO production · **Base** : `iboa_erp_test` (MySQL) et SQLite `:memory:`

Ce document fige la définition **unique** de l'encours client appliquée par A3 ERP,
et recense les points du code qui ont le droit de la calculer.

---

## 1. Formule

```text
encours prévisionnel
  = encours comptable (factures ouvertes, reste dû)
  + commandes ouvertes éligibles (part non encore facturée)
  + nouvelle commande évaluée
  − acomptes confirmés encore affectables
```

Implémentation : `App\Services\CustomerCreditExposureService::compute()`.

Un client est **plafonné** seulement si `payment_mode = credit` **et** `credit_limit > 0`.
Sinon `limited = false` et `available = PHP_INT_MAX` : aucun blocage.

---

## 2. Périmètre exact

### Ce qui ENTRE dans l'encours

| Composante | Table | Filtre | Montant retenu |
|---|---|---|---|
| Encours comptable | `invoices` | `status ∈ {emise, envoyee, partiellement_payee, en_retard}`, `deleted_at IS NULL` | `remaining_amount` |
| Commandes ouvertes | `orders` | `status ∈ {en_attente_validation, confirme, en_preparation, partiellement_livre, livre}`, `deleted_at IS NULL` | `total_ttc − COALESCE(invoiced_amount, 0)`, plancher 0 |
| Nouvelle commande | — | la commande évaluée | `total_ttc` |
| Acomptes | `client_payments` | `is_acompte = 1`, `status = confirme`, `deleted_at IS NULL` | `unallocated_amount` (**soustrait**) |

### Ce qui N'ENTRE PAS, et pourquoi

| Élément | Traitement | Justification |
|---|---|---|
| Devis | exclu | Aucun engagement ferme du client. |
| Commandes `brouillon` | exclu | Non soumises : rien n'est engagé. |
| Commandes `annulee` / soldées | exclu | Plus d'exposition. |
| Factures `brouillon`, `annulee`, `payee` | exclu | Reste dû nul ou pièce sans valeur juridique. |
| **BL non facturés** | **exclu** | Déjà portés par la commande via `total_ttc − invoiced_amount`. Les compter en plus **doublerait** l'exposition. |
| Avoirs | exclu en tant que ligne | Déjà déduits du `remaining_amount` des factures qu'ils soldent. |
| Règlements non lettrés hors acompte | exclu | Leur affectation n'est pas décidée : ils ne réduisent pas un encours identifié. |
| Chèques impayés | aucun traitement spécifique | Le rejet remet la facture en reste dû ; l'encours remonte mécaniquement. |
| Factures litigieuses | incluses | Restent dues tant qu'aucun avoir ne les solde. Un litige n'efface pas la créance. |

### Taxes, devise, société

- **Taxes** : tous les montants sont **TTC** (`total_ttc`, `remaining_amount`). Le plafond est donc un plafond TTC.
- **Devise** : montants stockés en devise de la société (FCFA / XOF). **Aucune conversion n'est appliquée dans le calcul.** Une commande saisie en devise étrangère doit être convertie en amont, faute de quoi l'encours serait faux. *Limite connue, non couverte à ce jour.*
- **Société** : toutes les composantes sont filtrées sur `company_id`. La table `clients` **n'a pas** de `company_id` : un même client peut exister dans deux sociétés, mais son encours n'est jamais partagé entre elles (prouvé par le scénario D de `MySqlCreditConcurrencyTest`).

---

## 3. Points autorisés à calculer l'encours

| Appelant | Méthode | Rôle |
|---|---|---|
| `CommercialWorkflowService::submit()` | `assertMaySubmit()` | Garde bloquante à la soumission d'une commande. |
| `Client::creditExposure()` | `assessClient()` | Valeur affichée par les écrans (`available_credit`, `isOverCreditLimit()`, `credit_usage_percent`). |

**Aucun autre point du code ne doit refaire cette somme** — ni contrôleur, ni tableau de bord, ni commande d'audit.

### Divergences corrigées

Avant ce lot, trois définitions concurrentes coexistaient :

1. `CustomerCreditExposureService` — la formule ci-dessus (appliquée au **blocage**) ;
2. `Client::isOverCreditLimit()` / `available_credit` — `balance` comparé à `credit_limit` (affiché à l'**écran**) ;
3. divers écrans comptables — `SUM(remaining_amount)` seul.

Conséquence : **l'écran annonçait un disponible que le contrôle n'accordait pas.**
`balance` ne couvre que les factures ouvertes moins les avoirs disponibles
(`Client::recalculateBalance()`), sans les commandes ouvertes ni les acomptes.

Les définitions 1 et 2 sont désormais unifiées. La définition 3 subsiste dans les
écrans purement comptables (`TiersController`, `ClientStatementController`,
balance âgée, relances) où l'objet mesuré est le **solde comptable**, pas
l'exposition crédit — ce sont deux grandeurs différentes et leur coexistence est
volontaire, à condition de ne jamais les nommer pareil.

### `balance` : ce que le champ est, et ce qu'il n'est pas

`clients.balance` reste un champ **dénormalisé de confort**, recalculé par
`Client::recalculateBalance()`. Il **ne pilote plus aucun contrôle**. Limite
connue : ce recalcul n'est **pas filtré par société** alors que l'encours l'est.
Sur une base multi-société, `balance` agrégerait les deux. Non corrigé à ce jour.

---

## 4. Concurrence

Le contrôle s'exécute dans une transaction qui :

1. verrouille la commande (`Order::lockForUpdate()`) ;
2. verrouille le client (`Client::lockForUpdate()`) ;
3. lit les agrégats **en lecture verrouillante** (`SELECT ... FOR UPDATE`).

L'étape 3 n'est pas facultative. Sous l'isolation MySQL par défaut
(REPEATABLE READ), une lecture ordinaire sert la vue cohérente de la
transaction : une commande concurrente committée après le début de la
transaction reste **invisible**, même après l'obtention du verrou client.

**Ce défaut a été observé, pas supposé** : le scénario A de
`tests/Feature/MySqlCreditConcurrencyTest.php` faisait passer les **deux**
commandes concurrentes (plafond 10 000 000, encours 8 000 000, deux commandes de
1 500 000 → 11 000 000 engagés). La lecture verrouillante corrige le défaut ; le
test le prouve en rejouant deux processus PHP réels à départ synchronisé.

---

## 5. Tests

| Test | Moteur | Ce qu'il prouve |
|---|---|---|
| `CustomerCreditExposureTest` | SQLite + MySQL | Formule, statuts inclus/exclus, acomptes non affectés. |
| `MySqlCreditConcurrencyTest` scénario A | **MySQL uniquement** | Sérialisation de deux commandes sur le même reliquat. |
| `MySqlCreditConcurrencyTest` scénario B | **MySQL uniquement** | Facture émise en concurrence : aucun état partiel, encours final explicable. |
| `MySqlCreditConcurrencyTest` scénario C | **MySQL uniquement** | Seuls les acomptes confirmés réduisent l'encours. |
| `MySqlCreditConcurrencyTest` scénario D | **MySQL uniquement** | Deux sociétés ne partagent pas l'encours d'un même client. |

**Exclusion déclarée** : les quatre scénarios de course sont `skipped` sur SQLite,
avec motif affiché. Raison technique et non de commodité — SQLite `:memory:`
n'est pas partageable entre processus et n'implémente pas `SELECT ... FOR UPDATE`.
Les identifiants de tests restent donc identiques entre les deux moteurs.
