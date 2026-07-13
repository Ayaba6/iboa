<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\AccountingPeriodLock;
use App\Models\BankDeposit;
use App\Models\CashAccount;
use App\Models\CashTransfer;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\InventorySession;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\DocumentSequenceService;

/**
 * AccountingService — auto-posts all business documents to the general ledger.
 *
 * Calling convention: always call inside the same DB::transaction() as the
 * source document so the GL entry and the document are committed atomically.
 * Never delegate GL posting to a queued listener — queue failures would leave
 * the ledger out of sync with business documents.
 *
 * SYSCOHADA plan comptable (OHADA):
 *   411  Clients                      (actif)
 *   401  Fournisseurs                 (passif)
 *   7011 Ventes de marchandises       (produit)
 *   6011 Achats de marchandises       (charge)
 *   4431 TVA facturée (collectée)     (passif)
 *   4432 TVA récupérable sur achats   (actif)  — SYSCOHADA correct (4455 = État autres taxes)
 *   7085 Remises/retours sur ventes   (produit)
 *   521  Banques                      (actif)
 *   571  Caisse                       (actif)
 */
class AccountingService
{
    // [code, name, type, class_number]
    private const CHART = [
        'clients'             => ['411',  'Clients',                         'actif',   4],
        'fournisseurs'        => ['401',  'Fournisseurs',                    'passif',  4],
        'ventes'              => ['7011', 'Ventes de marchandises',          'produit', 7],
        'achats'              => ['6011', 'Achats de marchandises',          'charge',  6],
        'tva_collectee'       => ['4431', 'TVA facturée sur ventes',         'passif',  4],
        'tva_deductible'      => ['4432', 'TVA récupérable sur achats',      'actif',   4],
        'retours_ventes'      => ['7085', 'Remises accordées et retours',    'produit', 7],
        'banque'              => ['521',  'Banques, chèques postaux',        'actif',   5],
        'caisse'              => ['571',  'Caisse',                          'actif',   5],
        'mobile_money'        => ['523',  'Établissements financiers — Mobile Money', 'actif', 5],
        'stocks'              => ['3111', 'Stocks de marchandises',          'actif',   3],
        'variation_stocks'    => ['6031', 'Variations de stocks de marchandises', 'charge', 6],
        'produits_inventaire' => ['7097', 'Produits sur inventaire',         'produit', 7],
        'pertes_inventaire'   => ['6097', 'Pertes sur inventaire',           'charge',  6],
        'virements_internes'  => ['585',  'Virements de fonds',              'actif',   5],
        // [BUG-2] Retenue à la source : créance sur l'État (impôt prélevé par le client)
        'retenues_etat'       => ['4473', 'État — retenues à la source',      'actif',   4],
        // [TRESO] Écarts de caisse (clôture journalière)
        'ecart_caisse_manque'   => ['6588', 'Manquants de caisse',           'charge',  6],
        'ecart_caisse_excedent' => ['7588', 'Excédents de caisse',           'produit', 7],
        // [TRESO] Frais bancaires (rapprochement)
        'frais_bancaires'       => ['6312', 'Frais bancaires',               'charge',  6],
        // [TRESO] Opérations diverses de caisse (entrées/sorties manuelles)
        'autre_charge_caisse'   => ['6588', 'Charges diverses',              'charge',  6],
        'autre_produit_caisse'  => ['7588', 'Produits divers',              'produit', 7],
        // [TRESO] Contentieux : perte sur créance irrécouvrable
        'pertes_creances'       => ['6514', 'Pertes sur créances clients',   'charge',  6],
        // [PRODUCTION] Fabrication tôles bac — stocks matières / produits finis (SYSCOHADA)
        'stocks_matieres'           => ['321',  'Stocks de matières premières',               'actif',   3],
        'variation_stocks_matieres' => ['6032', 'Variation des stocks de matières premières', 'charge',  6],
        'stocks_produits_finis'     => ['361',  'Stocks de produits finis',                   'actif',   3],
        'production_stockee'        => ['736',  'Variation des stocks de produits finis',     'produit', 7],
        // [§13.9 CDC] Rebuts de fabrication (valorisation comptable)
        'pertes_rebuts'             => ['6582', 'Pertes sur rebuts et déchets',               'charge',  6],
    ];

    // =========================================================================
    // Public posting methods
    // =========================================================================

