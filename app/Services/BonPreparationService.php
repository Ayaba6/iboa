<?php

namespace App\Services;

use App\Models\BonPreparation;
use App\Models\Order;
use App\Notifications\ValidationStepNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Modules\Production\Services\ProductionDeliveryGuard;

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
        ?string $reference = null,
        ?int $cashAccountId = null
    ): BonPreparation {
        return DB::transaction(function () use ($order, $amount, $reference, $cashAccountId) {
            $company = $order->company ?? currentCompany();

            // [FIX argent caisse] L'encaissement du guichet passe par le service
            // central : numéro, écriture comptable, transaction de caisse.
            // Avant, payment_amount ne vivait QUE sur le BP — invisible de la
            // trésorerie et de la comptabilité, ressaisie manuelle risquée.
            $cashAccountId ??= \App\Models\CashAccount::where('is_active', true)
                ->whereIn('type', ['caisse', 'especes'])->orderBy('id')->value('id')
                ?? \App\Models\CashAccount::where('is_active', true)->orderBy('id')->value('id');

            $payment = app(\App\Services\ClientPaymentService::class)->create([
                'company_id'      => $company->id,
                'client_id'       => $order->client_id,
                'amount'          => $amount,
                'status'          => 'confirme',
                'payment_date'    => now()->toDateString(),
                'method'          => 'especes',
                'reference'       => $reference,
                'cash_account_id' => $cashAccountId,
                'is_acompte'      => true, // argent reçu AVANT facture — imputé plus tard
                'force_duplicate' => true, // règlement comptoir AVANT facturation : la garde anti-doublon (client sans dette) ne s'applique pas
                'notes'           => 'Règlement caisse commande ' . $order->number . ' (bon de préparation)',
            ]);

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
                'client_payment_id'   => $payment->id,
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
    public function startLoading(BonPreparation $bp, ?string $derogationQualite = null): BonPreparation
    {
        if ($bp->status !== 'en_attente') {
            throw new \RuntimeException('Ce bon de préparation ne peut pas être démarré (statut : ' . $bp->status . ').');
        }

        // [MTO §15] On ne commence pas à préparer une production que personne n'a
        // contrôlée. Barrière QUALITÉ seulement : une préparation est par nature
        // partielle, ses quantités ne se jugent qu'au bon de livraison.
        app(ProductionDeliveryGuard::class)->assertPreparable($bp, $derogationQualite);

        $bp->update(['status' => 'en_cours']);
        return $bp->fresh();
    }

    /**
     * Magasinier marque le chargement comme terminé.
     */
    public function finishLoading(BonPreparation $bp, ?string $derogationQualite = null): BonPreparation
    {
        if ($bp->status !== 'en_cours') {
            throw new \RuntimeException('Le chargement doit être démarré avant d\'être clôturé.');
        }

        // [MTO §15] Rejoué à la clôture : un contrôle qualité a pu basculer en
        // « non conforme » ou « à reprendre » pendant le chargement. On ne
        // confirme pas comme chargé un produit devenu non livrable.
        app(ProductionDeliveryGuard::class)->assertPreparable($bp, $derogationQualite);

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
