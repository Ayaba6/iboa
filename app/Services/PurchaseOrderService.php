<?php

namespace App\Services;

use App\Http\Traits\HasOptimisticLocking;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Reception;
use App\Models\SupplierInvoice;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    use HasOptimisticLocking;

    public function __construct(
        public readonly PurchaseOrderRepository $repository,
        private DocumentSequenceService $sequenceService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id']     = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']         = $this->sequenceService->nextNumber($company, 'commande_achat');
            $data['created_by']     = Auth::id();
            $data['status']         = $data['status'] ?? 'brouillon';

            // Map issued_at → ordered_at if sent as issued_at
            if (isset($data['issued_at']) && !isset($data['ordered_at'])) {
                $data['ordered_at'] = $data['issued_at'];
                unset($data['issued_at']);
            }

            [$subtotal, $taxTotal] = $this->calculateTotals($items);

            $data['subtotal_ht'] = $subtotal;
            $data['total_tax']   = $taxTotal;
            $data['total_ttc']   = $subtotal + $taxTotal;

            $po = PurchaseOrder::create($data);
            $this->syncItems($po, $items);
            $this->recalculate($po);

            return $po->fresh();
        });
    }

    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data) {
            // [CONCURRENCE] Verrou optimiste
            $this->assertVersion($po, $data['_lock_version'] ?? null);
            unset($data['_lock_version'], $data['_idempotency_key']);

            $items = $data['items'] ?? null;
            unset($data['items']);

            // Map issued_at → ordered_at if needed
            if (isset($data['issued_at']) && !isset($data['ordered_at'])) {
                $data['ordered_at'] = $data['issued_at'];
                unset($data['issued_at']);
            }

            $po->update($data);

            if ($items !== null) {
                $po->items()->delete();
                $this->syncItems($po, $items);
            }

            $this->recalculate($po);
            return $po->fresh();
        });
    }

    public function confirm(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            // Lock the row first to eliminate TOCTOU between check and update.
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            if ($po->status !== 'brouillon') {
                throw new \RuntimeException('Seules les commandes en brouillon peuvent être confirmées.');
            }

            $po->update([
                'status'       => 'confirme',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            return $po->fresh();
        });
    }

    public function delete(PurchaseOrder $po): bool
    {
        if ($po->receptions()->exists()) {
            throw new \RuntimeException('Impossible de supprimer cette commande : des réceptions sont liées.');
        }

        return $po->delete();
    }

    /**
     * [ACHATS-PRO] Duplique un bon de commande en brouillon (équivalent Odoo).
     * Reset reception/invoiced quantities, statut brouillon, nouveau numéro.
     */
    public function duplicate(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po->load('items');
            $company = currentCompany();

            $new = PurchaseOrder::create([
                'company_id'      => $company->id,
                'supplier_id'     => $po->supplier_id,
                'fiscal_year_id'  => $company->current_fiscal_year_id,
                'number'          => $this->sequenceService->nextNumber($company, 'commande_achat'),
                'status'          => 'brouillon',
                'ordered_at'      => now()->toDateString(),
                'expected_at'     => now()->addDays(7)->toDateString(),
                'currency_code'   => $po->currency_code,
                'exchange_rate'   => $po->exchange_rate,
                'subtotal_ht'     => $po->subtotal_ht,
                'total_tax'       => $po->total_tax,
                'total_ttc'       => $po->total_ttc,
                'notes'           => $po->notes,
                'delivery_address'=> $po->delivery_address,
                'created_by'      => Auth::id(),
            ]);

            foreach ($po->items as $i => $item) {
                $new->items()->create([
                    'product_id'        => $item->product_id,
                    'description'       => $item->description,
                    'unit_id'           => $item->unit_id,
                    'quantity'          => $item->quantity,
                    'unit_price'        => $item->unit_price,
                    'discount_percent'  => $item->discount_percent ?? 0,
                    'tax_rate_id'       => $item->tax_rate_id,
                    'tax_rate_value'    => $item->tax_rate_value ?? 0,
                    'line_total_ht'     => $item->line_total_ht,
                    'line_tax'          => $item->line_tax,
                    'line_total_ttc'    => $item->line_total_ttc,
                    'received_quantity' => 0,
                    'invoiced_quantity' => 0,
                    'sort_order'        => $i,
                ]);
            }

            return $new->fresh('items');
        });
    }

    /**
     * Create a Reception from all PO items.
     */
    /** Statuts PO à partir desquels une réception/facture peut être créée (CDC §7.2). */
    private const RECEIVABLE_STATUSES = ['confirme', 'envoye', 'partiellement_recu'];
    private const INVOICEABLE_STATUSES = ['confirme', 'envoye', 'partiellement_recu', 'recu'];

    public function createReception(PurchaseOrder $po): Reception
    {
        return DB::transaction(function () use ($po) {
            // [CDC §7.4] Une réception ne peut être créée que pour un PO confirmé
            // (validation + approbation seuils déjà passées) — garde-fou serveur,
            // jusqu'ici porté uniquement par l'affichage conditionnel de la vue.
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
            if (! in_array($po->status, self::RECEIVABLE_STATUSES, true)) {
                throw new \RuntimeException(
                    "Cette commande doit être confirmée avant de créer une réception (statut actuel : {$po->status})."
                );
            }

            $company = currentCompany();

            // [FIX-MAJEUR] Use DocumentSequenceService for collision-free numbering
            $receptionNumber = $this->sequenceService->nextNumber($company, 'reception');

            $reception = Reception::create([
                'company_id'       => $company->id,
                'supplier_id'      => $po->supplier_id,
                'purchase_order_id'=> $po->id,
                'number'           => $receptionNumber,
                'status'           => 'brouillon',
                'received_at'      => now()->toDateString(),
                'type'             => 'totale',
                'created_by'       => Auth::id(),
            ]);

            $po->load('items');

            foreach ($po->items as $item) {
                $reception->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'product_id'             => $item->product_id,
                    'description'            => $item->description,
                    'unit_id'                => $item->unit_id,
                    'expected_quantity'      => $item->quantity,
                    'received_quantity'      => $item->quantity,
                    'rejected_quantity'      => 0,
                    'unit_cost'              => $item->unit_price,
                    'quality_status'         => 'accepte',
                ]);
            }

            // Update PO status to partiellement_recu
            if ($po->status === 'brouillon' || $po->status === 'envoye' || $po->status === 'confirme') {
                $po->update(['status' => 'partiellement_recu']);
            }

            return $reception;
        });
    }

    /**
     * Create a SupplierInvoice from a PO.
     */
    public function createSupplierInvoice(PurchaseOrder $po): SupplierInvoice
    {
        return DB::transaction(function () use ($po) {
            // [SYNC-FIX-02] Lock the PO row + check duplicate INSIDE the transaction
            // to eliminate the TOCTOU race that allowed concurrent double-billing.
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            // [CDC §7.4] Pas de facture fournisseur tant que la commande n'est pas
            // confirmée — même garde-fou serveur que createReception().
            if (! in_array($po->status, self::INVOICEABLE_STATUSES, true)) {
                throw new \RuntimeException(
                    "Cette commande doit être confirmée avant de créer une facture (statut actuel : {$po->status})."
                );
            }

            if (SupplierInvoice::where('purchase_order_id', $po->id)
                ->whereNotIn('status', ['annulee'])
                ->whereNull('deleted_at')
                ->exists()
            ) {
                throw new \RuntimeException('Une facture fournisseur a déjà été créée pour ce bon de commande ('.$po->number.').');
            }

            $company = currentCompany();

            $invoiceNumber = $this->sequenceService->nextNumber($company, 'facture_fournisseur');

            $po->load('items');

            $invoice = SupplierInvoice::create([
                'company_id'       => $company->id,
                'supplier_id'      => $po->supplier_id,
                'purchase_order_id'=> $po->id,
                'number'           => $invoiceNumber,
                'status'           => 'recue',
                'received_at'      => now()->toDateString(),
                'subtotal_ht'      => $po->subtotal_ht,
                'total_tax'        => $po->total_tax,
                'total_ttc'        => $po->total_ttc,
                'paid_amount'      => 0,
                'remaining_amount' => $po->total_ttc,
                'created_by'       => Auth::id(),
            ]);

            foreach ($po->items as $i => $item) {
                $invoice->items()->create([
                    'product_id'     => $item->product_id,
                    'description'    => $item->description,
                    'unit_id'        => $item->unit_id,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'tax_rate_id'    => $item->tax_rate_id,
                    'tax_rate_value' => $item->tax_rate_value,
                    'line_total_ht'  => $item->line_total_ht,
                    'line_tax'       => $item->line_tax,
                    'line_total_ttc' => $item->line_total_ttc,
                    'sort_order'     => $i,
                ]);
            }

            if (in_array($po->status, ['partiellement_recu', 'recu', 'confirme'])) {
                $po->update(['status' => 'facture']);
            }

            return $invoice;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(PurchaseOrder $po, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty  = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = (int) round($qty * $price * (1 - $disc / 100));
            $lineTax = (int) round($ht * ($tax / 100));
            $ttc   = $ht + $lineTax;

            $po->items()->create([
                'product_id'       => $item['product_id'] ?? null,
                'description'      => $item['description'] ?? '',
                'unit_id'          => $item['unit_id'] ?? null,
                'quantity'         => $qty,
                'unit_price'       => (int) $price,
                'discount_percent' => $disc,
                'tax_rate_id'      => $item['tax_rate_id'] ?? null,
                'tax_rate_value'   => $tax,
                'line_total_ht'    => $ht,
                'line_tax'         => $lineTax,
                'line_total_ttc'   => $ttc,
                'sort_order'       => $i,
            ]);
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = $qty * $price * (1 - $disc / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    private function recalculate(PurchaseOrder $po): void
    {
        $po->load('items');

        $subtotal = (int) $po->items->sum('line_total_ht');
        $taxTotal = (int) $po->items->sum('line_tax');
        $total    = $subtotal + $taxTotal;

        $po->update([
            'subtotal_ht' => $subtotal,
            'total_tax'   => $taxTotal,
            'total_ttc'   => $total,
        ]);
    }
}
