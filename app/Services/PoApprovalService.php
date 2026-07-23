<?php

namespace App\Services;

use App\Models\PoApprovalThreshold;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS-PRO-APPROVAL] Workflow d'approbation des PO par seuil de montant.
 *
 *   submitForApproval()  : brouillon → approval_status=en_attente
 *   approve()            : approuve, vérifie les droits par la règle
 *   reject($reason)      : rejette, status reste brouillon → édition possible
 *   findRequiredRule()   : trouve la règle qui couvre le montant du PO
 *
 * Garde-fou : la confirmation logistique du PO doit refuser si approval requise et non approuvée.
 */
class PoApprovalService
{
    /**
     * Renvoie la règle d'approbation requise (ou null si non requise).
     */
    public function findRequiredRule(PurchaseOrder $po): ?PoApprovalThreshold
    {
        return PoApprovalThreshold::findForAmount($po->company_id, (float) $po->total_ttc);
    }

    /**
     * Soumet le PO à approbation. Déclenche le calcul auto du niveau requis.
     */
    public function submitForApproval(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'brouillon') {
            throw new \RuntimeException("Seul un PO en brouillon peut être soumis à approbation (actuel : {$po->status}).");
        }

        $rule = $this->findRequiredRule($po);
        if (!$rule) {
            // Aucun seuil ne s'applique : l'approbation n'est pas nécessaire.
            $po->update(['approval_status' => 'non_requis']);
            return $po;
        }

        $po->update([
            'approval_status'           => 'en_attente',
            'submitted_for_approval_at' => now(),
            'approved_by'               => null,
            'approved_at'               => null,
            'rejection_reason'          => null,
        ]);

        // [CDC §16.4] Notifie exactement le rôle requis par le seuil applicable —
        // jamais une diffusion large à tous les approbateurs potentiels.
        if ($rule->required_role) {
            \App\Notifications\ValidationStepNotification::sendToRoles(
                [$rule->required_role],
                title: 'Bon de commande à approuver',
                message: "PO {$po->number} soumis — montant "
                    . number_format((float) $po->total_ttc, 0, ',', ' ') . " FCFA, règle « {$rule->name} ».",
                url: route('achats.commandes.show', $po),
                modelType: 'PurchaseOrder',
                modelId: $po->id,
                type: 'po_submitted_approval',
            );
        }

        return $po->fresh();
    }

    /**
     * Approuve le PO si l'utilisateur a les droits requis par la règle.
     */
    public function approve(PurchaseOrder $po, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $user) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            if ($po->approval_status !== 'en_attente') {
                throw new \RuntimeException("Ce PO n'est pas en attente d'approbation (statut : {$po->approval_status}).");
            }

            // [SEC-PHASE2 §2] Maker-checker : l'auteur de la commande ne l'approuve pas lui-même
            app(\App\Services\MakerCheckerService::class)->assert($po->created_by, 'purchase_order.approve', "la commande {$po->number}", $po);

            $rule = $this->findRequiredRule($po);
            if ($rule && !$rule->canBeApprovedBy($user)) {
                throw new \RuntimeException(
                    "Vous n'avez pas le niveau d'approbation requis pour ce montant. "
                    . "Règle applicable : « {$rule->name} »."
                );
            }
            // [SEC-PHASE2] Sans règle de seuil configurée, l'approbation n'est pas
            // libre pour autant : la route n'exige que purchase_orders.view, donc
            // un simple lecteur pourrait approuver. Droit d'approbation de base requis.
            if (!$rule && !$user->hasRole('super_admin') && !$user->can('purchase_requests.approve')) {
                throw new \RuntimeException(
                    "Aucune règle de seuil n'est configurée : l'approbation exige le droit d'approbation achats (purchase_requests.approve)."
                );
            }

            $po->update([
                'approval_status' => 'approuve',
                'approved_by'     => $user->id,
                'approved_at'     => now(),
            ]);

            return $po->fresh();
        });
    }

    /**
     * Rejette le PO avec un motif. Le PO repasse à approval_status=rejete et le
     * créateur peut le ré-éditer puis re-soumettre.
     */
    public function reject(PurchaseOrder $po, User $user, string $reason): PurchaseOrder
    {
        if ($po->approval_status !== 'en_attente') {
            throw new \RuntimeException("Ce PO n'est pas en attente d'approbation.");
        }
        $rule = $this->findRequiredRule($po);
        if ($rule && !$rule->canBeApprovedBy($user)) {
            throw new \RuntimeException("Vous n'avez pas les droits pour rejeter ce PO.");
        }

        $po->update([
            'approval_status'  => 'rejete',
            'approved_by'      => $user->id,
            'approved_at'      => now(),
            'rejection_reason' => $reason,
        ]);
        return $po->fresh();
    }

    /**
     * Garde-fou utilisé par PurchaseOrderController::confirm() :
     * empêche la confirmation logistique si l'approbation est requise et non donnée.
     */
    public function assertApprovedForConfirm(PurchaseOrder $po): void
    {
        if ($po->approval_status === 'non_requis') return;
        if ($po->approval_status === 'approuve')   return;

        throw new \RuntimeException(
            "Ce PO ({$po->number}, total " . number_format($po->total_ttc, 0, ',', ' ') . " FCFA) "
            . "nécessite l'approbation d'un manager avant confirmation. Statut actuel : {$po->approval_status}."
        );
    }
}
