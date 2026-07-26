# Audit du module Achats — A3 ERP / OA METAL INDUSTRIE

> Périmètre : Demandes d'achat · Commandes fournisseurs · Réceptions ·
> Factures fournisseurs · Retours fournisseurs (+ avoirs, trésorerie, compta).
> Objectif : profondeur fonctionnelle comparable à un ERP industriel de
> référence, adaptée aux processus réels d'OA METAL INDUSTRIE.
> **Ce module n'est pas certifié production.** Décision visée : GO STAGING
> CONDITIONNEL après fermeture des anomalies critiques + preuves.

## 0. Environnement & projet (confirmation)

| Élément | Valeur |
|---|---|
| Application | A3 ERP (`APP_NAME=iboa`) |
| Entreprise | **OA METAL INDUSTRIE** |
| Chemin | `C:\laragon\www\iboa` |
| Dépôt | github.com/rrodyz/iboa.git |
| Branche | `fix/erp-cdc-prod-compta-rh` |
| PHP / Laravel / MySQL | 8.2.28 / 12.64.0 / 8.4.3 |
| Base dev / test | `iboa_erp` / `iboa_erp_test` |
| Environnement | `local` (écritures autorisées, pas de sandbox bloquant) |
| Migrations en attente | aucune |

Pas de confusion avec POS PITCH / SOULGA / autre société.

## 1. Inventaire (existant — le module est déjà dense)

**Contrôleurs** (`app/Http/Controllers/Purchases/`) : PurchaseRequest, PurchaseOrder,
PoApproval, Reception, SupplierInvoice, SupplierReturn, Rfq, PaymentSchedule,
PurchaseDashboard.

**Services** : PurchaseRequestService, PurchaseOrderService, PoApprovalService,
RfqService, SupplierInvoiceService, SupplierPaymentService, SupplierReturnService,
SupplierService, PurchaseInsightsService, BankDepositService, ExpenseReportService.

**Modèles (21)** : PurchaseRequest(+Item), PurchaseOrder(+Item), Rfq(+Item,
+Supplier, +Quote, +QuoteItem), Reception(+Item), SupplierInvoice(+Item),
SupplierPayment(+Allocation), SupplierReturn(+Item), Supplier(+Address, +Contact,
+PurchaseCondition).

**Vues** : `resources/views/achats/` — demandes-achat, commandes, receptions,
factures-fournisseurs, retours-fournisseurs, rfq, approval, insights, schedules,
pdf, dashboard.

**Migrations achats** : 29. **Routes achats** : 112.

**Permissions existantes** : `purchase_requests.{view,create,submit,validate_l1,
validate_l2,validate_l3,approve}`, `purchase_orders.{view,create,edit,validate,
confirm,cancel}`, `receptions.{view,create,validate,cancel}`,
`supplier_invoices.{view,create,edit}`, `supplier_returns.{view,create,validate}`,
`suppliers.{view,create,edit,delete}`.

**Tests achats** : AchatsAcceptance, PurchaseFullFlow, PurchaseThresholdEnforcement,
UnitConversionReception, SupplierCodeGenerator, ProductionCoilReception,
CreditNoteReturnDisposition, DepositThresholdConfig, ExpenseReport.
**Baseline exécutée : 32 tests verts (123 assertions)** sur les 5 fichiers achats
principaux.

## 2. Classification fonctionnelle (première passe)

| Fonction | État | Note |
|---|---|---|
| DA : cycle brouillon→soumise→validation L1/L2/L3→approuvée | PROUVÉE | 3 niveaux de permission présents + tests seuils |
| DA : contrôle budgétaire (engagements, disponible, dérogation) | À CONFIRMER | à vérifier lot 1 |
| DA→BC : garde reliquat (BC ≤ DA approuvée), double conversion | À CONFIRMER | lot 1 |
| BC : seuils d'approbation (montant → approbateur) | PROUVÉE | `PoApprovalService` + `po_approval_thresholds` + tests |
| BC : fournisseur bloqué refusé | À CONFIRMER | lot 1 |
| BC : taux de change figé, devise | À CONFIRMER | lot 1 |
| BC PDF (SHA-256, version, QR) | À CONFIRMER | doc §5.5 |
| Réception : partielle/reliquat, statuts | PROUVÉE | `livre_partiel`/`livre` + tests |
| Réception : conversion unité d'achat↔stock | PROUVÉE | `UnitConversionReceptionTest` |
| Réception : bobine (poids, lot, coût) | PROUVÉE | `ProductionCoilReceptionTest` |
| Réception : coût nul bloqué / frais accessoires (landed cost) | INCOMPLÈTE | aucun garde coût nul ni répartition frais trouvés |
| Réception : quarantaine (qté refusée hors stock vendable) | À CONFIRMER | lot 2 |
| Réception : annulation technique (contre-mouvements, gardes aval) | À CONFIRMER | lot 2 |
| Facture : rapprochement 3 voies **bloquant** + tolérances | PARTIELLEMENT | détection en tableau de bord (`PurchaseInsightsService`), **pas de blocage à la validation** |
| Facture : doublon `supplier_invoice_number` par fournisseur | NON IMPLÉMENTÉE | colonne nullable non-unique |
| Facture : garde qté facturée ≤ reçue | NON IMPLÉMENTÉE | aucun garde trouvé |
| Facture : période fermée bloquée | PROUVÉE | `AccountingService` FIX-COMPTA-LOCK |
| Facture : écritures 601/4452/401 paramétrées (non codées en dur) | À CONFIRMER | lot 4 |
| Retour : valorisation au coût **historique** du lot | PARTIELLEMENT | utilise `unit_price` (prix BC), pas le coût lot |
| Retour : avoir fournisseur | À CONFIRMER | lot 5 |
| Paiement via service central trésorerie | À CONFIRMER | lot 4 |
| Concurrence (reliquats DA/BC, double facture/réception) | NON TESTÉE (achats) | lot concurrence |

