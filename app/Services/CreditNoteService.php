<?php

namespace App\Services;

use App\Events\CreditNoteValidated;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
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
            $items   = $data['items'] ?? [];
            unset($data['items']);

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $total = $subtotal + $taxTotal;

            $creditNote = CreditNote::create([
                'company_id'       => $company->id,
                'client_id'        => $invoice->client_id,
                'invoice_id'       => $invoice->id,
                'number'           => $this->sequenceService->nextNumber($company, 'avoir'),
                'status'           => 'brouillon',
                'issued_at'        => $data['issued_at'] ?? now()->toDateString(),
                'reason'           => $data['reason'] ?? null,
                'is_replacement'   => (bool) ($data['is_replacement'] ?? false),
                'currency_code'    => $invoice->currency_code,
                'subtotal_ht'      => $subtotal,
                'total_tax'        => $taxTotal,
                'total_ttc'        => $total,
                'remaining_credit' => $total,
                'notes'            => $data['notes'] ?? null,
                'created_by'       => Auth::id(),
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
                    if (!$item->product_id || !($item->product?->is_stockable ?? true) || $item->quantity <= 0) {
                        continue;
                    }

                    // [VEN Retour] Ligne mise au rebut : les biens ne réintègrent PAS le
                    // stock vendable (ils sont déjà sortis à la vente d'origine et sont
                    // détruits). Seules les lignes 'restock' génèrent un retour_client.
                    if (($item->disposition ?? 'restock') === 'rebut') {
                        continue;
                    }

                    $this->stockService->recordMovement([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $warehouseId,
                        'type'           => 'retour_client',
                        'quantity'       => (float) $item->quantity,
                        'unit_cost'      => (float) $item->unit_price,
                        'occurred_at'    => now(),
                        'reference_type' => 'credit_note',
                        'reference_id'   => $creditNote->id,
                        'notes'          => 'Avoir ' . $creditNote->number,
                    ]);
                }
            }

            $creditNote->update([
                'status'       => 'valide',
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
            $warehouseId = \App\Models\DeliveryNote::where('order_id', function ($q) use ($creditNote) {
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
        if (!$creditNote->invoice_id) {
            throw new \RuntimeException("Cet avoir n'est pas lié à une facture.");
        }
        if ($creditNote->remaining_credit <= 0) {
            throw new \RuntimeException('Le solde de cet avoir est déjà épuisé.');
        }

        return DB::transaction(function () use ($creditNote) {
            // [ARCH-C2] Lock both rows to prevent concurrent modifications.
            $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);
            $invoice    = Invoice::lockForUpdate()->findOrFail($creditNote->invoice_id);
            $apply      = min($creditNote->remaining_credit, $invoice->remaining_amount);

            // Reduce invoice remaining
            $invoice->paid_amount      += $apply;
            $invoice->remaining_amount -= $apply;
            if ($invoice->remaining_amount <= 0) {
                $invoice->status = 'payee';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partiellement_payee';
            }
            $invoice->save();

            // Reduce credit note remaining
            $creditNote->applied_amount   += $apply;
            $creditNote->remaining_credit -= $apply;
            $creditNote->status            = $creditNote->remaining_credit <= 0 ? 'applique' : 'valide';
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
    public function createReplacementDelivery(CreditNote $creditNote): \App\Models\DeliveryNote
    {
        if ($creditNote->status === 'brouillon') {
            throw new \RuntimeException("L'avoir doit être validé avant de générer un remplacement.");
        }
        if ($creditNote->replacement_delivery_id) {
            throw new \RuntimeException('Un bon de livraison de remplacement existe déjà pour cet avoir.');
        }

        return DB::transaction(function () use ($creditNote) {
            $creditNote->loadMissing('items.product');
            $company     = currentCompany();
            $warehouseId = $this->resolveReturnWarehouse($creditNote);

            $dn = \App\Models\DeliveryNote::create([
                'company_id'    => $company->id,
                'client_id'     => $creditNote->client_id,
                'order_id'      => null,
                'number'        => $this->sequenceService->nextNumber($company, 'bon_livraison'),
                'issued_at'     => now()->toDateString(),
                'status'        => 'brouillon',
                'warehouse_id'  => $warehouseId,
                'currency_code' => $creditNote->currency_code,
                'notes'         => 'Remplacement — avoir '.$creditNote->number,
                'created_by'    => Auth::id(),
            ]);

            $totalQty = 0;
            $sort     = 0;
            foreach ($creditNote->items as $item) {
                if (!$item->product_id || !($item->product?->is_stockable ?? true) || $item->quantity <= 0) {
                    continue;
                }
                $dn->items()->create([
                    'order_item_id' => null,
                    'product_id'    => $item->product_id,
                    'description'   => $item->description,
                    'unit_id'       => $item->unit_id,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'sort_order'    => $sort++,
                ]);
                $totalQty += (float) $item->quantity;
            }

            $dn->update(['total_quantity' => $totalQty]);

            $creditNote->update([
                'is_replacement'          => true,
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
            $qty     = (float) ($item['quantity']   ?? 1);
            $price   = (float) ($item['unit_price'] ?? 0);
            $tax     = (float) ($item['tax_rate_value'] ?? 0);
            $ht      = (int) round($qty * $price);
            $lineTax = (int) round($ht * ($tax / 100));

            $creditNote->items()->create([
                'product_id'     => $item['product_id']     ?? null,
                'disposition'    => in_array($item['disposition'] ?? 'restock', ['restock', 'rebut'], true)
                    ? ($item['disposition'] ?? 'restock') : 'restock',
                'description'    => $item['description']    ?? '',
                'unit_id'        => $item['unit_id']        ?? null,
                'quantity'       => $qty,
                'unit_price'     => (int) $price,
                'tax_rate_id'    => $item['tax_rate_id']    ?? null,
                'tax_rate_value' => $tax,
                'line_total_ht'  => $ht,
                'line_tax'       => $lineTax,
                'line_total_ttc' => $ht + $lineTax,
                'sort_order'     => $i,
            ]);
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($items as $item) {
            $qty     = (float) ($item['quantity']       ?? 1);
            $price   = (float) ($item['unit_price']     ?? 0);
            $tax     = (float) ($item['tax_rate_value'] ?? 0);
            $ht      = (int) round($qty * $price);
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

            if (in_array($creditNote->status, ['valide', 'applique'], true)) {
                // 1. Défaire l'application à la facture.
                $applied = (int) ($creditNote->applied_amount ?? 0);
                if ($applied > 0 && $creditNote->invoice_id) {
                    $invoice = Invoice::lockForUpdate()->findOrFail($creditNote->invoice_id);
                    $invoice->paid_amount      = max(0, (int) $invoice->paid_amount - $applied);
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
                            'product_id'      => $item->product_id,
                            'warehouse_id'    => $warehouseId,
                            'type'            => 'sortie',
                            'quantity'        => (float) $item->quantity,
                            'unit_cost'       => (float) $item->unit_price,
                            'occurred_at'     => now(),
                            'reference_type'  => 'credit_note',
                            'reference_id'    => $creditNote->id,
                            'notes'           => 'Annulation avoir ' . $creditNote->number . ' — ' . $motif,
                            'idempotency_key' => 'credit-note-cancel:' . $creditNote->id . ':' . $item->id,
                        ]);
                    }
                }

                // 3. Extourne des écritures comptables de l'avoir.
                \App\Models\JournalEntry::where('company_id', $creditNote->company_id)
                    ->where('reference', $creditNote->number)
                    ->where('status', 'valide')
                    ->whereNull('reversed_by_entry_id')
                    ->get()
                    ->each(fn ($entry) => $this->accountingService->reverseEntry(
                        $entry,
                        'Annulation avoir ' . $creditNote->number . ' — ' . $motif
                    ));
            }

            // [SEC-PHASE2] Journal d'audit : annulation d'avoir tracée
            app(AuditService::class)->log('avoir.annulation', $creditNote, [], ['motif' => $motif, 'montant' => $creditNote->total_ttc]);

            $creditNote->update([
                'status'           => 'annule',
                'remaining_credit' => 0,
                'applied_amount'   => 0,
                'notes'            => trim(($creditNote->notes ?? '') . "\n\n" . sprintf(
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
