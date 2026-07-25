<?php

namespace App\Services;

use App\Events\CreditNoteValidated;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Warehouse;
use App\Repositories\CreditNoteRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    public function __construct(
        public readonly CreditNoteRepository $repository,
        private readonly DocumentSequenceService $sequenceService,
        private readonly StockService $stockService,
        private readonly AccountingService $accountingService,
    ) {}

    // -------------------------------------------------------------------------
    // Create from invoice (pre-fills lines from invoice items)
    // -------------------------------------------------------------------------
    public function createFromInvoice(Invoice $invoice, array $data): CreditNote
    {
        return DB::transaction(function () use ($invoice, $data) {
            $company = currentCompany();
            $items = $data['items'] ?? [];
            unset($data['items']);

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
                'is_replacement' => (bool) ($data['is_replacement'] ?? false),
                'currency_code' => $invoice->currency_code,
                'subtotal_ht' => $subtotal,
                'total_tax' => $taxTotal,
                'total_ttc' => $total,
                'remaining_credit' => $total,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($creditNote, $items);

            return $creditNote;
        });
    }

    // -------------------------------------------------------------------------
    // Validate
    // -------------------------------------------------------------------------
    public function validate(CreditNote $creditNote): CreditNote
    {
        return DB::transaction(function () use ($creditNote) {
            // [FIX-IDEM-03] Lock to prevent concurrent double-validation.
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            // [SEC-PHASE2 §2] Maker-checker : l'auteur de l'avoir ne le valide pas lui-même
            app(MakerCheckerService::class)->assert($creditNote->created_by, 'credit_note.validate', "l'avoir {$creditNote->number}", $creditNote);

            if ($creditNote->status !== 'brouillon') {
                throw new \RuntimeException('Seuls les avoirs en brouillon peuvent être validés.');
            }

            $creditNote->load('items.product');

            // Return physical goods to stock via retour_client (inbound)
            // Use the warehouse from the original delivery note if available,
            // otherwise fall back to the default warehouse.
            $warehouseId = $this->resolveReturnWarehouse($creditNote);

            if ($warehouseId) {
                foreach ($creditNote->items as $item) {
                    if (! $item->product_id || ! ($item->product?->is_stockable ?? true) || $item->quantity <= 0) {
                        continue;
                    }

                    // [VEN Retour] Ligne mise au rebut : les biens ne réintègrent PAS le
                    // stock vendable (ils sont déjà sortis à la vente d'origine et sont
                    // détruits). Seules les lignes 'restock' génèrent un retour_client.
                    if (($item->disposition ?? 'restock') === 'rebut') {
                        continue;
                    }
                    app(HistoricalCustomerReturnService::class)->returnItem($creditNote, $item, (int) $warehouseId);
                }
            }

            $creditNote->update([
                'status' => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $fresh = $creditNote->fresh(['client', 'company']);
            $this->applyValidationSideEffects($fresh);

            return $fresh;
        });
    }

    /**
     * Applique les effets secondaires de la validation d'un avoir :
     * - Comptabilisation au grand livre
     * - Retour de stock
     * - Événement CreditNoteValidated
     *
     * Méthode publique appelée aussi par CommercialWorkflowService::validateCreditNote()
     * pour le circuit interne (brouillon → en_attente_validation → valide).
     */
    public function applyValidationSideEffects(CreditNote $creditNote): void
    {
        // Post to GL synchronously — must be in the same transaction
        $this->accountingService->postCreditNote($creditNote);
        // [COMPTA-STOCK] Retour en stock automatique
        $this->accountingService->postCreditNoteStockMovement($creditNote);

        // Fire event — queued listeners handle secondary effects after commit
        event(new CreditNoteValidated($creditNote));
    }

    private function resolveReturnWarehouse(CreditNote $creditNote): ?int
    {
        // Try to get warehouse from the invoice's most recent delivery note
        if ($creditNote->invoice_id) {
            $warehouseId = DeliveryNote::where('order_id', function ($q) use ($creditNote) {
                $q->select('order_id')
                    ->from('invoices')
                    ->where('id', $creditNote->invoice_id)
                    ->whereNotNull('order_id');
            })->where('status', 'valide')
                ->orderByDesc('validated_at')
                ->value('warehouse_id');

            if ($warehouseId) {
                return $warehouseId;
            }
        }

        return Warehouse::where('is_default', true)->value('id')
            ?? Warehouse::value('id');
    }

    // -------------------------------------------------------------------------
    // Apply credit note to its linked invoice
    // -------------------------------------------------------------------------
    public function applyToInvoice(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->status !== 'valide') {
            throw new \RuntimeException("L'avoir doit être validé avant d'être appliqué.");
        }
        if (! $creditNote->invoice_id) {
            throw new \RuntimeException("Cet avoir n'est pas lié à une facture.");
        }
        if ($creditNote->remaining_credit <= 0) {
            throw new \RuntimeException('Le solde de cet avoir est déjà épuisé.');
        }

        return DB::transaction(function () use ($creditNote) {
            // [ARCH-C2] Lock both rows to prevent concurrent modifications.
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($creditNote->invoice_id);
            $apply = min($creditNote->remaining_credit, $invoice->remaining_amount);

            // Reduce invoice remaining
            $invoice->paid_amount += $apply;
            $invoice->remaining_amount -= $apply;
            if ($invoice->remaining_amount <= 0) {
                $invoice->status = 'payee';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partiellement_payee';
            }
            $invoice->save();

            // Reduce credit note remaining
            $creditNote->applied_amount += $apply;
            $creditNote->remaining_credit -= $apply;
            $creditNote->status = $creditNote->remaining_credit <= 0 ? 'applique' : 'valide';
            $creditNote->save();

            // Recalculate client balance now that a receivable has been reduced
            $invoice->client?->recalculateBalance();

            return $creditNote->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // [VEN Retour] Remplacement : génère un BL brouillon des biens à réexpédier
    // -------------------------------------------------------------------------
    /**
     * Crée un bon de livraison de remplacement (brouillon, sans commande) reprenant
     * les articles stockables de l'avoir. Le magasinier le valide ensuite par le
     * circuit BL normal, qui gère la sortie de stock et la traçabilité.
     *
     * Le BL de remplacement expédie de NOUVELLES unités saines : il concerne donc
     * toutes les lignes stockables, quelle que soit leur disposition (restock/rebut).
     */
    public function createReplacementDelivery(CreditNote $creditNote): DeliveryNote
    {
        if ($creditNote->status === 'brouillon') {
            throw new \RuntimeException("L'avoir doit être validé avant de générer un remplacement.");
        }
        if ($creditNote->replacement_delivery_id) {
            throw new \RuntimeException('Un bon de livraison de remplacement existe déjà pour cet avoir.');
        }

        return DB::transaction(function () use ($creditNote) {
            $creditNote->loadMissing('items.product');
            $company = currentCompany();
            $warehouseId = $this->resolveReturnWarehouse($creditNote);

            $dn = DeliveryNote::create([
                'company_id' => $company->id,
                'client_id' => $creditNote->client_id,
                'order_id' => null,
                'number' => $this->sequenceService->nextNumber($company, 'bon_livraison'),
                'issued_at' => now()->toDateString(),
                'status' => 'brouillon',
                'warehouse_id' => $warehouseId,
                'currency_code' => $creditNote->currency_code,
                'notes' => 'Remplacement — avoir '.$creditNote->number,
                'created_by' => Auth::id(),
            ]);

            $totalQty = 0;
            $sort = 0;
            foreach ($creditNote->items as $item) {
                if (! $item->product_id || ! ($item->product?->is_stockable ?? true) || $item->quantity <= 0) {
                    continue;
                }
                $dn->items()->create([
                    'order_item_id' => null,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sort_order' => $sort++,
                ]);
                $totalQty += (float) $item->quantity;
            }

            $dn->update(['total_quantity' => $totalQty]);

            $creditNote->update([
                'is_replacement' => true,
                'replacement_delivery_id' => $dn->id,
            ]);

            return $dn;
        });
    }

    // -------------------------------------------------------------------------
    // Delete (brouillon only)
    // -------------------------------------------------------------------------
    public function delete(CreditNote $creditNote): bool
    {
        if ($creditNote->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les avoirs en brouillon peuvent être supprimés.');
        }

        return (bool) $creditNote->delete();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private function syncItems(CreditNote $creditNote, array $items): void
    {
        $creditNote->items()->delete();
        foreach ($items as $i => $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = (int) round($qty * $price);
            $lineTax = (int) round($ht * ($tax / 100));

            $creditNote->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'disposition' => in_array($item['disposition'] ?? 'restock', ['restock', 'rebut', 'quarantaine', 'no_return'], true)
                    ? ($item['disposition'] ?? 'restock') : 'restock',
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
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $tax = (float) ($item['tax_rate_value'] ?? 0);
            $ht = (int) round($qty * $price);
            $subtotal += $ht;
            $taxTotal += (int) round($ht * ($tax / 100));
        }

        return [$subtotal, $taxTotal];
    }

    /**
     * [Matrice annulations] Annulation d'un avoir — inverse TOUS les effets de
     * la validation, jamais un simple changement de statut :
     *   - application à la facture défaite (paid/remaining/statut restaurés) ;
     *   - contre-mouvements de stock pour les lignes réintégrées (échec propre
     *     si la marchandise a déjà été revendue — stock insuffisant) ;
     *   - extourne des écritures comptables (liée à l'origine, période ouverte) ;
     *   - statut « annule » + motif + auteur, pas de suppression physique.
     *
     * @throws \RuntimeException
     */
    /**
     * [ULTIMATUM parcours D — remboursement réel] Rembourse en espèces/banque le
     * crédit restant d'un avoir validé : transaction de caisse (débit) +
     * écriture D 411 Clients / C 5xx (le client est désintéressé, sa créance
     * d'avoir s'éteint), statut 'rembourse', journalisé. Le remboursement est
     * exclusif de l'imputation (remaining_credit passe à 0).
     */
    public function refund(CreditNote $creditNote, int $cashAccountId, ?int $amount = null, ?string $reference = null): CreditNote
    {
        return DB::transaction(function () use ($creditNote, $cashAccountId, $amount, $reference) {
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            if (! in_array($creditNote->status, ['valide', 'rembourse'], true)) {
                throw new \RuntimeException('Seul un avoir validé peut être remboursé.');
            }

            // [R2 §2] Maker-checker : celui qui rembourse ≠ auteur de l'avoir
            // (sortie de trésorerie à effet irréversible — non exemptable en prod).
            app(MakerCheckerService::class)->assert(
                $creditNote->created_by, 'credit_note.refund', "le remboursement de l'avoir {$creditNote->number}", $creditNote
            );
            // [R2 §2] Conflit d'intérêt : l'opérateur ne rembourse pas un avoir dont
            // le client est lié à son propre compte utilisateur.
            app(MakerCheckerService::class)->assertNotBeneficiary(
                $creditNote->client?->user_id, "le remboursement de l'avoir {$creditNote->number}", $creditNote
            );

            $available = (int) $creditNote->remaining_credit;
            if ($available <= 0) {
                throw new \RuntimeException('Cet avoir n\'a plus de crédit restant à rembourser.');
            }
            // [R2 §2] Montant demandé (partiel possible) ; défaut = tout le solde.
            $amount = $amount === null ? $available : (int) $amount;
            if ($amount <= 0) {
                throw new \RuntimeException('Le montant à rembourser doit être positif.');
            }
            if ($amount > $available) {
                throw new \RuntimeException(sprintf(
                    'Montant demandé (%s) supérieur au crédit restant (%s FCFA).',
                    number_format($amount, 0, ',', ' '), number_format($available, 0, ',', ' ')
                ));
            }
            $cashAccount = CashAccount::lockForUpdate()->findOrFail($cashAccountId);

            // Sortie de trésorerie (la garde de solde du service caisse s'applique)
            app(CashAccountService::class)->recordTransaction($cashAccount, [
                'type' => 'debit',
                'reference_type' => 'CreditNoteRefund',
                'reference_id' => $creditNote->id,
                'amount' => $amount,
                'label' => 'Remboursement avoir '.$creditNote->number.' — '.($creditNote->client?->name ?? ''),
                'occurred_at' => now(),
            ]);

            // Écriture D 411 / C 5xx — référence unique par remboursement (partiels
            // successifs = plusieurs écritures) pour lever la garde d'idempotence.
            app(AccountingService::class)->postCreditNoteRefund($creditNote, $cashAccount, $amount, (int) $creditNote->refunded_amount);

            $newRefunded = (int) $creditNote->refunded_amount + $amount;
            $newRemaining = $available - $amount;
            $creditNote->update([
                'refunded_amount' => $newRefunded,
                'remaining_credit' => $newRemaining,
                // « rembourse » seulement quand le solde est épuisé ; sinon reste « valide »
                'status' => $newRemaining <= 0 ? 'rembourse' : 'valide',
            ]);

            // [R2 §2] INVARIANT dur : total = imputé + remboursé + disponible
            $cn = $creditNote->fresh();
            $sum = (int) $cn->applied_amount + (int) $cn->refunded_amount + (int) $cn->remaining_credit;
            if ($sum !== (int) $cn->total_ttc) {
                throw new \RuntimeException(sprintf(
                    'Invariant avoir rompu : %d imputé + %d remboursé + %d disponible = %d ≠ %d total.',
                    $cn->applied_amount, $cn->refunded_amount, $cn->remaining_credit, $sum, $cn->total_ttc
                ));
            }

            $creditNote->client?->recalculateBalance();

            app(AuditService::class)->log('avoir.remboursement', $creditNote, [], [
                'montant' => $amount, 'caisse' => $cashAccount->name, 'reference' => $reference,
                'remboursé_cumulé' => $newRefunded, 'restant' => $newRemaining,
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * [R2 §2 — annulation d'un remboursement] Défait TOUT le remboursement d'un
     * avoir (jamais un simple changement de statut) :
     *   - recrédite chaque caisse d'où l'argent était sorti (net par compte, pour
     *     rester correct après un cycle rembourse → annule → rembourse) ;
     *   - extourne chaque écriture REMB-* liée (période ouverte) ;
     *   - restaure le crédit disponible (remaining += refunded, refunded → 0) ;
     *   - INVARIANT dur maintenu : total = imputé + remboursé + disponible.
     * Le crédit redevient imputable/remboursable ; aucune suppression physique.
     *
     * @throws \RuntimeException
     */
    public function cancelRefund(CreditNote $creditNote, string $motif): CreditNote
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Motif d\'annulation du remboursement obligatoire.');
        }

        return DB::transaction(function () use ($creditNote, $motif) {
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            $refunded = (int) $creditNote->refunded_amount;
            if ($refunded <= 0) {
                throw new \RuntimeException('Aucun remboursement à annuler sur cet avoir.');
            }

            // 1. Recréditer chaque caisse : NET par compte = Σ débits remboursement
            //    − Σ crédits d'annulation déjà passés (gère les caisses multiples et
            //    les cycles successifs sans double recrédit).
            $movements = CashTransaction::whereIn('reference_type', ['CreditNoteRefund', 'CreditNoteRefundCancel'])
                ->where('reference_id', $creditNote->id)
                ->get()
                ->groupBy('cash_account_id');

            $restored = 0;
            foreach ($movements as $accountId => $txs) {
                $net = (int) $txs->sum(fn ($t) => $t->type === 'debit' ? (int) $t->amount : -(int) $t->amount);
                if ($net <= 0) {
                    continue;
                }
                $account = CashAccount::lockForUpdate()->findOrFail($accountId);
                app(CashAccountService::class)->recordTransaction($account, [
                    'type' => 'credit',
                    'reference_type' => 'CreditNoteRefundCancel',
                    'reference_id' => $creditNote->id,
                    'amount' => $net,
                    'label' => 'Annulation remboursement avoir '.$creditNote->number.' — '.$motif,
                    'occurred_at' => now(),
                ]);
                $restored += $net;
            }

            // 2. Extourne des écritures de remboursement (REMB-<numéro>[-cumul]).
            JournalEntry::where('company_id', $creditNote->company_id)
                ->where('reference', 'like', 'REMB-'.$creditNote->number.'%')
                ->where('status', 'valide')
                ->whereNull('reversed_by_entry_id')
                ->get()
                ->each(fn ($entry) => $this->accountingService->reverseEntry(
                    $entry,
                    'Annulation remboursement avoir '.$creditNote->number.' — '.$motif
                ));

            // 3. Restaurer le crédit disponible.
            $newRemaining = (int) $creditNote->remaining_credit + $refunded;
            $creditNote->update([
                'refunded_amount' => 0,
                'remaining_credit' => $newRemaining,
                'status' => (int) $creditNote->applied_amount >= (int) $creditNote->total_ttc ? 'applique' : 'valide',
            ]);

            // 4. INVARIANT dur : total = imputé + remboursé(0) + disponible.
            $cn = $creditNote->fresh();
            $sum = (int) $cn->applied_amount + (int) $cn->refunded_amount + (int) $cn->remaining_credit;
            if ($sum !== (int) $cn->total_ttc) {
                throw new \RuntimeException(sprintf(
                    'Invariant avoir rompu après annulation remboursement : %d + %d + %d = %d ≠ %d.',
                    $cn->applied_amount, $cn->refunded_amount, $cn->remaining_credit, $sum, $cn->total_ttc
                ));
            }

            $creditNote->client?->recalculateBalance();

            app(AuditService::class)->log('avoir.remboursement.annulation', $creditNote, [], [
                'montant_recredite' => $restored, 'motif' => $motif,
            ]);

            return $creditNote->fresh();
        });
    }

    public function cancel(CreditNote $creditNote, string $motif): CreditNote
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Motif d\'annulation obligatoire.');
        }

        return DB::transaction(function () use ($creditNote, $motif) {
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            if ($creditNote->status === 'annule') {
                throw new \RuntimeException('Cet avoir est déjà annulé.');
            }

            // [R2 §2] Un avoir déjà remboursé (argent sorti) ne peut être annulé
            // tel quel : le remboursement doit d'abord être annulé (recrédit caisse
            // + extourne), sinon la trésorerie serait faussée en silence.
            if ((int) $creditNote->refunded_amount > 0) {
                throw new \RuntimeException(sprintf(
                    'Cet avoir a été remboursé (%s FCFA) : annulez d\'abord le remboursement.',
                    number_format((int) $creditNote->refunded_amount, 0, ',', ' ')
                ));
            }

            if (in_array($creditNote->status, ['valide', 'applique'], true)) {
                // 1. Défaire l'application à la facture.
                $applied = (int) ($creditNote->applied_amount ?? 0);
                if ($applied > 0 && $creditNote->invoice_id) {
                    $invoice = Invoice::lockForUpdate()->findOrFail($creditNote->invoice_id);
                    $invoice->paid_amount = max(0, (int) $invoice->paid_amount - $applied);
                    $invoice->remaining_amount = min((int) $invoice->total_ttc, (int) $invoice->remaining_amount + $applied);
                    $invoice->status = $invoice->remaining_amount >= (int) $invoice->total_ttc
                        ? 'emise'
                        : ($invoice->paid_amount > 0 ? 'partiellement_payee' : 'emise');
                    $invoice->save();
                }

                // 2. Contre-mouvements de stock des lignes réintégrées.
                $creditNote->load('items.product');
                $warehouseId = $this->resolveReturnWarehouse($creditNote);
                if ($warehouseId) {
                    foreach ($creditNote->items as $item) {
                        if (! $item->product_id || ! ($item->product?->is_stockable ?? true)
                            || $item->quantity <= 0 || ($item->disposition ?? 'restock') === 'rebut') {
                            continue;
                        }
                        $this->stockService->recordMovement([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $warehouseId,
                            'type' => 'sortie',
                            'quantity' => (float) $item->quantity,
                            'unit_cost' => (float) $item->unit_price,
                            'occurred_at' => now(),
                            'reference_type' => 'credit_note',
                            'reference_id' => $creditNote->id,
                            'notes' => 'Annulation avoir '.$creditNote->number.' — '.$motif,
                            'idempotency_key' => 'credit-note-cancel:'.$creditNote->id.':'.$item->id,
                        ]);
                    }
                }

                // 3. Extourne des écritures comptables de l'avoir.
                JournalEntry::where('company_id', $creditNote->company_id)
                    ->where('reference', $creditNote->number)
                    ->where('status', 'valide')
                    ->whereNull('reversed_by_entry_id')
                    ->get()
                    ->each(fn ($entry) => $this->accountingService->reverseEntry(
                        $entry,
                        'Annulation avoir '.$creditNote->number.' — '.$motif
                    ));
            }

            // [SEC-PHASE2] Journal d'audit : annulation d'avoir tracée
            app(AuditService::class)->log('avoir.annulation', $creditNote, [], ['motif' => $motif, 'montant' => $creditNote->total_ttc]);

            $creditNote->update([
                'status' => 'annule',
                'remaining_credit' => 0,
                'applied_amount' => 0,
                'notes' => trim(($creditNote->notes ?? '')."\n\n".sprintf(
                    '[ANNULATION %s par %s] %s',
                    now()->format('d/m/Y H:i'),
                    Auth::user()?->name ?? 'système',
                    $motif
                )),
            ]);

            return $creditNote->fresh();
        });
    }
}
