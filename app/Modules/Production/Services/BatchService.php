<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Lots de fabrication (batch) — traçabilité produit fini.
 */
class BatchService
{
    public function createForOrder(ProductionOrder $order, ?float $quantity = null): ProductionBatch
    {
        if (in_array($order->status, ['brouillon', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'Lot impossible sur un OF brouillon ou annulé.']);
        }

        $seq = $order->batches()->count() + 1;
        $number = 'LOT-' . $order->number . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        // [Cohérence lot] Une quantité vide OU nulle/négative (0 explicite envoyé
        // par le formulaire) retombe sur la quantité produite, sinon demandée.
        // Sinon on créait un lot fantôme à 0 (créé avant la déclaration de prod).
        // NB : quantity_produced est casté decimal → "0.00" (chaîne TRUTHY en PHP).
        // Il faut caster en float AVANT le ?: sinon "0.00" masque le fallback demandé.
        $qty = ($quantity !== null && $quantity > 0)
            ? $quantity
            : ((float) $order->quantity_produced ?: (float) $order->quantity_requested);

        return $order->batches()->create([
            'company_id'  => $order->company_id,
            'product_id'  => $order->product_id,
            'batch_number'=> $number,
            'quantity'    => $qty,
            'status'      => 'en_cours',
            'produced_at' => now(),
            'created_by'  => Auth::id(),
        ]);
    }

    public function close(ProductionBatch $batch): void
    {
        // [Cohérence lot] À la clôture, un lot resté à 0 (créé avant la déclaration
        // de production) est réconcilié sur la quantité réellement produite de l'OF.
        $data = ['status' => 'cloture'];
        if ((float) $batch->quantity <= 0) {
            $produced = (float) ($batch->productionOrder?->quantity_produced ?? 0);
            if ($produced > 0) {
                $data['quantity'] = $produced;
            }
        }
        $batch->update($data);
    }
}
