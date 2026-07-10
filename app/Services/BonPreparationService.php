<?php

namespace App\Services;

use App\Models\BonPreparation;
use App\Models\Order;
use App\Notifications\ValidationStepNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** [CDC §bon-preparation] Gère le cycle de vie des bons de préparation. */
class BonPreparationService
{
    public function __construct(private DocumentSequenceService $seq) {}

    /**
     * Crée automatiquement un BP pour une commande à crédit validée par le responsable.
     * Appelé par CommercialWorkflowService::validateOrder() pour clients crédit.
     */
    public function createForCreditOrder(Order $order): BonPreparation
    {
        return DB::transaction(function () use ($order) {
            $company = $order->company ?? currentCompany();
            $bp = BonPreparation::create([
                'company_id'    => $company->id,
                'order_id'      => $order->id,
                'fiscal_year_id'=> $order->fiscal_year_id,
                'number'        => $this->seq->nextNumber($company, 'bon_preparation'),
                'payment_mode'  => 'credit',
                'status'        => 'en_attente',
                'validated_by'  => Auth::id(),
                'validated_at'  => now(),
                'created_by'    => Auth::id(),
            ]);

            // Notifie le magasinier : un bon de préparation est disponible
            ValidationStepNotification::sendToRoles(
                ['magasinier'],
                title: 'Bon de préparation à traiter',
                message: "BP {$bp->number} — commande {$order->number} prête à charger (crédit).",
                url: route('ventes.bons-preparation.show', $bp),
                modelType: 'BonPreparation', modelId: $bp->id,
                type: 'bon_preparation_created', icon: 'archive-box', color: 'blue',
            );

            return $bp;
        });
    }

    /**
     * Crée un BP après paiement caissier (commande comptant).
     * Appelé par OrderController::registerPayment().
     */
    public function createForCashOrder(
        Order $order,
        int $amount,
        ?string $reference = null
    ): BonPreparation {
        return DB::transaction(function () use ($order, $amount, $reference) {
            $company = $order->company ?? currentCompany();
            $bp = BonPreparation::create([
                'company_id'          => $company->id,
                'order_id'            => $order->id,
                'fiscal_year_id'      => $order->fiscal_year_id,
                'number'              => $this->seq->nextNumber($company, 'bon_preparation'),
                'payment_mode'        => 'cash',
                'status'              => 'en_attente',
                'payment_amount'      => $amount,
                'payment_reference'   => $reference,
                'payment_recorded_by' => Auth::id(),
                'payment_recorded_at' => now(),
                'created_by'          => Auth::id(),
            ]);

            // Passe la commande en_preparation si elle est encore à confirme
            if ($order->status === 'confirme') {
                $order->update(['status' => 'en_preparation']);
            }

            ValidationStepNotification::sendToRoles(
                ['magasinier'],
                title: 'Bon de préparation à traiter',
                message: "BP {$bp->number} — commande {$order->number} réglée (comptant) — chargement autorisé.",
                url: route('ventes.bons-preparation.show', $bp),
                modelType: 'BonPreparation', modelId: $bp->id,
                type: 'bon_preparation_created', icon: 'archive-box', color: 'green',
            );

            return $bp;
        });
    }

    /**
     * Magasinier démarre le chargement.
     */
    public function startLoading(BonPreparation $bp): BonPreparation
    {
        if ($bp->status !== 'en_attente') {
            throw new \RuntimeException('Ce bon de préparation ne peut pas être démarré (statut : ' . $bp->status . ').');
        }
        $bp->update(['status' => 'en_cours']);
        return $bp->fresh();
    }

    /**
     * Magasinier marque le chargement comme terminé.
     */
    public function finishLoading(BonPreparation $bp): BonPreparation
    {
        if ($bp->status !== 'en_cours') {
            throw new \RuntimeException('Le chargement doit être démarré avant d\'être clôturé.');
        }
        $bp->update([
            'status'    => 'charge',
            'loaded_by' => Auth::id(),
            'loaded_at' => now(),
        ]);

        // Notifie le commercial/responsable : commande chargée, BL peut être créé
        $fresh = $bp->fresh(['order']);
        ValidationStepNotification::sendToRoles(
            ['responsable_commercial', 'commercial'],
            title: 'Chargement terminé — BL à créer',
            message: "Commande {$fresh->order->number} chargée — créer le bon de livraison.",
            url: route('ventes.commandes.show', $fresh->order_id),
            modelType: 'Order', modelId: $fresh->order_id,
            type: 'loading_finished', icon: 'truck', color: 'green',
        );

        return $fresh;
    }
}
