<?php

namespace App\Modules\Production\Services;

use App\Models\JournalEntry;
use App\Modules\Production\Models\ProductionOrder;

/**
 * [PRODUCTION — chaîne centrale] Calcule l'état de la chaîne métier complète
 * d'un OF : Commande → Vérif stock → OF → Réservation → Production → Qualité
 * → Entrée stock PF → Livraison → Facturation → Comptabilisation.
 *
 * Lecture seule : agrège l'état réel depuis les modules connectés.
 * État par étape : done · current · pending · blocked · na (non applicable).
 */
class ProductionWorkflowService
{
    public function steps(ProductionOrder $order): array
    {
        $order->loadMissing([
            'order.deliveryNotes', 'order.invoices', 'reservations', 'outputs',
            'qualityControls', 'consumptions',
        ]);

        // La commande est jugée sur la RELATION RÉSOLUE, pas sur la clé étrangère.
        // `order_id` peut désigner une commande supprimée logiquement ou hors
        // périmètre de la société courante : la clé subsiste, `$order->order`
        // rend null, et les lignes suivantes déréférençaient ce null — HTTP 500
        // sur la fiche OF. Constaté sur OF-2026-0001 et OF-2026-0003, qui
        // pointent CMD-2026-001 et CMD-2026-002, annulées puis supprimées.
        $commande   = $order->order;
        $hasOrder   = $commande !== null;
        $reserved   = $order->reservations->where('status', 'reserved')->isNotEmpty();
        $produced   = $order->outputs->isNotEmpty();
        $qc         = $order->qualityControls->sortByDesc('id')->first();
        $qcOk       = $qc && $qc->status === 'conforme';
        $qcBad      = $qc && $qc->status === 'non_conforme';
        $delivered  = $hasOrder && $commande->deliveryNotes->isNotEmpty();
        $invoiced   = $hasOrder && $commande->invoices->isNotEmpty();
        $posted     = JournalEntry::where('company_id', $order->company_id)
                        ->where('reference', 'like', $order->number . '%')->exists();

        $st = $order->status;

        return [
            $this->step('commande', 'Commande client', $hasOrder ? 'done' : 'na',
                $hasOrder ? route('ventes.commandes.show', $commande) : null),

            $this->step('stock_check', 'Vérification du stock',
                in_array($st, ['lance', 'en_cours', 'termine'], true) ? 'done' : ($st === 'brouillon' ? 'current' : 'na')),

            $this->step('of', 'Ordre de fabrication', 'done'),

            $this->step('reservation', 'Réservation matières / PF',
                $reserved ? 'done' : ($st === 'termine' ? 'pending' : 'na')),

            $this->step('production', 'Production',
                $st === 'termine' ? 'done' : ($st === 'en_cours' ? 'current' : ($st === 'annule' ? 'na' : 'pending'))),

            $this->step('quality', 'Contrôle qualité',
                $qcOk ? 'done' : ($qcBad ? 'blocked' : ($st === 'termine' ? 'pending' : 'na'))),

            $this->step('pf_stock', 'Entrée stock produit fini',
                $produced ? 'done' : ($st === 'termine' || $st === 'en_cours' ? 'pending' : 'na')),

            $this->step('delivery', 'Livraison',
                $delivered ? 'done' : ($hasOrder && $st === 'termine' ? 'pending' : 'na'),
                $hasOrder ? route('ventes.commandes.show', $commande) : null),

            $this->step('invoice', 'Facturation',
                $invoiced ? 'done' : ($hasOrder && $st === 'termine' ? 'pending' : 'na'),
                $hasOrder ? route('ventes.commandes.show', $commande) : null),

            $this->step('accounting', 'Comptabilisation',
                $posted ? 'done' : ($st === 'termine' ? 'pending' : 'na')),
        ];
    }

    private function step(string $key, string $label, string $state, ?string $link = null): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'link' => $link];
    }
}
