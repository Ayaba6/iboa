<?php

namespace App\Services;

use App\Events\SupplierInvoiceValidated;
use App\Models\Company;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Repositories\SupplierInvoiceRepository;
use App\Services\AccountingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceService
{
    public function __construct(
        public readonly SupplierInvoiceRepository $repository,
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

    public function create(array $data): SupplierInvoice
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            // [ACHATS A1] Anti-doublon : une même facture fournisseur (numéro
            // fournisseur) ne peut être saisie deux fois pour le même fournisseur.
            $this->assertNoDuplicateSupplierNumber(
                $company->id, $data['supplier_id'] ?? null, $data['supplier_invoice_number'] ?? null, null
            );

            $data['company_id'] = $company->id;
            $data['number']     = $this->sequenceService->nextNumber($company, 'facture_fournisseur');
            $data['created_by'] = Auth::id();
            $data['status']     = $data['status'] ?? 'recue';
            // [ACHATS A1] Clé normalisée persistée (sert l'index unique DB).
            $data['supplier_invoice_number_normalized'] = \App\Support\SupplierInvoiceNumber::normalize($data['supplier_invoice_number'] ?? null);

            [$subtotal, $taxTotal] = $this->calculateTotals($items);

            $data['subtotal_ht']     = $subtotal;
            $data['total_tax']       = $taxTotal;
            $data['total_ttc']       = $subtotal + $taxTotal;
            $data['paid_amount']     = $data['paid_amount'] ?? 0;
            $data['remaining_amount']= $data['total_ttc'] - ($data['paid_amount'] ?? 0);

            // [ACHATS A1] Backstop concurrence : si deux transactions passent le
            // pré-contrôle simultanément, l'index unique DB rejette la perdante.
            // On convertit la violation en message métier clair.
            try {
                $invoice = SupplierInvoice::create($data);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                throw new \RuntimeException(sprintf(
                    'Doublon : la facture fournisseur n° « %s » existe déjà pour ce fournisseur '
                    . '(rejet concurrent par la barrière d\'unicité).',
                    trim((string) ($data['supplier_invoice_number'] ?? ''))
                ), 0, $e);
            }
            $this->syncItems($invoice, $items);
            $this->recalculate($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * [ACHATS A1] Refuse un doublon de facture fournisseur : même numéro
     * NORMALISÉ pour le même fournisseur. Politique de RÉSERVATION : le contrôle
     * couvre toutes les factures physiques — y compris annulées et soft-deleted
     * (`withTrashed`) — car une référence saisie reste réservée dans l'historique.
     * Une correction passe par avoir / extourne / nouvelle version, jamais par
     * réutilisation du numéro après suppression logique.
     *
     * Le numéro fournisseur reste facultatif ; sans lui, aucun contrôle. La
     * barrière DB (index unique sur la clé normalisée) est la protection FORTE
     * contre la concurrence ; ce pré-contrôle sert le message clair côté métier.
     *
     * @throws \RuntimeException
     */
    private function assertNoDuplicateSupplierNumber(?int $companyId, ?int $supplierId, ?string $supplierNumber, ?int $exceptId): void
    {
        $normalized = \App\Support\SupplierInvoiceNumber::normalize($supplierNumber);
        if ($normalized === null || ! $supplierId) {
            return;
        }

        $exists = SupplierInvoice::withTrashed()
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('supplier_invoice_number_normalized', $normalized)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw new \RuntimeException(sprintf(
                'Doublon : la facture fournisseur n° « %s » existe déjà pour ce fournisseur '
                . '(numéro réservé dans l\'historique, même si annulée). Une même facture ne '
                . 'peut être saisie deux fois (risque de double paiement) — utilisez un avoir '
                . 'ou une extourne pour corriger.',
                trim((string) $supplierNumber)
            ));
        }
    }

    public function update(SupplierInvoice $inv, array $data): SupplierInvoice
    {
        return DB::transaction(function () use ($inv, $data) {
            // [INVOICE-LOCKED-GUARD] Une FF validée/payée/partiellement payée/en retard/annulée
            // ne peut plus être modifiée — utiliser un retour fournisseur pour corriger.
            $inv = SupplierInvoice::lockForUpdate()->findOrFail($inv->id);

            if (!in_array($inv->status, ['brouillon', 'recue'], true)) {
                throw new \RuntimeException(sprintf(
                    "La facture fournisseur %s est « %s » — la modification est interdite. "
                    . "Pour corriger une erreur, créez un retour fournisseur ou annulez puis ressaisissez.",
                    $inv->number,
                    $inv->status
                ));
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            // [ACHATS A1] Anti-doublon aussi à la modification (changement de
            // numéro fournisseur ou de fournisseur).
            $this->assertNoDuplicateSupplierNumber(
                $inv->company_id,
                $data['supplier_id'] ?? $inv->supplier_id,
                array_key_exists('supplier_invoice_number', $data) ? $data['supplier_invoice_number'] : $inv->supplier_invoice_number,
                $inv->id
            );
            // [ACHATS A1] Recalcule la clé normalisée si le numéro change.
            if (array_key_exists('supplier_invoice_number', $data)) {
                $data['supplier_invoice_number_normalized'] = \App\Support\SupplierInvoiceNumber::normalize($data['supplier_invoice_number']);
            }

            $inv->update($data);

            if ($items !== null) {
                $inv->items()->delete();
                $this->syncItems($inv, $items);
            }

            $this->recalculate($inv);
            return $inv->fresh();
        });
    }

    public function delete(SupplierInvoice $inv): bool
    {
        // [FIX-ACHATS-02] Only allow deletion of pre-validation invoices.
        // Validated/paid invoices have GL entries and payment allocations that
        // must be reversed via a dedicated cancellation flow, not a hard delete.
        if (!in_array($inv->status, ['brouillon', 'recue'])) {
            throw new \RuntimeException(
                'Seules les factures fournisseurs en brouillon ou reçues (non validées) peuvent être supprimées.'
            );
        }
        return $inv->delete();
    }

    /**
     * Validate a supplier invoice: set status to 'validee', set validated_at timestamp.
     */
    public function validate(SupplierInvoice $inv): SupplierInvoice
    {
        if (! in_array($inv->status, ['recue', 'brouillon'])) {
            throw new \RuntimeException('Seules les factures reçues ou en brouillon peuvent être validées.');
        }

        return DB::transaction(function () use ($inv) {
            // [ARCH-C4] Lock invoice row before status update to prevent concurrent validation.
            $inv = SupplierInvoice::lockForUpdate()->find($inv->id);

            $inv->update([
                'status'       => 'validee',
                'validated_at' => now(),
                'validated_by' => Auth::id(),
            ]);

            $fresh = $inv->fresh(['supplier', 'company']);

            // Post to GL synchronously — must be in the same transaction
            $this->accountingService->postSupplierInvoice($fresh);
            // [COMPTA-STOCK] Entrée de stock automatique
            $this->accountingService->postPurchaseStockMovement($fresh);

            // Fire event — the synchronous SyncSupplierBalanceOnInvoice listener
            // calls recalculateBalance(); do NOT also call it here (SOLDES-03 double recalc).
            event(new SupplierInvoiceValidated($fresh));

            return $fresh;
        });
    }

    /**
     * Record a payment against this invoice.
     */
    public function recordPayment(SupplierInvoice $inv, array $data): SupplierPayment
    {
        if (! in_array($inv->status, ['validee', 'partiellement_payee', 'recue'])) {
            throw new \RuntimeException('Cette facture ne peut pas recevoir de paiement dans son état actuel.');
        }

        $amount = (int) $data['amount'];
        if ($amount <= 0) {
            throw new \RuntimeException('Le montant doit être supérieur à zéro.');
        }
        if ($amount > $inv->remaining_amount) {
            throw new \RuntimeException('Le montant dépasse le reste à payer (' . number_format($inv->remaining_amount, 0, ',', ' ') . ' FCFA).');
        }

        return DB::transaction(function () use ($inv, $data, $amount) {
            // [ARCH-C4] Lock invoice row to prevent duplicate or concurrent payments.
            $inv = SupplierInvoice::lockForUpdate()->find($inv->id);

            $company = currentCompany();

            $payment = SupplierPayment::create([
                'company_id'        => $company->id,
                'supplier_id'       => $inv->supplier_id,
                'cash_account_id'   => $data['cash_account_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'number'            => $this->sequenceService->nextNumber($company, 'decaissement'),
                'amount'            => $amount,
                'payment_date'      => $data['payment_date'],
                'reference'         => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => 'confirme',
                'allocated_amount'  => $amount,
                'unallocated_amount'=> 0,
                'created_by'        => Auth::id(),
            ]);

            SupplierPaymentAllocation::create([
                'supplier_payment_id' => $payment->id,
                'supplier_invoice_id' => $inv->id,
                'amount'              => $amount,
                'allocated_at'        => now(),
                'created_by'          => Auth::id(),
            ]);

            $newPaid      = $inv->paid_amount + $amount;
            $newRemaining = $inv->total_ttc - $newPaid;
            $newStatus    = $newRemaining <= 0 ? 'payee' : 'partiellement_payee';

            $inv->update([
                'paid_amount'      => $newPaid,
                'remaining_amount' => max(0, $newRemaining),
                'status'           => $newStatus,
            ]);

            // [FIX-MAJEUR] Post supplier payment to GL (was missing from this path)
            $this->accountingService->postSupplierPayment($payment->fresh(['supplier', 'company']));

            // [FIX-TRESO] Débit du compte de trésorerie — ce chemin (bouton « Payer »
            // de la fiche facture fournisseur) créait paiement + allocation + GL
            // mais AUCUNE transaction de caisse : le solde du compte ne bougeait
            // pas (constaté en recette : DEC-2026-001, Coris Bank intact).
            if (! empty($payment->cash_account_id)) {
                $cashAccount = \App\Models\CashAccount::find($payment->cash_account_id);
                if ($cashAccount) {
                    app(\App\Services\CashAccountService::class)->recordTransaction($cashAccount, [
                        'type'             => 'debit',
                        'reference_type'   => 'SupplierPayment',
                        'reference_id'     => $payment->id,
                        'amount'           => $payment->amount,
                        'label'            => 'Décaissement ' . $payment->number . ' — ' . ($inv->supplier?->name ?? ''),
                        'transaction_date' => $payment->payment_date ?? today(),
                    ]);
                }
            }

            // Recalculate supplier balance after payment (remaining_amount has changed)
            $inv->supplier?->recalculateBalance();

            return $payment;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(SupplierInvoice $invoice, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_percent'] ?? 0);
            $tax      = (float) ($item['tax_rate_value'] ?? 0);
            $ht       = (int) round($qty * $price * (1 - $discount / 100));
            $lineTax  = (int) round($ht * ($tax / 100));
            $ttc      = $ht + $lineTax;

            $invoice->items()->create([
                'supplier_invoice_id' => $invoice->id,
                'product_id'          => $item['product_id'] ?? null,
                'description'         => $item['description'] ?? '',
                'unit_id'             => $item['unit_id'] ?? null,
                'quantity'            => $qty,
                'unit_price'          => (int) $price,
                'discount_percent'    => $discount,
                'tax_rate_id'         => $item['tax_rate_id'] ?? null,
                'tax_rate_value'      => $tax,
                'line_total_ht'       => $ht,
                'line_tax'            => $lineTax,
                'line_total_ttc'      => $ttc,
                'sort_order'          => $i,
            ]);
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_percent'] ?? 0);
            $tax      = (float) ($item['tax_rate_value'] ?? 0);
            $ht       = $qty * $price * (1 - $discount / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    private function recalculate(SupplierInvoice $invoice): void
    {
        $invoice->load('items');

        $subtotal = (int) $invoice->items->sum('line_total_ht');
        $taxTotal = (int) $invoice->items->sum('line_tax');
        $total    = $subtotal + $taxTotal;
        $paid     = (int) ($invoice->paid_amount ?? 0);

        $invoice->update([
            'subtotal_ht'     => $subtotal,
            'total_tax'       => $taxTotal,
            'total_ttc'       => $total,
            'remaining_amount'=> $total - $paid,
        ]);
    }
}
