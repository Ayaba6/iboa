<?php

namespace App\Services;

use App\Events\OrderConfirmed;
use App\Models\Client;
use App\Models\CommercialValidation;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Modules\Production\Services\ReservationService;
use App\Notifications\ValidationStepNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service centralisé pour les transitions de statut des documents commerciaux.
 *
 * Toute la logique métier de validation est ici — les controllers appellent
 * ce service, jamais les méthodes du trait directement (sauf pour lire l'état).
 */
class CommercialWorkflowService
{
    public function __construct(
        private DeliveryNoteService $deliveryNoteService,
        private InvoiceService $invoiceService,
        private CreditNoteService $creditNoteService,
        private BonPreparationService $bonPrepService,
        private CustomerCreditExposureService $creditExposureService,
    ) {}

    // ── Soumission ────────────────────────────────────────────────────────────

    /**
     * Soumet un document à validation.
     *
     * @param  Quote|Order|DeliveryNote|Invoice|CreditNote  $document
     *
     * @throws \RuntimeException
     */
    public function submit(mixed $document, ?string $motif = null): void
    {
        $this->assertPermission('sales.submit');

        if ($document instanceof Order || $document instanceof Quote) {
            app(SalesFloorWaiverService::class)->assertDocumentMayProceed($document);
        }

        if ($document instanceof Order) {
            $document = DB::transaction(function () use ($document, $motif) {
                // Verrouille le document puis le client. Le verrou client sérialise toutes
                // les commandes concurrentes qui consomment le même plafond de crédit.
                $fresh = Order::lockForUpdate()->findOrFail($document->id);
                if (! $fresh->isSubmittable()) {
                    throw new \RuntimeException("Cette commande a déjà été soumise (statut : {$fresh->status}).");
                }

                $this->creditExposureService->assertMaySubmit($fresh);
                $fresh->submit($motif);

                return $fresh->fresh('client');
            });
        } else {
            $document->submit($motif);
        }

        // [CDC §13.1/§17] Document soumis → notifie exactement le rôle qui valide
        // ce type de document, jamais un envoi large. Message enrichi :
        // demandeur + client + montant pour décider sans ouvrir le document.
        $demandeur = Auth::user()?->name ?? 'Utilisateur';
        $client = $document->client?->name ?? Client::find($document->client_id ?? 0)?->name;
        $montant = isset($document->total_ttc)
            ? number_format((int) $document->total_ttc, 0, ',', ' ').' FCFA'
            : null;
        $contexte = implode(' · ', array_filter([
            $demandeur ? "par {$demandeur}" : null,
            $client ? "client {$client}" : null,
            $montant,
        ]));

        match (true) {
            $document instanceof Order => ValidationStepNotification::sendToRoles(
                ['comptable', 'daf'],
                title: 'Validation financière requise',
                message: "Commande {$document->number} soumise {$contexte} — validation Finance en attente.",
                url: route('ventes.commandes.show', $document),
                modelType: 'Order', modelId: $document->id, type: 'order_submitted',
            ),
            $document instanceof Quote => ValidationStepNotification::sendToRoles(
                ['responsable_commercial'],
                title: 'Devis à valider',
                message: "Devis {$document->number} soumis {$contexte} — validation requise.",
                url: route('ventes.devis.show', $document),
                modelType: 'Quote', modelId: $document->id, type: 'quote_submitted',
            ),
            $document instanceof DeliveryNote => ValidationStepNotification::sendToRoles(
                ['responsable_commercial'],
                title: 'Bon de livraison à valider',
                message: "BL {$document->number} soumis {$contexte} — validation requise avant expédition.",
                url: route('ventes.bons-livraison.show', $document),
                modelType: 'DeliveryNote', modelId: $document->id, type: 'delivery_note_submitted',
            ),
            $document instanceof Invoice => ValidationStepNotification::sendToRoles(
                ['comptable'],
                title: 'Facture à valider',
                message: "Facture {$document->number} soumise {$contexte} — validation comptable requise.",
                url: route('ventes.factures.show', $document),
                modelType: 'Invoice', modelId: $document->id, type: 'invoice_submitted',
            ),
            $document instanceof CreditNote => ValidationStepNotification::sendToRoles(
                ['comptable'],
                title: 'Avoir à valider',
                message: "Avoir {$document->number} soumis {$contexte} — validation comptable requise.",
                url: route('ventes.avoirs.show', $document),
                modelType: 'CreditNote', modelId: $document->id, type: 'credit_note_submitted',
            ),
            default => null,
        };
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Valide un devis.
     *
     * @throws \RuntimeException
     */
    public function validateQuote(Quote $quote, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        app(SalesFloorWaiverService::class)->assertDocumentMayProceed($quote);
        $quote->assertCanValidate();
        $quote->validateDocument('valide', $motif);

        $this->notifyCreatorOfValidation($quote, 'Devis validé', "Devis {$quote->number} validé — prêt à transmettre au client.", route('ventes.devis.show', $quote), 'Quote');
    }

    /**
     * Valide une commande (validation financière — en_attente_validation → confirme).
     * Déclenche OrderConfirmed, identique au circuit direct OrderService::confirm() :
     * réservation stock (ReserveStockOnOrderConfirmed) + auto-création OF pour les
     * articles MTO (TriggerMtoProductionOnOrderConfirmed).
     *
     * @throws \RuntimeException
     */
    public function validateOrder(Order $order, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        app(SalesFloorWaiverService::class)->assertDocumentMayProceed($order);
        $order->assertCanValidate();

        DB::transaction(function () use ($order, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double validation.
            $fresh = Order::lockForUpdate()->findOrFail($order->id);
            if (! $fresh->isValidatable()) {
                throw new \RuntimeException("Cette commande a déjà été traitée (statut : {$fresh->status}).");
            }
            $fresh->validateDocument('confirme', $motif);

            event(new OrderConfirmed($fresh->fresh()));
        });

        // [CDC §13.1] Validation financière obtenue → Chef Production génère l'OF.
        $fresh = $order->fresh();
        ValidationStepNotification::sendToRoles(
            ['chef_production', 'directeur_usine'],
            title: 'Commande confirmée — OF à générer',
            message: "Commande {$fresh->number} validée financièrement — ordre de fabrication à générer.",
            url: route('ventes.commandes.show', $fresh),
            modelType: 'Order',
            modelId: $fresh->id,
            type: 'order_validated',
            icon: 'cog',
            color: 'blue',
        );

        // [CDC §commande-crédit] Commande crédit validée par responsable → bon de préparation auto-créé.
        // Le bon de préparation autorise le magasinier à procéder au chargement.
        $client = $fresh->client ?? Client::find($fresh->client_id);
        if ($client && $client->isCredit() && ! $fresh->hasBonPreparation()) {
            $this->bonPrepService->createForCreditOrder($fresh);
        }
    }

    /**
     * Valide un bon de livraison.
     * Applique également les mouvements de sortie de stock, la libération des
     * réservations et la mise à jour du statut de la commande parente.
     *
     * @throws \RuntimeException
     */
    public function validateDeliveryNote(DeliveryNote $dn, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $dn->assertCanValidate();

        // [VENTE↔PRODUCTION] Blocages livraison pour commandes fabriquées (QC + qté produite).
        app(ProductionDeliveryGuard::class)->assertDeliverable($dn);

        DB::transaction(function () use ($dn, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double sortie de stock.
            $dn = DeliveryNote::lockForUpdate()->findOrFail($dn->id);
            if (! $dn->isValidatable()) {
                throw new \RuntimeException("Ce document a déjà été traité (statut : {$dn->status}).");
            }
            $dn->validateDocument('valide', $motif);
            // [FIX-BL-STOCK] Créer les mouvements de sortie de stock après validation interne.
            $this->deliveryNoteService->applyStockOut($dn->fresh());
        });

        $fresh = $dn->fresh(['order.invoices']);

        // [CDC §bon-livraison] BL validé → génère automatiquement la facture
        // (si la commande n'a pas déjà une facture active).
        $order = $fresh->order;
        if ($order && ! $order->invoices()->whereIn('status', ['en_attente_validation', 'emise', 'envoyee', 'partiellement_payee', 'payee'])->exists()) {
            try {
                $this->invoiceService->createFromDeliveryNote($fresh);
            } catch (\Throwable) {
                // Ne bloque pas la validation BL si la création facture échoue
                // (ex: BL partiel — InvoiceService gère ce cas)
            }
        }

        // [CDC §13 Livraison] BL validé, stock sorti → notifie le commercial créateur,
        // pas tout le rôle — c'est lui qui suit ce client.
        if ($fresh->createdBy) {
            $fresh->createdBy->notify(new ValidationStepNotification(
                title: 'Livraison expédiée',
                message: "BL {$fresh->number} validé — marchandise sortie de stock.",
                url: route('ventes.bons-livraison.show', $fresh),
                modelType: 'DeliveryNote', modelId: $fresh->id,
                type: 'delivery_note_validated', icon: 'truck', color: 'green',
            ));
        }
    }

    /**
     * Valide une facture.
     * Applique également la comptabilisation GL, la sortie de stock et l'événement
     * InvoiceValidated — identique au circuit direct InvoiceService::validate().
     *
     * @throws \RuntimeException
     */
    public function validateInvoice(Invoice $invoice, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $invoice->assertCanValidate();

        DB::transaction(function () use ($invoice, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double comptabilisation GL + double sortie stock.
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->isValidatable()) {
                throw new \RuntimeException("Cette facture a déjà été traitée (statut : {$invoice->status}).");
            }

            // Définir due_at si manquant avant de valider
            if (! $invoice->due_at) {
                $invoice->update([
                    'due_at' => now()->addDays(30)->toDateString(),
                ]);
            }

            $invoice->validateDocument('emise', $motif);

            // [FIX-FA-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->invoiceService->applyValidationSideEffects($invoice->fresh(['client', 'company']));
        });

        $fresh = $invoice->fresh();
        $this->notifyCreatorOfValidation($fresh, 'Facture validée', "Facture {$fresh->number} validée — comptabilisée.", route('ventes.factures.show', $fresh), 'Invoice');
    }

    /**
     * Valide un avoir.
     * Applique également la comptabilisation GL, le retour de stock et l'événement
     * CreditNoteValidated — identique au circuit direct CreditNoteService::validate().
     *
     * @throws \RuntimeException
     */
    public function validateCreditNote(CreditNote $cn, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $cn->assertCanValidate();

        DB::transaction(function () use ($cn, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double comptabilisation GL + double retour stock.
            $cn = CreditNote::lockForUpdate()->findOrFail($cn->id);
            if (! $cn->isValidatable()) {
                throw new \RuntimeException("Cet avoir a déjà été traité (statut : {$cn->status}).");
            }
            $cn->validateDocument('valide', $motif);
            // [FIX-AVOIR-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->creditNoteService->applyValidationSideEffects($cn->fresh(['client', 'company']));
        });

        $fresh = $cn->fresh();
        $this->notifyCreatorOfValidation($fresh, 'Avoir validé', "Avoir {$fresh->number} validé — comptabilisé.", route('ventes.avoirs.show', $fresh), 'CreditNote');
    }

    // ── Refus ─────────────────────────────────────────────────────────────────

    /**
     * Refuse un document (retour en brouillon avec motif).
     *
     * @throws \RuntimeException
     */
    public function reject(mixed $document, string $motif): void
    {
        $this->assertPermission('sales.reject');
        $document->rejectDocument($motif);
    }

    // ── Annulation ────────────────────────────────────────────────────────────

    /**
     * Annule un document avec motif.
     *
     * @throws \RuntimeException
     */
    public function cancel(mixed $document, string $motif): void
    {
        $this->assertPermission('sales.cancel');

        // [Matrice annulations] L'avoir a des effets riches (stock, GL,
        // application facture) : son annulation passe par le service dédié qui
        // inverse tout — un simple changement de statut laissait le stock
        // gonflé, la facture faussée et l'écriture en place.
        if ($document instanceof CreditNote) {
            app(CreditNoteService::class)->cancel($document, $motif);

            return;
        }

        // Statut d'annulation selon le type
        $cancelledStatus = match (true) {
            $document instanceof Invoice => 'annulee',
            default => 'annule',
        };

        $document->cancelDocument($cancelledStatus, $motif);

        // [V4] Annulation d'une commande → libère les réservations de produit fini.
        if ($document instanceof Order) {
            app(ReservationService::class)->releaseForOrder($document);
        }

        // [SYNC] Annulation d'une facture → recalcule le montant facturé de la commande.
        if ($document instanceof Invoice) {
            Order::resyncInvoicedAmount($document->order_id);
        }
    }

    // ── Dashboard KPIs ────────────────────────────────────────────────────────

    /**
     * Retourne les KPIs du workflow validation pour le dashboard Ventes.
     */
    public function getDashboardKpis(): array
    {
        $companyId = currentCompany()?->id;

        return [
            // Devis
            'quotes_brouillon' => Quote::where('company_id', $companyId)->where('status', 'brouillon')->count(),
            'quotes_en_attente' => Quote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'quotes_envoyes' => Quote::where('company_id', $companyId)->where('status', 'envoye')->count(),

            // Commandes
            'orders_en_attente' => Order::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'orders_confirmes' => Order::where('company_id', $companyId)->where('status', 'confirme')->count(),

            // Bons de livraison
            'deliveries_en_attente' => DeliveryNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'deliveries_a_facturer' => DeliveryNote::where('company_id', $companyId)->where('status', 'valide')->count(),

            // Factures
            'invoices_en_attente' => Invoice::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'invoices_emises' => Invoice::where('company_id', $companyId)->whereIn('status', ['emise', 'envoyee'])->count(),
            'invoices_impayees' => Invoice::where('company_id', $companyId)->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])->count(),

            // Avoirs
            'credit_notes_en_attente' => CreditNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),

            // Documents refusés récents (7j)
            'recently_rejected' => CommercialValidation::where('action', 'refus')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),

            // Total en attente toutes catégories
            'total_pending' => Quote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + Order::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + DeliveryNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + Invoice::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + CreditNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * [CDC §13/§17] Confirme au créateur du document que sa demande de
     * validation a abouti — notification ciblée à la personne précise, pas
     * à un rôle entier.
     */
    private function notifyCreatorOfValidation(mixed $document, string $title, string $message, string $url, string $modelType): void
    {
        $creator = $document->createdBy;
        if (! $creator) {
            return;
        }
        $creator->notify(new ValidationStepNotification(
            $title, $message, $url, $modelType, $document->id,
            type: 'document_validated', icon: 'check-circle', color: 'green',
        ));
    }

    /**
     * @throws \RuntimeException si l'utilisateur n'a pas la permission requise.
     */
    private function assertPermission(string $permission): void
    {
        if (! Auth::user()?->can($permission)) {
            throw new \RuntimeException(
                "Vous n'avez pas la permission d'effectuer cette action ({$permission})."
            );
        }
    }
}
