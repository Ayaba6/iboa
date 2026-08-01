# BUG-A3-SALES-DEPOSIT-004

**Titre** — Le seuil d'acompte requis pour autoriser la production n'est pas structuré.

| | |
|---|---|
| Ouvert le | 2026-08-01 |
| Origine | Audit §1 de la correction `BUG-A3-MTO-FIN-001` |
| Gravité | P2 — fonctionnalité annoncée, non exploitable |
| Statut | **Ouvert** — aucune correction engagée |
| Module | Ventes / Production (garde financière de lancement d'OF) |

---

## 1. Énoncé

Le CDC prévoit un mode de règlement « acompte » : le client verse une fraction
du TTC, et ce versement autorise le lancement en production. Cette règle n'a
**aucun support de données exploitable**. Aucune fiche client ne peut désigner
un taux d'acompte exigible, et aucun client n'est déclaré soumis à ce régime.

Conséquence directe : la garde financière n'a pas de branche « acompte » à
appliquer. Conformément au principe *fail-closed* retenu, elle **refuse** —
elle n'invente pas de seuil.

## 2. Ce qui a été vérifié

| Point | Constat |
|---|---|
| `clients.payment_mode` | `varchar(20) NOT NULL DEFAULT 'credit'` |
| ENUM d'origine | `ENUM('cash','credit')` — migration `2026_07_02_081538` |
| Relaxation en varchar | `2026_07_24_170000`, **motif : parité de tests uniquement** ; son `down()` restaure l'ENUM |
| `StoreClientRequest` / `UpdateClientRequest` | `nullable|in:cash,credit` |
| Liste déroulante `clients/_form.blade.php` | 2 options : Comptant, Virement / Crédit |
| Valeurs présentes en base (`iboa_erp`) | `credit` × 3, `cash` × 2, **`acompte` × 0** |
| Seeders / imports | aucun n'écrit `acompte` |
| `bon_preparations.payment_mode` | `ENUM('cash','credit')` |
| `CLT-TEST-ACOMPTE` | porte `payment_mode = 'cash'` |

## 3. Les quatre supports concurrents, et pourquoi aucun ne convient

| Support | État | Pourquoi il ne suffit pas |
|---|---|---|
| `sales_settings.deposit_required_rate` | Existe, défaut 70 %, éditable | Taux **global**. Il dit *combien*, jamais *pour qui*. |
| `payment_terms.deposit_required` (bool) + `deposit_rate` (5,2) | Existent, structurés | **Aucune clé étrangère depuis `clients`.** `clients.payment_terms` est un varchar libre (`'30 jours'`). Les 8 lignes ont `deposit_required = 0`. |
| `item_categories.deposit_required` (bool) | Existe, éditable à l'écran | **Lu par aucun code applicatif.** Écrit puis oublié. |
| `clients.condition_paiement` | Varchar(60) libre | Contient par exemple `'50 % à la commande, solde avant livraison'`. Une chaîne libre ne doit pas piloter une règle financière critique. |

## 4. Impact constaté

Le défaut n'est pas seulement une fonctionnalité absente : il a **masqué**
`BUG-A3-MTO-FIN-001`.

Cinq fichiers de test créaient des clients `payment_mode => 'acompte'` ou
`'comptant'` — valeurs qu'aucun formulaire n'accepte et qu'aucune ligne ne
porte. Ils passaient au vert sur une configuration inexistante, pendant que le
mode réellement stocké (`cash`) échappait à toute vérification :

- `tests/Feature/FinancialEligibilityAmountTest.php` — dont le cas nommé
  *« comptant : 100 % exigé »*, le seul qui couvrait exactement le défaut
  survenu en exploitation, monté sur `'comptant'` ;
- `tests/Feature/DepositThresholdConfigTest.php` ;
- `tests/Feature/SharedAcompteRiskTest.php` ;
- `tests/Feature/ComptantAcompteChainTest.php` ;
- `tests/Feature/ReceiptsNoDoubleCountTest.php`.

Tous ont été refondés sur les valeurs réelles lors de la correction de
`BUG-A3-MTO-FIN-001`. `DepositThresholdConfigTest` **constate** désormais
l'absence de source structurée au lieu de la simuler ; il redeviendra un test
de comportement à la fermeture de la présente fiche.

## 5. Ce qui n'est PAS une correction

Ajouter une constante `PAYMENT_ACOMPTE = 'acompte'` au modèle `Client`. Une
constante ne crée ni le taux, ni le rattachement, ni la saisie. Elle
ajouterait une troisième valeur au vocabulaire sans qu'aucun client puisse la
porter, et rouvrirait le risque même qui a produit l'incident : un mode
déclaré côté code, absent côté données.

## 6. Piste de correction proposée (non engagée)

1. Ajouter `clients.payment_term_id` (FK nullable vers `payment_terms`).
2. Exposer le choix dans `clients/_form.blade.php` et l'autoriser dans les deux
   Form Requests.
3. Faire produire à `ProductionFinancialEligibilityService` un
   `ProductionFinancialRequirement::TYPE_DEPOSIT` lorsque le terme rattaché
   porte `deposit_required = 1`, avec `requiredAmount = ceil(total_ttc × deposit_rate / 100)`.
4. Arbitrer le rôle résiduel de `sales_settings.deposit_required_rate` :
   valeur par défaut d'un nouveau terme, ou paramètre à retirer.
5. Trancher le sort de `item_categories.deposit_required` : le câbler ou le
   supprimer — un drapeau lu par personne est un piège pour le prochain lecteur.
6. Reconvertir `DepositThresholdConfigTest` en test de comportement.

Point d'ancrage dans le code :
`ProductionFinancialRequirement::TYPE_DEPOSIT` porte le commentaire décrivant
cette absence, et aucune branche ne le produit aujourd'hui.

## 7. Décision métier requise avant correction

Quels clients d'OA METAL INDUSTRIE relèvent réellement de l'acompte, et à quel
taux ? Le paramétrage n'a de sens qu'une fois cette liste connue —
`CLT-TEST-ACOMPTE` porte aujourd'hui la mention libre
« 50 % à la commande, solde avant livraison », qui n'engage aucun contrôle.