    /**
     * Facture client validée.
     *
     *   Sans retenue à la source :
     *   DR 411  Clients           = TTC
     *   DR 7085 Remises accordées = DISCOUNT (si > 0)
     *   CR 7011 Ventes            = HT (avant remise)
     *   CR 4431 TVA collectée     = TAX (si > 0)
     *   Équilibre : TTC + DISCOUNT = HT + TAX ✓
     *
     *   Avec retenue à la source (client soumis à retenue) :
     *   DR 411  Clients           = net_to_pay (= TTC − retenue)
     *   DR 4473 État — RAS        = withholding_amount
     *   DR 7085 Remises accordées = DISCOUNT (si > 0)
     *   CR 7011 Ventes            = HT (avant remise)
     *   CR 4431 TVA collectée     = TAX (si > 0)
     *   Équilibre : net_to_pay + retenue + DISCOUNT = TTC + DISCOUNT = HT + TAX ✓
     *
     * [BUG-2] La retenue est une créance sur l'État (le client reverse l'impôt à la DGI
     * en notre nom). Le compte 4473 reste au bilan jusqu'au remboursement ou compensation.
     */
    public function postClientInvoice(Invoice $invoice): ?JournalEntry
    {
        $company = $this->company($invoice->company_id);
        if (!$company) return null;

        $ttc         = (int) $invoice->total_ttc;
        $ht          = (int) $invoice->subtotal_ht;
        $tax         = (int) $invoice->total_tax;
        $discount    = (int) ($invoice->total_discount ?? 0);
        $withholding = (int) ($invoice->withholding_amount ?? 0);
        $netToPay    = ($withholding > 0)
            ? (int) ($invoice->net_to_pay ?? max(0, $ttc - $withholding))
            : $ttc;

        if ($ttc <= 0) return null;

        // [COMPTA-FAMILLE] Charger les comptes de vente associés aux familles + taxes par taux
        $invoice->loadMissing('items.product.family.saleAccount', 'items.taxRate.collectedAccount');

        $defaultSaleAccount = $this->account($company, 'ventes');

        // DR 411 Clients = net_to_pay (montant que le client versera réellement)
        $lines = [
            $this->line($this->account($company, 'clients'), 'Facture '.$invoice->number, $netToPay, 0),
        ];

        // [BUG-2] DR 4473 État — Retenues à la source (créance récupérable sur la DGI)
        if ($withholding > 0) {
            $lines[] = $this->line(
                $this->account($company, 'retenues_etat'),
                'Retenue à la source '.$invoice->number,
                $withholding,
                0
            );
        }

        // Ventilation du HT par compte de vente (selon famille de l'article)
        // Le montant $ht est passé comme fallback pour éviter une écriture déséquilibrée
        // si la facture n'a pas de lignes (ex. factures créées sans articles).
        $ventilation = $this->ventilateByFamilyAccount($invoice->items, $defaultSaleAccount->id, 'sale_account_id', $ht);
        foreach ($ventilation as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Facture '.$invoice->number, 0, $amount);
        }

        // [COMPTA-TVA] Ventilation de la TVA par taux (compte dédié sur TaxRate sinon 4431 global)
        if ($tax > 0) {
            $defaultTvaAccount = $this->account($company, 'tva_collectee');
            $tvaVentilation = $this->ventilateTaxByRate($invoice->items, $defaultTvaAccount->id, 'collected_account_id', $tax);
            foreach ($tvaVentilation as $accountId => $amount) {
                $lines[] = $this->lineByAccountId($accountId, 'TVA '.$invoice->number, 0, $amount);
            }
        }
        // [FIX-COMPTA-01] Global discount: debit 7085 to balance the entry
        if ($discount > 0) {
            $lines[] = $this->line($this->account($company, 'retours_ventes'), 'Remise '.$invoice->number, $discount, 0);
        }

        $entry = $this->post($company, 'vente', [
            'entry_date'  => $invoice->issued_at ?? today(),
            'reference'   => $invoice->number,
            'description' => 'Facture client '.$invoice->number.' — '.($invoice->client?->name ?? ''),
        ], $lines);

        // [AUDIT-ERP-E] Lier l'écriture à la facture source
        if ($entry) {
            $invoice->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * Paiement client enregistré.
     *   DR 521/571 Banque/Caisse = montant
     *   CR 411 Clients           = montant
     */
    public function postClientPayment(ClientPayment $payment): ?JournalEntry
    {
        $company = $this->company($payment->company_id);
        if (!$company) return null;

        $amount = (int) $payment->amount;
        if ($amount <= 0) return null;

        $tresorerie  = $this->tresorerieAccount($company, $payment->cash_account_id);
        $journalCode = ($tresorerie->code === '571') ? 'caisse' : 'banque';

        $entry = $this->post($company, $journalCode, [
            'entry_date'  => $payment->payment_date ?? today(),
            'reference'   => $payment->number,
            'description' => 'Encaissement '.$payment->number.' — '.($payment->client?->name ?? ''),
        ], [
            $this->line($tresorerie,                           'Encaissement '.$payment->number, $amount, 0),
            $this->line($this->account($company, 'clients'),   'Encaissement '.$payment->number, 0, $amount),
        ]);

        // [AUDIT-ERP-E] Lier l'écriture au paiement source
        if ($entry) {
            $payment->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * [TRESO] Virement interne entre deux comptes de trésorerie.
     *   DR compte destination (caisse 571 / banque 521)
     *   CR compte source      (caisse 571 / banque 521)
     * Total trésorerie inchangé — déplacement entre comptes.
     */
    public function postCashTransfer(CashTransfer $transfer): ?JournalEntry
    {
        $company = $this->company($transfer->company_id);
        if (!$company) return null;

        $amount = (int) $transfer->amount;
        if ($amount <= 0) return null;

        $fromAccount = $this->tresorerieAccount($company, $transfer->from_cash_account_id);
        $toAccount   = $this->tresorerieAccount($company, $transfer->to_cash_account_id);

        $label = 'Virement '.$transfer->number;
        $entry = $this->post($company, 'operations_diverses', [
            'entry_date'  => $transfer->transfer_date ?? today(),
            'reference'   => $transfer->number,
            'description' => 'Virement interne '.$transfer->number
                . ' — ' . ($transfer->fromAccount?->name ?? '?') . ' → ' . ($transfer->toAccount?->name ?? '?'),
        ], [
            $this->line($toAccount,   $label, $amount, 0),   // DR destination
            $this->line($fromAccount, $label, 0, $amount),   // CR source
        ]);

        if ($entry) {
            $transfer->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * [TRESO] Frais bancaires constatés au rapprochement (ligne relevé non comptabilisée).
     *   DR 6312 Frais bancaires / CR 521 Banque
     */
    public function postBankFee(\App\Models\CashAccount $cashAccount, int $amount, string $label, ?string $date = null): ?JournalEntry
    {
        $company = $this->company($cashAccount->company_id);
        if (!$company || $amount <= 0) return null;

        $banque = $this->tresorerieAccount($company, $cashAccount->id);
        $entryDate = $date ? \Illuminate\Support\Carbon::parse($date) : today();

        return $this->post($company, 'banque', [
            'entry_date'  => $entryDate,
            'reference'   => 'FRAIS-' . now()->format('YmdHis'),
            'description' => $label ?: 'Frais bancaires',
        ], [
            $this->line($this->account($company, 'frais_bancaires'), $label ?: 'Frais bancaires', $amount, 0),
            $this->line($banque, $label ?: 'Frais bancaires', 0, $amount),
        ]);
    }

    /**
     * [TRESO] Opération diverse de caisse (entrée/sortie manuelle).
     *   Entrée (apport, recette diverse) : DR 571 Caisse / CR 758 Produits divers
     *   Sortie (dépense diverse, petty cash) : DR 658 Charges diverses / CR 571 Caisse
     */
    public function postCashOperation(\App\Models\CashOperation $operation): ?JournalEntry
    {
        $company = $this->company($operation->company_id);
        if (!$company) return null;

        $amount = (int) $operation->amount;
        if ($amount <= 0) return null;

        $tresorerie = $this->tresorerieAccount($company, $operation->cash_account_id);
        $label      = $operation->label ?: ($operation->category ?: 'Opération de caisse');
        $journalCode = ($tresorerie->code === '571') ? 'caisse' : 'banque';

        if ($operation->direction === 'entree') {
            $lines = [
                $this->line($tresorerie, $label, $amount, 0),
                $this->line($this->account($company, 'autre_produit_caisse'), $label, 0, $amount),
            ];
        } else {
            $lines = [
                $this->line($this->account($company, 'autre_charge_caisse'), $label, $amount, 0),
                $this->line($tresorerie, $label, 0, $amount),
            ];
        }

        return $this->post($company, $journalCode, [
            'entry_date'  => $operation->operation_date ?? today(),
            'reference'   => $operation->number,
            'description' => $label,
        ], $lines);
    }

    /**
     * [TRESO] Contentieux : passage d'une créance en perte (irrécouvrable).
     *   DR 6514 Pertes sur créances clients / CR 411 Clients
     */
    public function postBadDebt(\App\Models\LitigationCase $case): ?JournalEntry
    {
        $company = $this->company($case->company_id);
        if (!$company) return null;

        $amount = (int) $case->amount;
        if ($amount <= 0) return null;

        $label = 'Créance irrécouvrable — dossier ' . $case->number;

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $case->closed_at ?? today(),
            'reference'   => $case->number,
            'description' => $label,
        ], [
            $this->line($this->account($company, 'pertes_creances'), $label, $amount, 0),
            $this->line($this->account($company, 'clients'), $label, 0, $amount),
        ]);
    }

    /**
     * [TRESO] Écart de clôture de caisse.
     *   Excédent (compté > théorique) : DR 571 Caisse / CR 7588 Excédents
     *   Manque   (compté < théorique) : DR 6588 Manquants / CR 571 Caisse
     */
    public function postCashClosureDifference(\App\Models\CashClosure $closure): ?JournalEntry
    {
        $company = $this->company($closure->company_id);
        if (!$company) return null;

        $diff = (int) $closure->difference; // compté - théorique
        if ($diff === 0) return null;

        $caisse = $this->tresorerieAccount($company, $closure->cash_account_id);
        $label  = 'Écart clôture ' . $closure->number;

        if ($diff > 0) {
            $lines = [
                $this->line($caisse, $label, $diff, 0),
                $this->line($this->account($company, 'ecart_caisse_excedent'), $label, 0, $diff),
            ];
        } else {
            $m = abs($diff);
            $lines = [
                $this->line($this->account($company, 'ecart_caisse_manque'), $label, $m, 0),
                $this->line($caisse, $label, 0, $m),
            ];
        }

        $entry = $this->post($company, 'operations_diverses', [
            'entry_date'  => $closure->closure_date ?? today(),
            'reference'   => $closure->number,
            'description' => 'Écart de clôture de caisse ' . $closure->number
                . ' — ' . ($closure->cashAccount?->name ?? ''),
        ], $lines);

        if ($entry) {
            $closure->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * Facture fournisseur validée.
     *   DR 6011 Achats      = HT
     *   DR 4432 TVA déd.   = TAX (si > 0)
     *   CR 401 Fournisseurs = TTC
     */
    public function postSupplierInvoice(SupplierInvoice $invoice): ?JournalEntry
    {
        $company = $this->company($invoice->company_id);
        if (!$company) return null;

        $ttc = (int) $invoice->total_ttc;
        $ht  = (int) $invoice->subtotal_ht;
        $tax = (int) $invoice->total_tax;
        if ($ttc <= 0) return null;

        // [COMPTA-FAMILLE] Charger les comptes d'achat associés aux familles + taxes par taux
        $invoice->loadMissing('items.product.family.purchaseAccount', 'items.taxRate.deductibleAccount');

        $defaultPurchaseAccount = $this->account($company, 'achats');

        $lines = [];

        // Ventilation du HT par compte d'achat (selon famille de l'article)
        $ventilation = $this->ventilateByFamilyAccount($invoice->items, $defaultPurchaseAccount->id, 'purchase_account_id', $ht);
        foreach ($ventilation as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Fact. fourn. '.$invoice->number, $amount, 0);
        }

        $lines[] = $this->line($this->account($company, 'fournisseurs'), 'Fact. fourn. '.$invoice->number, 0, $ttc);

        // [COMPTA-TVA] Ventilation de la TVA déductible par taux
        if ($tax > 0) {
            $defaultTvaAccount = $this->account($company, 'tva_deductible');
            $tvaVentilation = $this->ventilateTaxByRate($invoice->items, $defaultTvaAccount->id, 'deductible_account_id', $tax);
            foreach ($tvaVentilation as $accountId => $amount) {
                $lines[] = $this->lineByAccountId($accountId, 'TVA '.$invoice->number, $amount, 0);
            }
        }

        $entry = $this->post($company, 'achat', [
            'entry_date'  => $invoice->received_at ?? today(),
            'reference'   => $invoice->number,
            'description' => 'Facture fournisseur '.$invoice->number.' — '.($invoice->supplier?->name ?? ''),
        ], $lines);

        // [AUDIT-ERP-E] Lier l'écriture à la facture fournisseur source
        if ($entry) {
            $invoice->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * Paiement fournisseur enregistré.
     *   DR 401 Fournisseurs  = montant
     *   CR 521/571 Banque    = montant
     */
    public function postSupplierPayment(SupplierPayment $payment): ?JournalEntry
    {
        $company = $this->company($payment->company_id);
        if (!$company) return null;

        $amount = (int) $payment->amount;
        if ($amount <= 0) return null;

        $tresorerie  = $this->tresorerieAccount($company, $payment->cash_account_id);
        $journalCode = ($tresorerie->code === '571') ? 'caisse' : 'banque';

        $entry = $this->post($company, $journalCode, [
            'entry_date'  => $payment->payment_date ?? today(),
            'reference'   => $payment->number,
            'description' => 'Décaissement '.$payment->number.' — '.($payment->supplier?->name ?? ''),
        ], [
            $this->line($this->account($company, 'fournisseurs'), 'Décaissement '.$payment->number, $amount, 0),
            $this->line($tresorerie,                              'Décaissement '.$payment->number, 0, $amount),
        ]);

        // [AUDIT-ERP-E] Lier l'écriture au paiement fournisseur source
        if ($entry) {
            $payment->updateQuietly(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    /**
     * Avoir client validé (annulation partielle ou totale de facture).
     *   DR 7085 Retours ventes = HT
     *   DR 4431 TVA collectée  = TAX (si > 0)
     *   CR 411 Clients         = TTC
     */
    public function postCreditNote(CreditNote $creditNote): ?JournalEntry
    {
        $company = $this->company($creditNote->company_id);
        if (!$company) return null;

        $ttc = (int) $creditNote->total_ttc;
        $ht  = (int) $creditNote->subtotal_ht;
        $tax = (int) $creditNote->total_tax;
        if ($ttc <= 0) return null;

        // [COMPTA-FAMILLE] Ventilation HT par compte de vente famille (contrepasse la vente)
        $creditNote->loadMissing('items.product.family.saleAccount', 'items.taxRate.collectedAccount');
        $defaultSaleAccount = $this->account($company, 'retours_ventes');

        $lines = [];

        // Ventilation : DR compte vente famille (contrepasse la vente initiale)
        $ventilation = $this->ventilateByFamilyAccount($creditNote->items, $defaultSaleAccount->id, 'sale_account_id', $ht);
        foreach ($ventilation as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Avoir '.$creditNote->number, $amount, 0);
        }

        $lines[] = $this->line($this->account($company, 'clients'), 'Avoir '.$creditNote->number, 0, $ttc);

        if ($tax > 0) {
            $defaultTvaAccount = $this->account($company, 'tva_collectee');
            $tvaVentilation = $this->ventilateTaxByRate($creditNote->items, $defaultTvaAccount->id, 'collected_account_id', $tax);
            foreach ($tvaVentilation as $accountId => $amount) {
                $lines[] = $this->lineByAccountId($accountId, 'TVA avoir '.$creditNote->number, $amount, 0);
            }
        }

        return $this->post($company, 'vente', [
            'entry_date'  => $creditNote->issued_at ?? today(),
            'reference'   => $creditNote->number,
            'description' => 'Avoir '.$creditNote->number.' — '.($creditNote->client?->name ?? ''),
        ], $lines);
    }

    /**
     * Retour fournisseur validé.
     *   DR 401 Fournisseurs = TTC   (réduit notre dette)
     *   CR 6011 Achats      = HT
     *   CR 4432 TVA déd.   = TAX (si > 0)
     */
    public function postSupplierReturn(SupplierReturn $return): ?JournalEntry
    {
        $company = $this->company($return->company_id);
        if (!$company) return null;

        $ttc = (int) $return->total_ttc;
        $ht  = (int) $return->subtotal_ht;
        $tax = (int) $return->total_tax;
        if ($ttc <= 0) return null;

        // [COMPTA-FAMILLE] Ventilation HT par compte d'achat famille (contrepasse l'achat)
        $return->loadMissing('items.product.family.purchaseAccount', 'items.taxRate.deductibleAccount');
        $defaultPurchaseAccount = $this->account($company, 'achats');

        $lines = [
            $this->line($this->account($company, 'fournisseurs'), 'Retour fourn. '.$return->number, $ttc, 0),
        ];

        // Ventilation : CR compte achat famille (réduit l'achat initial)
        $ventilation = $this->ventilateByFamilyAccount($return->items, $defaultPurchaseAccount->id, 'purchase_account_id', $ht);
        foreach ($ventilation as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Retour fourn. '.$return->number, 0, $amount);
        }

        if ($tax > 0) {
            $defaultTvaAccount = $this->account($company, 'tva_deductible');
            $tvaVentilation = $this->ventilateTaxByRate($return->items, $defaultTvaAccount->id, 'deductible_account_id', $tax);
            foreach ($tvaVentilation as $accountId => $amount) {
                $lines[] = $this->lineByAccountId($accountId, 'TVA '.$return->number, 0, $amount);
            }
        }

        return $this->post($company, 'achat', [
            'entry_date'  => $return->returned_at ?? today(),
            'reference'   => $return->number,
            'description' => 'Retour fournisseur '.$return->number.' — '.($return->supplier?->name ?? ''),
        ], $lines);
    }

    /**
     * [COMPTA-STOCK] Sortie de stock automatique lors de la validation d'une facture client.
     * Pour chaque article stockable : impacte le compte stock famille et 6031 Variation.
     *
     *   DR 6031 Variation de stocks  = cout total (somme des coûts d'achat × qté)
     *   CR 311  Stocks famille       = même montant
     *
     * Coût utilisé : product.purchase_price × quantité (méthode standard cost).
     * Articles non stockables (services) ignorés.
     */
    public function postSaleStockMovement(Invoice $invoice): ?JournalEntry
    {
        $company = $this->company($invoice->company_id);
        if (!$company) return null;

        $invoice->loadMissing('items.product.family.stockAccount');

        $defaultStockAccount = $this->account($company, 'stocks');
        $variationAccount    = $this->account($company, 'variation_stocks');

        // Calcul du coût par compte stock (ventilation)
        $buckets = [];
        $totalCost = 0;
        foreach ($invoice->items as $item) {
            $product = $item->product;
            if (!$product || !$product->is_stockable) continue;

            // [MARGES-CMP] Valoriser la sortie de stock au CMP figé sur la ligne
            // (snapshotItemCosts s'exécute avant cette méthode), repli CMP produit
            // puis prix d'achat. Aligne la compta sur la valorisation CMP du stock.
            $unitCost = (float) ($item->unit_cost ?: 0);
            if ($unitCost <= 0) {
                $unitCost = (float) ($product->weighted_avg_cost ?: 0);
            }
            if ($unitCost <= 0) {
                $unitCost = (float) ($product->purchase_price ?? 0);
            }

            $cost = (int) round((float) $item->quantity * $unitCost);
            if ($cost <= 0) continue;

            $accountId = $product->family?->stock_account_id ?? $defaultStockAccount->id;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $cost;
            $totalCost += $cost;
        }

        if ($totalCost <= 0) return null; // rien à comptabiliser (services seulement, ou coûts manquants)

        $lines = [
            $this->line($variationAccount, 'Sortie stock '.$invoice->number, $totalCost, 0),
        ];
        foreach ($buckets as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Sortie stock '.$invoice->number, 0, $amount);
        }

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $invoice->issued_at ?? today(),
            'reference'   => $invoice->number.'-STK',
            'description' => 'Sortie de stock sur facture '.$invoice->number,
        ], $lines);
    }

    /**
     * [COMPTA-STOCK] Entrée de stock automatique lors de la validation d'une facture fournisseur.
     *
     *   DR 311  Stocks famille       = HT (basé sur le prix d'achat réel de la ligne)
     *   CR 6031 Variation de stocks  = même montant
     *
     * Articles non stockables (services) ignorés.
     */
    public function postPurchaseStockMovement(SupplierInvoice $invoice): ?JournalEntry
    {
        $company = $this->company($invoice->company_id);
        if (!$company) return null;

        $invoice->loadMissing('items.product.family.stockAccount');

        $defaultStockAccount = $this->account($company, 'stocks');

        $buckets = [];
        $totalCost = 0;
        foreach ($invoice->items as $item) {
            $product = $item->product;
            if (!$product || !$product->is_stockable) continue;

            // Prix d'achat réel = line_total_ht (intègre déjà la remise éventuelle)
            $cost = (int) ($item->line_total_ht ?? 0);
            if ($cost <= 0) continue;

            $accountId = $product->family?->stock_account_id ?? $defaultStockAccount->id;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $cost;
            $totalCost += $cost;
        }

        if ($totalCost <= 0) return null;

        // [SYSCOHADA] Compte de variation adossé au compte de stock (par bucket) :
        //   321 MP → 6032, 361 PF → 736, 3111 marchandises → 6031.
        $lines = [];
        foreach ($buckets as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Entrée stock '.$invoice->number, $amount, 0);
            $lines[] = $this->line($this->variationForStock($company, (int) $accountId), 'Entrée stock '.$invoice->number, 0, $amount);
        }

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $invoice->received_at ?? today(),
            'reference'   => $invoice->number.'-STK',
            'description' => 'Entrée de stock sur fact. fournisseur '.$invoice->number,
        ], $lines);
    }

    /**
     * Compte de variation de stock adossé à un compte de stock (SYSCOHADA) :
     *   321  Stocks matières premières → 6032 Variation MP
     *   361  Produits finis            → 736  Production stockée
     *   3111 Marchandises (défaut)     → 6031 Variation marchandises
     */
    private function variationForStock(Company $company, int $stockAccountId): \App\Models\Account
    {
        $code = \App\Models\Account::find($stockAccountId)?->code;
        $key = match ($code) {
            '321'   => 'variation_stocks_matieres',
            '361'   => 'production_stockee',
            default => 'variation_stocks',
        };

        return $this->account($company, $key);
    }

    /**
     * [COMPTA-STOCK] Retour en stock automatique lors d'un avoir client (contrepasse la sortie de stock).
     *
     *   DR 311  Stocks famille       = cout
     *   CR 6031 Variation de stocks  = cout
     */
    public function postCreditNoteStockMovement(CreditNote $creditNote): ?JournalEntry
    {
        $company = $this->company($creditNote->company_id);
        if (!$company) return null;

        $creditNote->loadMissing('items.product.family.stockAccount');

        $defaultStockAccount = $this->account($company, 'stocks');
        $variationAccount    = $this->account($company, 'variation_stocks');

        $buckets = [];
        $totalCost = 0;
        foreach ($creditNote->items as $item) {
            $product = $item->product;
            if (!$product || !$product->is_stockable) continue;

            // [VEN Retour] Les lignes mises au rebut ne réintègrent pas le stock :
            // pas d'entrée d'actif au grand livre (biens détruits, déjà sortis à la vente).
            if (($item->disposition ?? 'restock') === 'rebut') continue;

            $cost = (int) round((float) $item->quantity * (float) ($product->purchase_price ?? 0));
            if ($cost <= 0) continue;

            $accountId = $product->family?->stock_account_id ?? $defaultStockAccount->id;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $cost;
            $totalCost += $cost;
        }

        if ($totalCost <= 0) return null;

        $lines = [];
        foreach ($buckets as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Retour stock avoir '.$creditNote->number, $amount, 0);
        }
        $lines[] = $this->line($variationAccount, 'Retour stock avoir '.$creditNote->number, 0, $totalCost);

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $creditNote->issued_at ?? today(),
            'reference'   => $creditNote->number.'-STK',
            'description' => 'Retour en stock sur avoir '.$creditNote->number,
        ], $lines);
    }

    /**
     * [COMPTA-STOCK] Sortie de stock lors d'un retour fournisseur (contrepasse l'entrée).
     *
     *   DR 6031 Variation de stocks  = HT du retour
     *   CR 311  Stocks famille       = même montant
     */
    public function postSupplierReturnStockMovement(SupplierReturn $return): ?JournalEntry
    {
        $company = $this->company($return->company_id);
        if (!$company) return null;

        $return->loadMissing('items.product.family.stockAccount');

        $defaultStockAccount = $this->account($company, 'stocks');
        $variationAccount    = $this->account($company, 'variation_stocks');

        $buckets = [];
        $totalCost = 0;
        foreach ($return->items as $item) {
            $product = $item->product;
            if (!$product || !$product->is_stockable) continue;

            $cost = (int) ($item->line_total_ht ?? 0);
            if ($cost <= 0) continue;

            $accountId = $product->family?->stock_account_id ?? $defaultStockAccount->id;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $cost;
            $totalCost += $cost;
        }

        if ($totalCost <= 0) return null;

        $lines = [
            $this->line($variationAccount, 'Sortie stock retour fourn. '.$return->number, $totalCost, 0),
        ];
        foreach ($buckets as $accountId => $amount) {
            $lines[] = $this->lineByAccountId($accountId, 'Sortie stock retour fourn. '.$return->number, 0, $amount);
        }

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $return->returned_at ?? today(),
            'reference'   => $return->number.'-STK',
            'description' => 'Sortie de stock sur retour fournisseur '.$return->number,
        ], $lines);
    }

    /**
     * Écarts d'inventaire — UNE seule écriture consolidée par session.
     *   Écarts positifs (gains) : DR 3111 Stocks, CR 7097 Produits sur inventaire
     *   Écarts négatifs (pertes) : DR 6097 Pertes sur inventaire, CR 3111 Stocks
     *
     * [FIX-COMPTA-02] Previously created one entry per item (O(N) entries for large
     * inventories). Now consolidates all variances into a single balanced entry per session.
     *   - One line per impacted GL account (stocks, produits, pertes)
     *   - Net debit/credit computed across all items before posting
     */
    public function postInventoryVariances(InventorySession $session): void
    {
        $company = $this->company($session->company_id);
        if (!$company) return;

        $session->loadMissing('items');

        // Guard: skip if there are no non-zero variances at all
        $hasVariance = $session->items->contains(fn($i) => abs((float) $i->variance_quantity) > 0.0001);
        if (!$hasVariance) return;

        $totalGainValue = 0;   // sum of positive variance values → DR Stocks / CR Produits
        $totalLossValue = 0;   // sum of negative variance values → DR Pertes / CR Stocks

        foreach ($session->items as $item) {
            $variance = (float) $item->variance_quantity;
            if (abs($variance) < 0.0001) continue;

            $value = (int) round(abs($variance) * (float) $item->unit_cost);
            if ($value <= 0) continue;

            if ($variance > 0) {
                $totalGainValue += $value;
            } else {
                $totalLossValue += $value;
            }
        }

        if ($totalGainValue === 0 && $totalLossValue === 0) return;

        $label       = 'Écarts inventaire '.$session->number;
        $stockAcct   = $this->account($company, 'stocks');
        $prodAcct    = $this->account($company, 'produits_inventaire');
        $pertesAcct  = $this->account($company, 'pertes_inventaire');

        // Build consolidated lines:
        //   Net stock impact = gains − losses
        $netStockDebit  = max(0, $totalGainValue - $totalLossValue);
        $netStockCredit = max(0, $totalLossValue - $totalGainValue);

        $lines = [];

        if ($netStockDebit > 0) {
            $lines[] = $this->line($stockAcct,  $label, $netStockDebit, 0);
        }
        if ($netStockCredit > 0) {
            $lines[] = $this->line($stockAcct,  $label, 0, $netStockCredit);
        }
        if ($totalGainValue > 0) {
            $lines[] = $this->line($prodAcct,   $label, 0, $totalGainValue);
        }
        if ($totalLossValue > 0) {
            $lines[] = $this->line($pertesAcct, $label, $totalLossValue, 0);
        }

        if (empty($lines)) return;

        $this->post($company, 'operations_diverses', [
            'entry_date'  => $session->validated_at ?? today(),
            'reference'   => $session->number,
            'description' => $label,
        ], $lines);
    }

    /**
     * [COMPTA-BANQUE] Remise en banque validée.
     *
     * Écriture générée lors de la validation d'une remise :
     *
     *   DR 521 Banque (compte destination)         = total_amount
     *   CR 571 Caisse / 521 Banque (source)        = total_amount   ← si source renseignée
     *   CR 585 Virements de fonds (transit)        = total_amount   ← sinon (dépôt direct)
     *
     * Le compte de débit dépend du type du cash_account cible :
     *   - type 'banque' ou 'mobile_money' → 521 Banques
     *   - type 'caisse'                   → 571 Caisse
     *
     * Le journal utilisé est le journal Banque (type = 'banque').
     */
    public function postBankDeposit(BankDeposit $deposit): ?JournalEntry
    {
        $company = $this->company($deposit->company_id);
        if (!$company) return null;

        $amount = (int) $deposit->total_amount;
        if ($amount <= 0) return null;

        $deposit->loadMissing(['cashAccount', 'sourceCashAccount']);

        // Compte de débit : cash account de destination
        $destAccount   = $this->tresorerieAccount($company, $deposit->cash_account_id);

        // Compte de crédit : cash account source (si renseigné) ou virement interne
        $sourceAccount = $deposit->source_cash_account_id
            ? $this->tresorerieAccount($company, $deposit->source_cash_account_id)
            : $this->account($company, 'virements_internes');

        $label       = 'Remise en banque ' . $deposit->number;
        $journalCode = ($destAccount->code === '571') ? 'caisse' : 'banque';

        return $this->post($company, $journalCode, [
            'entry_date'  => $deposit->deposit_date->toDateString(),
            'reference'   => $deposit->number,
            'description' => $label,
        ], [
            $this->line($destAccount,   $label, $amount, 0),
            $this->line($sourceAccount, $label, 0, $amount),
        ]);
    }

    /**
     * [FIX-CRITIQUE] Reverse a validated journal entry (contrepassation).
     * Creates a mirror entry with debits and credits swapped, in the same journal.
     * Used when a validated invoice is cancelled.
     */
    public function reverseEntry(JournalEntry $entry, string $reason = 'Contrepassation'): ?JournalEntry
    {
        $company = $this->company($entry->company_id);
        if (!$company) return null;

        // [COMPTA-FIX-03] Idempotence : refuse to reverse twice.
        if ($entry->reversed_by_entry_id) {
            $existing = JournalEntry::find($entry->reversed_by_entry_id);
            throw new \RuntimeException(sprintf(
                "L'écriture %s a déjà été contre-passée par %s. Une seconde contre-passation n'est pas autorisée.",
                $entry->number,
                $existing?->number ?? '(introuvable)'
            ));
        }

        // [COMPTA-FIX-03] Block reverse-of-reverse — only original entries can be contre-passées.
        if ($entry->reverses_entry_id) {
            throw new \RuntimeException(sprintf(
                "L'écriture %s est elle-même une contre-passation — vous ne pouvez pas la contre-passer à nouveau.",
                $entry->number
            ));
        }

        $entry->load('lines');
        if ($entry->lines->isEmpty()) return null;

        $reversedLines = $entry->lines->map(fn($l) => $this->line(
            Account::findOrFail($l->account_id),
            $reason . ' — ' . $l->label,
            (int) $l->credit,  // swap: credit becomes debit
            (int) $l->debit    // swap: debit becomes credit
        ))->all();

        $reversal = $this->post($company, $entry->journalType?->type ?? 'vente', [
            'entry_date'  => today(),
            'reference'   => 'ANNUL-' . $entry->reference,
            'description' => $reason . ' : ' . $entry->description,
        ], $reversedLines);

        // [COMPTA-FIX-03] Link both entries bidirectionally — original tracks its reversal,
        // reversal tracks the original.
        if ($reversal) {
            $reversal->update(['reverses_entry_id' => $entry->id]);
            $entry->update(['reversed_by_entry_id' => $reversal->id]);
        }

        return $reversal;
    }

    // =========================================================================
    // [PRODUCTION] Écritures de fabrication (SYSCOHADA) — idempotentes par référence
    // =========================================================================

    /**
     * Consommation de matière sur OF (sortie de stock matières premières).
     *   DR 6032 Variation des stocks de matières premières
     *   CR 321  Stocks de matières premières
     */
    public function postProductionConsumption(ProductionOrder $order, int $amount): ?JournalEntry
    {
        $company = $this->company($order->company_id);
        if (! $company || $amount <= 0) {
            return null;
        }

        $reference = $order->number . '-CONS';
        if ($this->entryExists($company, $reference)) {
            return null;
        }

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $order->finished_at ?? today(),
            'reference'   => $reference,
            'description' => 'Consommation matière production ' . $order->number,
        ], [
            $this->line($this->account($company, 'variation_stocks_matieres'), 'Conso matière ' . $order->number, $amount, 0),
            $this->line($this->account($company, 'stocks_matieres'), 'Conso matière ' . $order->number, 0, $amount),
        ]);
    }

    /**
     * Production stockée (entrée de produits finis en stock).
     *   DR 361  Stocks de produits finis
     *   CR 736  Variation des stocks de produits finis (production stockée)
     */
    public function postProductionStockEntry(ProductionOrder $order, int $amount): ?JournalEntry
    {
        $company = $this->company($order->company_id);
        if (! $company || $amount <= 0) {
            return null;
        }

        $reference = $order->number . '-PROD';
        if ($this->entryExists($company, $reference)) {
            return null;
        }

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $order->finished_at ?? today(),
            'reference'   => $reference,
            'description' => 'Production stockée ' . $order->number,
        ], [
            $this->line($this->account($company, 'stocks_produits_finis'), 'Entrée PF ' . $order->number, $amount, 0),
            $this->line($this->account($company, 'production_stockee'), 'Entrée PF ' . $order->number, 0, $amount),
        ]);
    }

    /**
     * [§13.9 CDC] Valorisation comptable d'un rebut de fabrication (après double validation).
     *   DR 6582 Pertes sur rebuts et déchets = valeur du rebut
     *   CR 321  Stocks de matières premières = valeur du rebut
     *
     * La valeur est fournie par ProductionWaste::value (en FCFA entier).
     * Idempotent sur la référence WASTE-{id}.
     */
    public function postWaste(\App\Modules\Production\Models\ProductionWaste $waste): ?JournalEntry
    {
        $company = $this->company($waste->company_id);
        if (! $company || $waste->value <= 0) {
            return null;
        }

        $reference = 'WASTE-' . $waste->id;
        if ($this->entryExists($company, $reference)) {
            return null;
        }

        $order = $waste->productionOrder;
        $label = 'Rebut OF ' . ($order?->number ?? '#' . $waste->production_order_id)
               . ' — ' . $waste->causeLabel();

        return $this->post($company, 'operations_diverses', [
            'entry_date'  => $waste->updated_at ?? today(),
            'reference'   => $reference,
            'description' => $label,
        ], [
            $this->line($this->account($company, 'pertes_rebuts'), $label, $waste->value, 0),
            $this->line($this->account($company, 'stocks_matieres'), $label, 0, $waste->value),
        ]);
    }

    private function entryExists(Company $company, string $reference): bool
    {
        return JournalEntry::where('company_id', $company->id)->where('reference', $reference)->exists();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create a validated journal entry + lines and update account balances.
     * Status is set to 'valide' immediately — auto-posting skips the brouillon step.
     */
    private function post(Company $company, string $journalTypeCode, array $header, array $lines): JournalEntry
    {
        // [FIX-COMPTA-LOCK] Refuser toute écriture automatique sur une période verrouillée.
        $entryDate = $header['entry_date'] ?? today();
        $lock = AccountingPeriodLock::findForDate($company->id, $entryDate);
        if ($lock) {
            throw new \RuntimeException(sprintf(
                "Impossible de comptabiliser : la période « %s » est verrouillée (par %s le %s). "
                . "Déverrouillez la période dans Comptabilité → Périodes, ou corrigez la date du document.",
                $lock->label(),
                $lock->lockedBy?->name ?? 'système',
                $lock->locked_at?->format('d/m/Y') ?? '?'
            ));
        }

        $totalDebit  = (int) collect($lines)->sum('debit');
        $totalCredit = (int) collect($lines)->sum('credit');

        if ($totalDebit !== $totalCredit) {
            Log::error('AccountingService: écriture déséquilibrée', [
                'reference'    => $header['reference'] ?? null,
                'total_debit'  => $totalDebit,
                'total_credit' => $totalCredit,
            ]);
            throw new \RuntimeException('Écriture comptable déséquilibrée (débit '.$totalDebit.' ≠ crédit '.$totalCredit.').');
        }

        $journalType = $this->journalType($company, $journalTypeCode);
        $number      = $this->nextEntryNumber($company);
        $userId      = Auth::id();

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'journal_type_id' => $journalType?->id,
            'fiscal_year_id'  => $company->current_fiscal_year_id,
            'number'          => $number,
            'entry_date'      => $header['entry_date'] ?? today(),
            'value_date'      => $header['value_date']  ?? $header['entry_date'] ?? today(),
            'reference'       => $header['reference']   ?? null,
            'description'     => $header['description'] ?? null,
            'status'          => 'valide',
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'created_by'      => $userId,
            'validated_by'    => $userId,
            'validated_at'    => now(),
        ]);

        foreach ($lines as $i => $line) {
            $entry->lines()->create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $line['account_id'],
                'label'            => $line['label'] ?? ($header['description'] ?? ''),
                'debit'            => (int) ($line['debit']  ?? 0),
                'credit'           => (int) ($line['credit'] ?? 0),
                'sort_order'       => $i,
            ]);

            // Update account running balances atomically
            Account::where('id', $line['account_id'])
                ->increment('debit_balance',  (int) ($line['debit']  ?? 0));
            Account::where('id', $line['account_id'])
                ->increment('credit_balance', (int) ($line['credit'] ?? 0));
        }

        return $entry;
    }

    private function line(Account $account, string $label, int $debit, int $credit): array
    {
        return ['account_id' => $account->id, 'label' => $label, 'debit' => $debit, 'credit' => $credit];
    }

    /**
     * Helper line variant qui prend directement un account_id (utile pour les ventilations).
     */
    private function lineByAccountId(int $accountId, string $label, int $debit, int $credit): array
    {
        return ['account_id' => $accountId, 'label' => $label, 'debit' => $debit, 'credit' => $credit];
    }

    /**
     * [COMPTA-TVA] Ventile le montant de TVA par compte comptable en se basant
     * sur le TaxRate de chaque ligne. Si le taux n'a pas de compte GL dédié,
     * fallback sur le compte 4431 (collectée) ou 4455 (déductible) global.
     *
     * @param  iterable  $items              Items avec relation taxRate chargée
     * @param  int       $fallbackAccountId  Compte par défaut
     * @param  string    $accountField       'collected_account_id' ou 'deductible_account_id'
     * @return array<int,int>                [accountId => totalTva, ...]
     */
    private function ventilateTaxByRate($items, int $fallbackAccountId, string $accountField, int $fallbackAmount = 0): array
    {
        $buckets = [];
        foreach ($items as $item) {
            $tva = (int) ($item->line_tax ?? 0);
            if ($tva <= 0) continue;

            $accountId = $item->taxRate?->{$accountField} ?? $fallbackAccountId;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $tva;
        }
        // Fallback quand aucun article ne contribue à la TVA
        if ((empty($buckets) || array_sum($buckets) === 0) && $fallbackAmount > 0) {
            $buckets[$fallbackAccountId] = $fallbackAmount;
        }
        return $buckets;
    }

    /**
     * [COMPTA-FAMILLE] Ventile la base HT par compte comptable en se basant sur
     * la famille de l'article de chaque ligne. Les items sans famille (ou dont
     * la famille n'a pas de compte configuré) sont rattachés au $fallbackAccountId.
     *
     * @param  iterable  $items              Items de facture (Invoice ou SupplierInvoice)
     * @param  int       $fallbackAccountId  Compte par défaut (7011 ou 6011)
     * @param  string    $accountField       'sale_account_id' ou 'purchase_account_id'
     * @return array<int,int>                [accountId => totalHt, ...]
     */
    private function ventilateByFamilyAccount($items, int $fallbackAccountId, string $accountField, int $fallbackAmount = 0): array
    {
        $buckets = [];
        foreach ($items as $item) {
            $ht = (int) ($item->line_total_ht ?? 0);
            if ($ht <= 0) continue;

            $accountId = $item->product?->family?->{$accountField} ?? $fallbackAccountId;
            $buckets[$accountId] = ($buckets[$accountId] ?? 0) + $ht;
        }

        // Si aucun article ne contribue (items vides ou montants nuls),
        // utiliser le montant total de repli pour que l'écriture reste équilibrée.
        if ((empty($buckets) || array_sum($buckets) === 0) && $fallbackAmount > 0) {
            $buckets[$fallbackAccountId] = $fallbackAmount;
        } elseif (empty($buckets)) {
            $buckets[$fallbackAccountId] = 0;
        }

        return $buckets;
    }

    private function account(Company $company, string $key): Account
    {
        // [Paramètres comptables] Compte surchargé par la configuration comptable
        // de la société s'il est explicitement défini ; sinon fallback plan SYSCOHADA.
        $overrideId = $this->overriddenAccountId($company, $key);
        if ($overrideId) {
            $override = Account::find($overrideId);
            if ($override && (int) $override->company_id === (int) $company->id) {
                return $override;
            }
        }

        [$code, $name, $type, $classNumber] = self::CHART[$key];

        return Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'account_class_id' => $this->accountClassId($company, $classNumber),
                'name'             => $name,
                'type'             => $type,
                'is_detail'        => true,
                'is_active'        => true,
                'debit_balance'    => 0,
                'credit_balance'   => 0,
            ]
        );
    }

    /**
     * [Paramètres comptables] Résout l'ID du compte surchargé pour une clé métier
     * depuis accounting_settings (mémorisé par société). Retourne null si non
     * paramétré → le moteur retombe sur le plan SYSCOHADA standard (zéro régression).
     */
    private array $accountingSettingsCache = [];

    private function overriddenAccountId(Company $company, string $key): ?int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('accounting_settings')) {
            return null;
        }

        if (!array_key_exists($company->id, $this->accountingSettingsCache)) {
            $this->accountingSettingsCache[$company->id] = \App\Models\AccountingSetting::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->first();
        }

        $setting = $this->accountingSettingsCache[$company->id];

        return $setting?->accountIdFor($key);
    }

    /**
     * Resolve the treasury GL account (521 Banque or 571 Caisse) from the
     * CashAccount that was used for the payment.
     */
    private function tresorerieAccount(Company $company, ?int $cashAccountId): Account
    {
        if ($cashAccountId) {
            $cashAccount = CashAccount::find($cashAccountId);
            if ($cashAccount) {
                // [RELATION MÉTIER] Chaque type de compte de trésorerie a son
                // compte SYSCOHADA : caisse → 571, mobile money → 523, banque → 521.
                $key = match ($cashAccount->type) {
                    'caisse'       => 'caisse',
                    'mobile_money' => 'mobile_money',
                    default        => 'banque',
                };
                return $this->account($company, $key);
            }
        }
        // Default to banque when no cash account linked
        return $this->account($company, 'banque');
    }

    private function journalType(Company $company, string $type): JournalType
    {
        $existing = JournalType::where('company_id', $company->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Auto-create a default journal type when none exists (e.g. in test or fresh company)
        $defaults = [
            'vente'   => ['code' => 'VT', 'name' => 'Journal des ventes'],
            'achat'   => ['code' => 'AC', 'name' => 'Journal des achats'],
            'banque'  => ['code' => 'BQ', 'name' => 'Journal de banque'],
            'caisse'  => ['code' => 'CA', 'name' => 'Journal de caisse'],
            'operations_diverses' => ['code' => 'OD', 'name' => 'Opérations diverses'],
        ];

        $default = $defaults[$type] ?? ['code' => strtoupper(substr($type, 0, 2)), 'name' => 'Journal '.$type];

        return JournalType::firstOrCreate(
            ['company_id' => $company->id, 'type' => $type],
            [
                'code'      => $default['code'],
                'name'      => $default['name'],
                'is_active' => true,
            ]
        );
    }

    private function accountClassId(Company $company, int $number): int
    {
        return AccountClass::firstOrCreate(
            ['company_id' => $company->id, 'number' => $number],
            ['name' => 'Classe '.$number]
        )->id;
    }

    private function nextEntryNumber(Company $company): string
    {
        return app(DocumentSequenceService::class)->nextNumber($company, 'ecriture_comptable');
    }

    private function company(int $companyId): ?Company
    {
        return Company::find($companyId);
    }
}
