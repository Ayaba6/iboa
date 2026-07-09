<?php

namespace App\Services\Sync\Handlers;

use App\Models\DeliveryNote;
use App\Services\DeliveryNoteService;

/**
 * [Sync ERP] BL validé → sorties stock. Relance après échec.
 * L'idempotence (comptage sorties existantes par produit) est portée par
 * DeliveryNoteService::applyStockOutInner().
 */
class ReplayDeliveryStockSync
{
    public function __construct(private DeliveryNoteService $service)
    {
    }

    public function __invoke(DeliveryNote $dn, array $payload = []): void
    {
        if ($dn->status !== 'valide') {
            throw new \RuntimeException("BL {$dn->number} : relance impossible, le document n'est pas validé.");
        }

        $this->service->applyStockOutInner($dn);
    }
}