## 3. Anomalies (première passe — à compléter par lot)

### CRITIQUE
- **A1 — Doublon facture fournisseur non empêché.** `supplier_invoices.supplier_invoice_number`
  est *nullable* et **non unique par fournisseur** ([migration](database/migrations/2026_04_05_112634_create_supplier_invoices_table.php:23)).
  Deux saisies du même numéro fournisseur → risque de **double comptabilisation et
  double paiement**. Attendu : unicité `(supplier_id, supplier_invoice_number)` +
  garde applicative + test.
- **A2 — Rapprochement 3 voies non bloquant.** L'écart commande↔réception↔facture est
  *détecté* pour le tableau de bord mais **n'empêche pas** la validation/comptabilisation
  d'une facture hors tolérance ([PurchaseInsightsService]). Attendu : gate à la
  validation (tolérances qté/prix configurables, blocage + dérogation motivée/journalisée).
- **A3 — Facture > quantités reçues autorisée.** Aucun garde `qté facturée ≤ qté reçue`
  dans `SupplierInvoiceService`/contrôleur. Attendu : garde + dérogation.

### ÉLEVÉE
- **A4 — Réception à coût nul non bloquée / frais accessoires non répartis.** Pas de
  garde coût nul ni de *landed cost* (transport/droits/assurance). Attendu :
  valorisation provisoire au prix BC, blocage coût nul (sauf dérogation), écart de
  prix d'achat à la facture.
- **A5 — Retour non valorisé au coût historique du lot.** `SupplierReturnService`
  valorise à `unit_price` (prix BC courant) et non au **coût du lot/bobine retourné**
  ([SupplierReturnService.php:106](app/Services/SupplierReturnService.php:106)). Attendu :
  allocation par lot au coût historique, jamais le CMP courant.

### MOYENNE
- **A6 — Contrôle budgétaire DA** (engagements/disponible/dérogation) : à confirmer,
  probablement partiel.
- **A7 — Concurrence achats** non couverte par des tests dédiés (reliquats DA/BC,
  double réception/facture).

### À CONFIRMER (lots suivants)
- Fournisseur bloqué → BC refusé ; annulation réception (gardes aval) ; avoir
  fournisseur complet ; paiement via service central trésorerie ; PDF BC versionné
  SHA-256.

## 4. Séquence de correction (par lot)

1. **Lot 1 — intégrité DA→BC** : reliquat, double conversion, fournisseur bloqué,
   contrôle budgétaire + dérogation.
2. **Lot 2 — réception & valorisation** : coût nul bloqué, frais accessoires,
   quarantaine, annulation technique.
3. **Lot 3 — rapprochement facture** : 3 voies **bloquant** + tolérances + doublon
   fournisseur (A1) + garde qté (A3).
4. **Lot 4 — comptabilité & trésorerie** : matrice écritures achats paramétrées,
   paiement via service central, écart de prix.
5. **Lot 5 — retours & avoirs** : coût historique par lot (A5), avoir fournisseur.
6. **Lot 6 — permissions & concurrence** : maker-checker actions sensibles, suite
   concurrence achats.
7. **Lot 7 — ergonomie, KPI, rapports.**

Chaque lot : tests SQLite **et** MySQL (attendus indépendants), audits propres,
`git diff --check`, commit dédié `*(purchases|receiving|ap|purchase-returns)`,
pas de push sans autorisation.

## 5. Qualification

Module Achats de **profondeur fonctionnelle comparable à un ERP industriel de
référence**, adapté aux processus et contraintes réels de l'ERP A3 et d'OA METAL
INDUSTRIE. **Non certifié production.** Les anomalies CRITIQUES (A1–A3) sont
bloquantes pour un GO STAGING.
