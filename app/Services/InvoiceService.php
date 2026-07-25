<?php

namespace App\Services;

use App\Events\InvoiceValidated;
use App\Http\Traits\HasOptimisticLocking;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\FiscalTransmission;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTerm;
use App\Models\ProductStock;
use App\Repositories\InvoiceRepository;
use App\Services\Sync\Handlers\ReplayInvoiceAccountingSync;
use App\Services\Sync\SyncOrchestrator;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    use HasOptimisticLocking;

    public function __construct(
        public readonly InvoiceRepository $repository,
        private DocumentSequenceService $sequenceService,
        private AccountingService $accountingService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): Invoice
    {
        // [Parametrage Vente] client bloque = aucun document commercial
        ClientService::assertSellable(
            ! empty($data['client_id']) ? Client::find($data['client_id']) : null
        );

        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id'] = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number'] = $this->sequenceService->nextNumber($company, 'facture');
            $data['created_by'] = Auth::id();
            $data['status'] = $data['status'] ?? 'brouillon';

            // Auto-calculate due_at from PaymentTerm when not explicitly provided
            $this->resolveDueAt($data);

            // [TVA-EXEMPT] Défense serveur : si le client est exonéré de TVA,
            // forcer tous les taux à 0 indépendamment de ce que le formulaire envoie.
            $client = isset($data['client_id'])
                ? Client::with('taxRates')->find($data['client_id'])
                : null;
            if ($client?->isTaxExempt()) {
                $items = $this->zeroOutTax($items);
            }

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $discount = (int) ($data['global_discount_amount'] ?? 0);
            $total = $subtotal + $taxTotal - $discount;

            // Retenues à la source depuis les taxes du client
            [$withholdingDetails, $withholdingAmount] = $this->computeWithholding($client, $subtotal);
            $netToPay = max(0, $total - $withholdingAmount);

            $data['subtotal_ht'] = $subtotal;
            $data['total_discount'] = $discount;
            $data['total_tax'] = $taxTotal;
            $data['total_ttc'] = $total;
            $data['withholding_details'] = $withholdingDetails ?: null;
            $data['withholding_amount'] = $withholdingAmount;
            $data['net_to_pay'] = $netToPay;
            $data['remaining_amount'] = $netToPay;
            $data['global_discount_amount'] = $discount;

            $invoice = Invoice::create($data);
            $this->syncItems($invoice, $items);
            $this->recalculate($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Create an invoice directly from an order (all items, same amounts).
     */
    public function createFromOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            // [ARCH-S2-04] Lock the order row first to prevent concurrent requests
            // from creating duplicate invoices for the same order (TOCTOU race).
            $order = Order::lockForUpdate()->findOrFail($order->id);

            // [FIX-VENTES-01] Prevent double-billing the same order.
            if (Invoice::where('order_id', $order->id)->whereNotIn('status', ['annulee'])->exists()) {
                throw new \RuntimeException(
                    'Une facture non-annulée existe déjà pour la commande '.$order->number.'. Annulez-la avant d\'en créer une nouvelle.'
                );
            }

            $company = currentCompany();

            // [SEC-PHASE1] Échéance auto : payment_term du client si défini, sinon +30 jours.
            $issuedAt = now();
            $dueAt = $this->defaultDueAt($issuedAt, $order->client?->payment_term_id);

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $order->client_id,
                'fiscal_year_id' => $order->fiscal_year_id,
                'order_id' => $order->id,
                'number' => $this->sequenceService->nextNumber($company, 'facture'),
                'type' => 'standard',
                'status' => 'brouillon',
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $dueAt,
                'subtotal_ht' => $order->subtotal_ht,
                'total_discount' => $order->total_discount,
                'total_tax' => $order->total_tax,
                'total_ttc' => $order->total_ttc,
                'remaining_amount' => $order->total_ttc,
                'global_discount_amount' => $order->global_discount_amount,
                'global_discount_percent' => $order->global_discount_percent,
                'notes' => $order->notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'nb_toles' => $item->nb_toles,
                    'metrage_par_tole' => $item->metrage_par_tole,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate_id' => $item->tax_rate_id,
                    'tax_rate_value' => $item->tax_rate_value,
                    'line_total_ht' => $item->line_total_ht,
                    'line_tax' => $item->line_tax,
                    'line_total_ttc' => $item->line_total_ttc,
                    'sort_order' => $item->sort_order,
                ]);
                // Track invoiced quantity for partial invoicing
                $item->increment('invoiced_quantity', (float) $item->quantity);
            }

            // Mark the order as invoiced
            $order->update(['status' => 'facture']);

            // [COMPTA-RETENUE] Calcul automatique de la retenue à la source
            $this->recalculate($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Create an invoice from a validated delivery note.
     * Items taken from BL, amounts recalculated from order items prices.
     */
    public function createFromDeliveryNote(DeliveryNote $dn): Invoice
    {
        return DB::transaction(function () use ($dn) {
            // [SYNC-FIX-01] Lock the delivery note row to prevent concurrent invoicing
            // of the same BL — and guard against duplicate-billing.
            $dn = DeliveryNote::lockForUpdate()->findOrFail($dn->id);
            $dn->load('items', 'order');

            // [SYNC-FIX-01] Guard : un BL ne peut être facturé qu'une seule fois (hors annulation).
            $existingInvoice = Invoice::where('delivery_note_id', $dn->id)
                ->whereNotIn('status', ['annulee'])
                ->whereNull('deleted_at')
                ->first();
            if ($existingInvoice) {
                throw new \RuntimeException(sprintf(
                    'Le bon de livraison %s a déjà été facturé (facture %s, statut %s). '
                    .'Annulez la facture existante avant d\'en créer une nouvelle.',
                    $dn->number, $existingInvoice->number, $existingInvoice->status
                ));
            }

            // [SYNC-FIX-01] Guard supplémentaire : si une facture standard existe déjà pour
            // l'ordre entier (sans delivery_note_id), refuser pour éviter le double-facturation.
            if ($dn->order_id) {
                $orderInvoice = Invoice::where('order_id', $dn->order_id)
                    ->whereNull('delivery_note_id')
                    ->whereNotIn('status', ['annulee'])
                    ->whereNull('deleted_at')
                    ->first();
                if ($orderInvoice) {
                    throw new \RuntimeException(sprintf(
                        'La commande %s a déjà été facturée intégralement (facture %s). '
                        .'Impossible de générer une seconde facture via le BL %s.',
                        $dn->order?->number, $orderInvoice->number, $dn->number
                    ));
                }
            }

            $company = currentCompany();

            // Calculate totals from BL items using order item prices
            $subtotal = 0;
            $taxTotal = 0;
            $itemsData = [];

            foreach ($dn->items as $i => $item) {
                // [FIX-BUG-01] Initialize $orderItem to null so it's always defined
                // when referenced after the conditional block (avoids "Undefined variable"
                // warning on items without order_item_id, plus prevents leakage between
                // iterations of this loop).
                $orderItem = null;
                $qty = (float) $item->quantity;
                $price = (float) $item->unit_price;
                $disc = 0.0;
                $tax = 0.0;

                // Fetch tax rate from linked order item
                if ($item->order_item_id) {
                    $orderItem = OrderItem::find($item->order_item_id);
                    if ($orderItem) {
                        $disc = (float) $orderItem->discount_percent;
                        $tax = (float) $orderItem->tax_rate_value;
                    }
                }

                $ht = (int) round($qty * $price * (1 - $disc / 100));
                $lineTax = (int) round($ht * $tax / 100);
                $ttc = $ht + $lineTax;

                $subtotal += $ht;
                $taxTotal += $lineTax;

                $itemsData[] = [
                    'delivery_note_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'unit_id' => $item->unit_id,
                    'quantity' => $qty,
                    // [§5 TÔLE BAC] hérite nb tôles / longueur de la ligne commande source
                    'nb_toles' => $orderItem?->nb_toles ?? $item->nb_toles,
                    'metrage_par_tole' => $orderItem?->metrage_par_tole ?? $item->metrage_par_tole,
                    'unit_price' => (int) $price,
                    'discount_percent' => $disc,
                    'tax_rate_id' => $orderItem?->tax_rate_id ?? null,
                    'tax_rate_value' => $tax,
                    'line_total_ht' => $ht,
                    'line_tax' => $lineTax,
                    'line_total_ttc' => $ttc,
                    'sort_order' => $i,
                ];
            }

            // [FIX-MAJEUR] Propagate global discount from the parent order
            $globalDiscount = (int) ($dn->order?->global_discount_amount ?? 0);
            $globalDiscountPercent = (float) ($dn->order?->global_discount_percent ?? 0);
            $total = max(0, ($subtotal + $taxTotal) - $globalDiscount);

            // [SEC-PHASE1] Échéance auto : payment_term du client si défini, sinon +30 jours.
            $issuedAt = now();
            $client = Client::find($dn->client_id);
            $dueAt = $this->defaultDueAt($issuedAt, $client?->payment_term_id);

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $dn->client_id,
                'fiscal_year_id' => $company->current_fiscal_year_id,
                'order_id' => $dn->order_id,
                'delivery_note_id' => $dn->id,
                'number' => $this->sequenceService->nextNumber($company, 'facture'),
                'type' => 'standard',
                'status' => 'brouillon',
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $dueAt,
                'subtotal_ht' => $subtotal,
                'total_discount' => $globalDiscount,
                'global_discount_amount' => $globalDiscount,
                'global_discount_percent' => $globalDiscountPercent,
                'total_tax' => $taxTotal,
                'total_ttc' => $total,
                'remaining_amount' => $total,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // [FIX-CRITIQUE] Increment invoiced_quantity on linked order items
            foreach ($dn->items as $dnItem) {
                if ($dnItem->order_item_id) {
                    OrderItem::where('id', $dnItem->order_item_id)
                        ->increment('invoiced_quantity', (float) $dnItem->quantity);
                }
            }

            // Mark order as invoiced if it has been fully delivered
            if ($dn->order && $dn->order->status === 'livre') {
                $dn->order->update(['status' => 'facture']);
            }

            // [COMPTA-RETENUE] Calcul automatique de la retenue à la source
            $this->recalculate($invoice);

            return $invoice->fresh();
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            // [FISCAL] Un document fiscalement transmis (accepté) est IMMUABLE.
            if (FiscalTransmission::isDocumentLocked($invoice)) {
                throw new \RuntimeException("La facture {$invoice->number} a été transmise à l'administration fiscale — toute modification est interdite. Émettez un avoir.");
            }

            // [CONCURRENCE] Verrou optimiste : détecte les modifications concurrentes
            $this->assertVersion($invoice, $data['_lock_version'] ?? null);
            unset($data['_lock_version'], $data['_idempotency_key']);

            // [INVOICE-LOCKED-GUARD] Verrou : une facture qui n'est plus en brouillon
            // ne peut PAS être modifiée. Ceci couvre payée, émise, partiellement_payée,
            // en_retard, annulée. La modification rétroactive est interdite en compta —
            // utilisez un avoir pour annuler/corriger.
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== 'brouillon') {
                throw new \RuntimeException(sprintf(
                    'La facture %s est « %s » — la modification est interdite par la réglementation comptable. '
                    .'Pour corriger une erreur sur une facture déjà émise, créez un avoir client et émettez une nouvelle facture.',
                    $invoice->number,
                    $invoice->status
                ));
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            // [TVA-EXEMPT] Défense serveur sur update : vérifier le client courant
            // (peut changer si l'utilisateur modifie le champ client en édition).
            $clientId = $data['client_id'] ?? $invoice->client_id;
            $client = Client::find($clientId);
            if ($client?->isTaxExempt() && $items !== null) {
                $items = $this->zeroOutTax($items);
            }

            $invoice->update($data);

            if ($items !== null) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $items);
            }

            $this->recalculate($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Validate the invoice: status -> emise, record validated_at.
     */
    public function validate(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            // [FIX-IDEM-02] Lock the row to prevent concurrent double-validation
            // (two simultaneous requests would both see status='brouillon' without the lock).
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== 'brouillon') {
                throw new \RuntimeException('Seules les factures en brouillon peuvent être validées.');
            }

            // [BUG-FIX] Garantie : une facture validée a TOUJOURS une échéance.
            // Sinon le cron invoices:mark-overdue et le module Relances l'ignorent.
            $updates = [
                'status' => 'emise',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ];
            if (! $invoice->due_at) {
                $issuedAt = $invoice->issued_at ?? now();
                $updates['due_at'] = $this->defaultDueAt(
                    $issuedAt instanceof Carbon ? $issuedAt : Carbon::parse($issuedAt),
                    $invoice->payment_term_id
                );
            }

            $invoice->update($updates);

            $fresh = $invoice->fresh(['client', 'company']);
            $this->applyValidationSideEffects($fresh);

            // [SEC-PHASE2 §8] Journal (succès = après commit)
            DB::afterCommit(fn () => app(AuditService::class)->log('facture.validation', $fresh, [], ['montant' => $fresh->total_ttc]));

            return $fresh;
        });
    }

    /**
     * Applique les effets secondaires de la validation d'une facture :
     * - Comptabilisation au grand livre (GL)
     * - Sortie de stock du coût des ventes
     * - Événement InvoiceValidated
     *
     * Méthode publique appelée aussi par CommercialWorkflowService::validateInvoice()
     * pour le circuit interne (brouillon → en_attente_validation → emise).
     * Les proformas sont ignorées (pas d'impact comptable/stock).
     */
    public function applyValidationSideEffects(Invoice $invoice): void
    {
        // [MED-1] PROFORMA = document commercial sans impact réel.
        // Ni comptabilité, ni stock, ni balance client.
        // Doit être "convertie" en facture standard via convertProforma() pour générer les impacts.
        if ($invoice->type === 'proforma') {
            return;
        }

        // [MARGES-CMP] Figer le coût unitaire (CMP) de chaque ligne au moment de la
        // validation → marges historiques exactes, indépendantes du CMP futur.
        $this->snapshotItemCosts($invoice);

        // Post to GL synchronously — must be in the same transaction.
        // [Sync ERP] journalisée + idempotente (jamais deux écritures pour la même
        // facture) + relançable via sync_logs.
        app(SyncOrchestrator::class)->run(
            sourceModule: 'ventes',
            targetModule: 'comptabilite',
            eventName: 'invoice.validated',
            action: 'post_gl_entry',
            source: $invoice,
            callback: fn () => $this->accountingService->postClientInvoice($invoice),
            handlerClass: ReplayInvoiceAccountingSync::class,
        );
        // [COMPTA-STOCK] Sortie de stock automatique
        $this->accountingService->postSaleStockMovement($invoice);

        // [SYNC] Maintenir le montant facturé de la commande source en temps
        // réel (sinon il n'était rattrapé que par sync:modules quotidien).
        Order::resyncInvoicedAmount($invoice->order_id);

        // Fire event — queued listener sends email after commit
        event(new InvoiceValidated($invoice));
    }

    /**
     * [MARGES-CMP] Fige le coût unitaire de chaque ligne au CMP courant du produit
     * (repli prix d'achat). Appelé une seule fois à la validation : le coût ne
     * doit jamais être recalculé ensuite (marge historique stable).
     */
    private function snapshotItemCosts(Invoice $invoice): void
    {
        $invoice->loadMissing('items.product', 'items.deliveryNoteItem.lotAllocations');

        foreach ($invoice->items as $item) {
            // Ne pas réécraser un snapshot déjà figé (idempotence).
            if ((float) $item->unit_cost > 0) {
                continue;
            }
            $product = $item->product;
            if (! $product) {
                continue;
            }
            $allocations = $item->deliveryNoteItem?->lotAllocations?->whereNull('reversed_at');
            if ($allocations && $allocations->isNotEmpty()) {
                $quantity = (float) $allocations->sum('quantity');
                $cost = $quantity > 0 ? (float) $allocations->sum('total_cost') / $quantity : 0;
                if ($cost > 0) {
                    $item->updateQuietly(['unit_cost' => round($cost, 2)]);

                    continue;
                }
            }
            // [FIX VALORISATION] Le coût des ventes se fige au CMP RÉEL du stock
            // (product_stocks.avg_cost, moyenne pondérée par les quantités) — la
            // colonne products.weighted_avg_cost n'est maintenue nulle part (0)
            // et le repli direct sur le prix d'achat CATALOGUE faussait la marge
            // comptable dès que le catalogue divergeait du CMP (constaté :
            // sortie valorisée 593 136 au lieu de 48 000 au CMP).
            // Même cascade que StockInsightsService (valorisation des stocks).
            $cost = (float) (ProductStock::where('product_id', $product->id)
                ->where('quantity', '>', 0)
                ->selectRaw('SUM(quantity * COALESCE(avg_cost, 0)) / NULLIF(SUM(quantity), 0) AS cmp')
                ->value('cmp') ?? 0);
            if ($cost <= 0) {
                $cost = (float) ($product->weighted_avg_cost ?: 0);
            }
            if ($cost <= 0) {
                $cost = (float) ($product->purchase_price ?? 0);
            }
            if ($cost > 0) {
                $item->updateQuietly(['unit_cost' => round($cost, 2)]);
            }
        }
    }

    /**
     * Mark an invoice as fully paid (admin override).
     *
     * Requires that the sum of existing payment allocations covers the full
     * amount — use this only when reconciling a payment that was already
     * recorded outside the normal ClientPayment workflow.
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($invoice->status, ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])) {
                throw new \RuntimeException('Seules les factures en cours de paiement peuvent être marquées comme payées.');
            }

            // Guard: ensure allocations actually cover the invoice total.
            $allocated = (int) $invoice->allocations()->sum('amount');
            if ($allocated < (int) $invoice->total_ttc) {
                throw new \RuntimeException(
                    "Les règlements enregistrés ({$allocated}) ne couvrent pas le total de la facture ({$invoice->total_ttc})."
                );
            }

            $invoice->update([
                'status' => 'payee',
                'paid_amount' => $invoice->total_ttc,
                'remaining_amount' => 0,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Create a credit note (avoir) against an invoice.
     */
    public function createCreditNote(Invoice $invoice, array $data): CreditNote
    {
        return DB::transaction(function () use ($invoice, $data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $total = $subtotal + $taxTotal;

            $creditNote = CreditNote::create([
                'company_id' => $company->id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'number' => $this->sequenceService->nextNumber($company, 'avoir'),
                'status' => 'brouillon',
                'issued_at' => $data['issued_at'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'currency_code' => $invoice->currency_code,
                'subtotal_ht' => $subtotal,
                'total_tax' => $taxTotal,
                'total_ttc' => $total,
                'remaining_credit' => $total,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $i => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $tax = (float) ($item['tax_rate_value'] ?? 0);
                $ht = (int) round($qty * $price);
                $lineTax = (int) round($ht * ($tax / 100));

                $creditNote->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => (int) $price,
                    'tax_rate_id' => $item['tax_rate_id'] ?? null,
                    'tax_rate_value' => $tax,
                    'line_total_ht' => $ht,
                    'line_tax' => $lineTax,
                    'line_total_ttc' => $ht + $lineTax,
                    'sort_order' => $i,
                ]);
            }

            return $creditNote;
        });
    }

    /**
     * Return a view path string that can be used to render / stream a PDF.
     * The actual Blade view and DomPDF/Browsershot wiring is done separately.
     */
    public function generatePdfPath(Invoice $invoice): string
    {
        return 'ventes.pdf.invoice';
    }

    /**
     * [FIX-CRITIQUE] Cancel a validated invoice: reverse the GL entry (contrepassation),
     * restore remaining_amount to full total, reset paid_amount to 0.
     * Only invoices with no payments can be cancelled.
     *
     * [MED-5] Le motif est OBLIGATOIRE et stocké dans `notes` pour audit comptable.
     */
    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        if (! in_array($invoice->status, ['emise', 'envoyee', 'en_retard'])) {
            throw new \RuntimeException('Seules les factures émises sans paiement peuvent être annulées.');
        }
        if ($invoice->paid_amount > 0) {
            throw new \RuntimeException('Impossible d\'annuler une facture avec des paiements enregistrés. Créez un avoir.');
        }
        // [MED-5] Motif obligatoire — empêche l'annulation sans justification audit.
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new \RuntimeException('Le motif de l\'annulation est obligatoire pour la traçabilité comptable.');
        }

        return DB::transaction(function () use ($invoice, $reason) {
            // Find and reverse the GL entry linked to this invoice
            $glEntry = JournalEntry::where('reference', $invoice->number)
                ->where('company_id', $invoice->company_id)
                ->where('status', 'valide')
                ->first();

            if ($glEntry) {
                $this->accountingService->reverseEntry(
                    $glEntry,
                    'Annulation facture '.$invoice->number.' — '.$reason
                );
            }

            // Le motif est concaténé à `notes` (préserve l'existant), avec timestamp + user
            $cancelTrace = sprintf(
                '[ANNULATION %s par %s] %s',
                now()->format('d/m/Y H:i'),
                Auth::user()?->name ?? 'système',
                $reason
            );
            $newNotes = $invoice->notes
                ? rtrim((string) $invoice->notes)."\n\n".$cancelTrace
                : $cancelTrace;

            $invoice->update([
                'status' => 'annulee',
                'remaining_amount' => 0,
                'notes' => $newNotes,
            ]);

            // [SEC-PHASE2] Journal d'audit : annulation de facture tracée
            app(AuditService::class)->log('facture.annulation', $invoice, [], ['motif' => $reason, 'montant' => $invoice->total_ttc]);

            // [FIX-VENTES-03] Decrement invoiced_quantity on each linked order item so
            // the order can be re-invoiced after this cancellation.
            if ($invoice->order_id) {
                $invoice->load('items');
                foreach ($invoice->items as $invItem) {
                    if ($invItem->product_id) {
                        OrderItem::where('order_id', $invoice->order_id)
                            ->where('product_id', $invItem->product_id)
                            ->decrement('invoiced_quantity', (float) $invItem->quantity);
                    }
                }
            }

            // Revert the parent order's status when the invoice it generated is cancelled.
            // If the order was fully delivered → livre, else partiellement_livre.
            if ($invoice->order_id) {
                $order = $invoice->order()->with('items', 'deliveryNotes')->first();
                if ($order && $order->status === 'facture') {
                    $allDelivered = $order->items->every(
                        fn ($item) => (float) $item->delivered_quantity >= (float) $item->quantity
                    );
                    $hasValidatedBl = $order->deliveryNotes->where('status', 'valide')->isNotEmpty();
                    if ($hasValidatedBl) {
                        $order->update(['status' => $allDelivered ? 'livre' : 'partiellement_livre']);
                    } else {
                        $order->update(['status' => 'confirme']);
                    }
                }
            }

            // Recalculate the client's outstanding balance now that the invoice is cancelled.
            $fresh = $invoice->fresh(['client']);
            $fresh->client?->recalculateBalance();

            return $fresh;
        });
    }

    public function delete(Invoice $invoice): bool
    {
        // [POST-VALID-01] Une facture annulée a un numéro définitif et des écritures
        // (origine + extourne) : sa suppression casserait la séquence de numérotation
        // et laisserait des écritures référençant un document disparu. Elle reste
        // consultable ; seul le brouillon (jamais numéroté ni comptabilisé) se supprime.
        if ($invoice->status !== 'brouillon') {
            throw new \RuntimeException('Seules les factures en brouillon peuvent être supprimées. Une facture émise s\'annule (elle reste consultable), elle ne se supprime pas.');
        }

        return DB::transaction(function () use ($invoice) {
            // [FIX-VENTES-05] When a brouillon invoice created from an order is deleted,
            // restore the order's status and undo invoiced_quantity increments so that
            // the order can be re-invoiced.
            if ($invoice->status === 'brouillon' && $invoice->order_id) {
                $order = $invoice->order()->with('items', 'deliveryNotes')->first();
                if ($order && $order->status === 'facture') {
                    // Undo invoiced_quantity for each matched order item
                    $invoice->load('items');
                    foreach ($invoice->items as $invItem) {
                        if ($invItem->product_id) {
                            OrderItem::where('order_id', $order->id)
                                ->where('product_id', $invItem->product_id)
                                ->decrement('invoiced_quantity', (float) $invItem->quantity);
                        }
                    }

                    // Restore order to its pre-invoicing status
                    $allDelivered = $order->items->every(
                        fn ($i) => (float) $i->delivered_quantity >= (float) $i->quantity
                    );
                    $hasValidatedBl = $order->deliveryNotes->where('status', 'valide')->isNotEmpty();

                    $order->update([
                        'status' => $hasValidatedBl
                            ? ($allDelivered ? 'livre' : 'partiellement_livre')
                            : 'confirme',
                    ]);
                }
            }

            return (bool) $invoice->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Résout la date d'échéance d'une facture, dans cet ordre :
     *   1. Si `due_at` explicite → on garde tel quel
     *   2. Sinon si `payment_term_id` → calcul via l'algorithme PaymentTerm
     *   3. Sinon → fallback à `issued_at + 30 jours` (défaut commercial standard)
     *
     * [SEC-PHASE1] Garantit qu'aucune facture ne reste sans échéance — préalable
     * indispensable au cron `invoices:mark-overdue` et au module Relances.
     */
    private function resolveDueAt(array &$data): void
    {
        if (! empty($data['due_at'])) {
            return; // explicit due date wins
        }

        $issuedAt = isset($data['issued_at'])
            ? Carbon::parse($data['issued_at'])
            : now();

        $data['due_at'] = $this->defaultDueAt($issuedAt, $data['payment_term_id'] ?? null);
    }

    /**
     * Calcule la due_at par défaut depuis une date d'émission et un payment_term_id éventuel.
     * Utilisé par create(), createFromOrder() et createFromDeliveryNote() pour garantir
     * qu'aucune facture ne soit jamais émise sans échéance.
     */
    private function defaultDueAt(Carbon $issuedAt, ?int $paymentTermId = null): string
    {
        if ($paymentTermId && ($term = PaymentTerm::find($paymentTermId))) {
            return $term->calculateDueDate($issuedAt)->toDateString();
        }

        return $issuedAt->copy()->addDays(30)->toDateString();
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_percent'] ?? 0);
            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = (int) round($qty * $price * (1 - $disc / 100));
            $lineTax = (int) round($ht * ($tax / 100));
            $ttc = $ht + $lineTax;

            $invoice->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'] ?? '',
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => $qty,
                'unit_price' => (int) $price,
                'discount_percent' => $disc,
                'tax_rate_id' => $item['tax_rate_id'] ?? null,
                'tax_rate_value' => $tax,
                'line_total_ht' => $ht,
                'line_tax' => $lineTax,
                'line_total_ttc' => $ttc,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * [TVA-EXEMPT] Défense serveur : met tous les taux TVA à 0 sur un tableau d'items.
     * Appelé quand client.is_tax_exempt = true pour garantir que, même si le formulaire
     * envoie un tax_rate_value non nul, aucune TVA ne sera stockée en base.
     */
    private function zeroOutTax(array $items): array
    {
        return array_map(function (array $item) {
            $item['tax_rate_value'] = 0;
            $item['tax_rate_id'] = null;
            $item['line_tax'] = 0;

            return $item;
        }, $items);
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_percent'] ?? 0);
            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = $qty * $price * (1 - $disc / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    public function recalculate(Invoice $invoice): void
    {
        $invoice->load('items', 'client.taxRates');

        $subtotal = (int) $invoice->items->sum('line_total_ht');
        $taxTotal = (int) $invoice->items->sum('line_tax');
        $discount = (int) ($invoice->global_discount_amount ?? 0);
        $total = $subtotal + $taxTotal - $discount;

        // Calcul des retenues à la source (type = 'retenue') du client
        [$withholdingDetails, $withholdingAmount] = $this->computeWithholding(
            $invoice->client,
            $subtotal
        );

        $netToPay = max(0, $total - $withholdingAmount);

        $invoice->update([
            'subtotal_ht' => $subtotal,
            'total_tax' => $taxTotal,
            'total_ttc' => $total,
            'withholding_details' => $withholdingDetails ?: null,
            'withholding_amount' => $withholdingAmount,
            'net_to_pay' => $netToPay,
            'remaining_amount' => max(0, $netToPay - (int) ($invoice->paid_amount ?? 0)),
        ]);
    }

    /**
     * Calcule les retenues à la source applicables à un client.
     * Retourne [details[], totalAmount].
     */
    private function computeWithholding(?Client $client, int $subtotalHt): array
    {
        if (! $client) {
            return [[], 0];
        }

        $retenues = $client->taxRates->where('type', 'retenue')->values();
        if ($retenues->isEmpty()) {
            return [[], 0];
        }

        $details = [];
        $total = 0;

        foreach ($retenues as $tax) {
            $amount = (int) round($subtotalHt * (float) $tax->rate / 100);
            $details[] = [
                'name' => $tax->name,
                'short_name' => $tax->short_name,
                'rate' => (float) $tax->rate,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [$details, $total];
    }

    // =========================================================================
    // [BUG-1] convertProforma — Conversion proforma → facture standard
    // =========================================================================

    /**
     * Convertit une facture proforma en facture standard définitive.
     *
     * Flow:
     *   1. Vérifie que la facture est bien de type 'proforma' et dans un état modifiable.
     *   2. Crée une nouvelle facture standard (type 'standard', status 'emise') avec
     *      les mêmes items/montants/client.
     *   3. Déclenche les effets de validation (GL + stock + événement InvoiceValidated).
     *   4. Passe la proforma originelle à 'annulee' avec lien parent_invoice_id.
     *
     * La proforma reste dans l'historique pour audit mais n'a aucun impact comptable.
     */
    public function convertProforma(Invoice $proforma): Invoice
    {
        if ($proforma->type !== 'proforma') {
            throw new \RuntimeException('Seules les factures de type « proforma » peuvent être converties.');
        }

        if (! in_array($proforma->status, ['brouillon', 'emise', 'envoyee'])) {
            throw new \RuntimeException(sprintf(
                'La proforma %s (statut : %s) ne peut plus être convertie.',
                $proforma->number,
                $proforma->status
            ));
        }

        return DB::transaction(function () use ($proforma) {
            // Lock the proforma row to prevent concurrent conversion.
            $proforma = Invoice::lockForUpdate()->findOrFail($proforma->id);

            if ($proforma->type !== 'proforma' || ! in_array($proforma->status, ['brouillon', 'emise', 'envoyee'])) {
                throw new \RuntimeException('La proforma ne peut plus être convertie (état modifié simultanément).');
            }

            $proforma->load('items', 'client.taxRates');
            $company = Company::findOrFail($proforma->company_id);

            // ── 1. Créer la nouvelle facture standard ─────────────────────────
            $newInvoice = Invoice::create([
                'company_id' => $proforma->company_id,
                'client_id' => $proforma->client_id,
                'fiscal_year_id' => $proforma->fiscal_year_id ?? $company->current_fiscal_year_id,
                'order_id' => $proforma->order_id,
                'delivery_note_id' => $proforma->delivery_note_id,
                'number' => $this->sequenceService->nextNumber($company, 'facture'),
                'type' => 'standard',
                'status' => 'brouillon',
                'issued_at' => now()->toDateString(),
                'due_at' => $this->defaultDueAt(now(), $proforma->client?->payment_term_id),
                'subtotal_ht' => $proforma->subtotal_ht,
                'total_discount' => $proforma->total_discount,
                'global_discount_amount' => $proforma->global_discount_amount,
                'global_discount_percent' => $proforma->global_discount_percent,
                'total_tax' => $proforma->total_tax,
                'total_ttc' => $proforma->total_ttc,
                'withholding_details' => $proforma->withholding_details,
                'withholding_amount' => $proforma->withholding_amount,
                'net_to_pay' => $proforma->net_to_pay,
                'remaining_amount' => $proforma->net_to_pay ?? $proforma->total_ttc,
                'notes' => $proforma->notes,
                'payment_method' => $proforma->payment_method,
                'parent_invoice_id' => null,
                'created_by' => Auth::id(),
            ]);

            // Copier les lignes de la proforma
            foreach ($proforma->items as $i => $item) {
                $newInvoice->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate_id' => $item->tax_rate_id,
                    'tax_rate_value' => $item->tax_rate_value,
                    'line_total_ht' => $item->line_total_ht,
                    'line_tax' => $item->line_tax,
                    'line_total_ttc' => $item->line_total_ttc,
                    'sort_order' => $i,
                ]);
            }

            // ── 2. Valider immédiatement la nouvelle facture (émission + GL + stock) ──
            // La validation passe en 'emise' et déclenche postClientInvoice + postSaleStockMovement.
            $newInvoice->update(['status' => 'emise', 'validated_at' => now(), 'validated_by' => Auth::id()]);
            $newInvoice = $newInvoice->fresh(['items.product.family.saleAccount', 'items.taxRate.collectedAccount', 'client']);
            $this->applyValidationSideEffects($newInvoice);

            // ── 3. Annuler la proforma originelle ─────────────────────────────
            $proforma->update([
                'status' => 'annulee',
                'parent_invoice_id' => $newInvoice->id,
                'notes' => rtrim((string) $proforma->notes)."\n\n"
                    .sprintf(
                        '[CONVERTI le %s par %s → Facture %s]',
                        now()->format('d/m/Y H:i'),
                        Auth::user()?->name ?? 'système',
                        $newInvoice->number
                    ),
            ]);

            return $newInvoice->fresh();
        });
    }
}
