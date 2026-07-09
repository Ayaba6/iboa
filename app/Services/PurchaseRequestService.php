<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Repositories\PurchaseRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        public readonly PurchaseRequestRepository $repository,
        private DocumentSequenceService $sequenceService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id']  = $company->id;
            $data['number']      = $this->sequenceService->nextNumber($company, 'demande_achat');
            $data['created_by']  = Auth::id();
            $data['requested_by']= $data['requested_by'] ?? Auth::id();
            $data['status']      = 'brouillon';

            $request = PurchaseRequest::create($data);
            $this->syncItems($request, $items);
            $this->recalculate($request);

            return $request->fresh();
        });
    }

    public function update(PurchaseRequest $request, array $data): PurchaseRequest
    {
        if (! $request->isEditable()) {
            throw new \RuntimeException('Cette demande ne peut plus être modifiée.');
        }

        return DB::transaction(function () use ($request, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $request->update($data);

            if ($items !== null) {
                $request->items()->delete();
                $this->syncItems($request, $items);
            }

            $this->recalculate($request);
            return $request->fresh();
        });
    }

    /**
     * Submit a draft purchase request for approval.
     */
    public function submit(PurchaseRequest $request): PurchaseRequest
    {
        if (! $request->canBeSubmitted()) {
            throw new \RuntimeException('Seules les demandes en brouillon peuvent être soumises.');
        }

        $request->update([
            'status'       => 'soumis',
            'submitted_at' => now(),
        ]);

        $fresh = $request->fresh();
        $this->notifyApproverByThreshold($fresh);

        return $fresh;
    }

    /**
     * [CDC §13.4/§16.4] Notifie l'approbateur exact selon le seuil du montant —
     * jamais une diffusion large : <500k Chef Service, <5M Directeur, ≥5M DG.
     */
    private function notifyApproverByThreshold(PurchaseRequest $request): void
    {
        $amount = (float) $request->total_estimated;
        $roles  = match (true) {
            $amount >= 5_000_000 => ['directeur'],        // DG
            $amount >= 500_000   => ['daf'],               // Directeur
            default              => ['directeur_usine'],   // Chef Service
        };

        \App\Notifications\ValidationStepNotification::sendToRoles(
            $roles,
            title: 'Demande d\'achat à valider',
            message: "DA {$request->number} soumise — montant estimé "
                . number_format($amount, 0, ',', ' ') . ' FCFA, validation requise.',
            url: route('achats.demandes-achat.show', $request),
            modelType: 'PurchaseRequest',
            modelId: $request->id,
            type: 'purchase_request_submitted',
        );
    }

    /**
     * Approve a submitted purchase request.
     */
    public function approve(PurchaseRequest $request): PurchaseRequest
    {
        if (! $request->canBeApproved()) {
            throw new \RuntimeException('Seules les demandes soumises peuvent être approuvées.');
        }

        // [CDC §13.4] Enforcement du seuil : l'approbateur doit détenir le
        // niveau correspondant au montant — <500k Chef Service (l1),
        // <5M Directeur (l2), ≥5M DG (l3). La notification ciblait déjà le
        // bon rôle ; sans ce contrôle, tout porteur de « approve » pouvait
        // valider n'importe quel montant.
        $amount   = (float) $request->total_estimated;
        $required = match (true) {
            $amount >= 5_000_000 => 'purchase_requests.validate_l3',
            $amount >= 500_000   => 'purchase_requests.validate_l2',
            default              => 'purchase_requests.validate_l1',
        };
        $user = Auth::user();
        if ($user && ! $user->can($required)) {
            $label = match ($required) {
                'purchase_requests.validate_l3' => 'la Direction Générale (≥ 5 000 000 FCFA)',
                'purchase_requests.validate_l2' => 'la Direction (≥ 500 000 FCFA)',
                default                          => 'le Chef de Service',
            };
            throw new \RuntimeException(
                'Montant de ' . number_format($amount, 0, ',', ' ') . ' FCFA : cette demande doit être approuvée par ' . $label . '.'
            );
        }

        $request->update([
            'status'      => 'approuve',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $request->fresh();
    }

    /**
     * Reject a submitted purchase request.
     */
    public function reject(PurchaseRequest $request, string $reason): PurchaseRequest
    {
        if (! $request->canBeApproved()) {
            throw new \RuntimeException('Seules les demandes soumises peuvent être rejetées.');
        }

        $request->update([
            'status'           => 'rejete',
            'rejection_reason' => $reason,
        ]);

        return $request->fresh();
    }

    /**
     * Convert an approved purchase request into a purchase order.
     */
    public function convertToPurchaseOrder(PurchaseRequest $request, int $supplierId): PurchaseOrder
    {
        if (! $request->canBeConverted()) {
            throw new \RuntimeException('Seules les demandes approuvées peuvent être converties en commande.');
        }

        return DB::transaction(function () use ($request, $supplierId) {
            $company = currentCompany();
            $request->loadMissing('items');

            $po = PurchaseOrder::create([
                'company_id'     => $company->id,
                'supplier_id'    => $supplierId,
                'fiscal_year_id' => $company->current_fiscal_year_id,
                'number'         => $this->sequenceService->nextNumber($company, 'commande_achat'),
                'status'         => 'brouillon',
                'ordered_at'     => now()->toDateString(),
                'notes'          => $request->notes,
                'created_by'     => Auth::id(),
            ]);

            foreach ($request->items as $i => $item) {
                $lineHt  = (int) $item->line_total;
                $po->items()->create([
                    'product_id'     => $item->product_id,
                    'description'    => $item->description ?: ($item->product?->name ?? ''),
                    'unit_id'        => $item->unit_id,
                    'quantity'       => $item->quantity,
                    'unit_price'     => (int) $item->estimated_price,
                    'line_total_ht'  => $lineHt,
                    'line_tax'       => 0,
                    'line_total_ttc' => $lineHt,
                    'sort_order'     => $i,
                ]);
            }

            $po->loadMissing('items');
            $subtotal = $po->items->sum('line_total_ht');

            $po->update([
                'subtotal_ht' => $subtotal,
                'total_tax'   => 0,
                'total_ttc'   => $subtotal,
            ]);

            // Mark request as converted
            $request->update([
                'status'            => 'converti',
                'purchase_order_id' => $po->id,
            ]);

            return $po;
        });
    }

    public function delete(PurchaseRequest $request): bool
    {
        if (! $request->isEditable()) {
            throw new \RuntimeException('Seules les demandes en brouillon ou rejetées peuvent être supprimées.');
        }
        return $request->delete();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(PurchaseRequest $request, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['estimated_price'] ?? 0);
            $total = (int) round($qty * $price);

            $request->items()->create([
                'purchase_request_id' => $request->id,
                'product_id'          => $item['product_id'] ?? null,
                'description'         => $item['description'] ?? '',
                'unit_id'             => $item['unit_id'] ?? null,
                'quantity'            => $qty,
                'estimated_price'     => (int) $price,
                'line_total'          => $total,
                'notes'               => $item['notes'] ?? null,
                'sort_order'          => $i,
            ]);
        }
    }

    private function recalculate(PurchaseRequest $request): void
    {
        $request->load('items');
        $total = (int) $request->items->sum('line_total');
        $request->update(['total_estimated' => $total]);
    }
}
