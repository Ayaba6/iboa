<?php

namespace App\Services;

use App\Models\PurchaseQualityDecision;
use App\Models\ReceptionItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS Qualité — segment 3] Machine d'états qualité des réceptions.
 *
 *   Quarantaine → Contrôle → Libération (totale/partielle)
 *   Quarantaine → Contrôle → Refus (partiel/total, après contrôle)
 *   Quarantaine → Dérogation qualité → Acceptation sous dérogation (maker-checker)
 *
 * Chaque décision est un DOCUMENT historisé (purchase_quality_decisions) avec
 * quantités avant/après — jamais un simple statut écrasé. Une décision ne modifie
 * JAMAIS la quantité physique reçue.
 *
 * Invariants garantis par ligne (données CERTIFIED) :
 *   quarantaine initiale = libéré + refusé après contrôle + retourné + quarantaine restante
 *   accepté net = accepté initial + libéré + accepté sous dérogation − retours − annulations
 *
 * Atomicité : verrou ligne + décision + quantités + mouvements de stock
 * (DEP-QUAR → dépôt utilisable ou DEP-REFUS) dans UNE transaction.
 * Idempotence : clé durable par ligne (rejeu même clé + même contenu → même
 * décision ; même clé + contenu différent → refus).
 */
class PurchaseQualityService
{
    public function __construct(private readonly StockService $stockService) {}

    /** Libération (totale ou partielle) : quarantaine → stock utilisable. */
    public function release(ReceptionItem $item, float $qty, array $opts = []): PurchaseQualityDecision
    {
        return $this->decide($item, 'release', $qty, $opts);
    }

    /** Refus après contrôle : quarantaine → DEP-REFUS (jamais stock utilisable). */
    public function rejectAfterControl(ReceptionItem $item, float $qty, array $opts = []): PurchaseQualityDecision
    {
        if (trim((string) ($opts['reason'] ?? '')) === '') {
            throw new \RuntimeException('Motif obligatoire pour un refus après contrôle.');
        }

        return $this->decide($item, 'reject_after_control', $qty, $opts);
    }

    /**
     * Acceptation sous DÉROGATION d'une quantité non conforme.
     * Maker-checker : l'approbateur (utilisateur courant) ne peut pas être le
     * contrôleur ayant constaté la non-conformité.
     */
    public function derogationAcceptance(ReceptionItem $item, float $qty, array $opts = []): PurchaseQualityDecision
    {
        if (trim((string) ($opts['reason'] ?? '')) === '') {
            throw new \RuntimeException('Motif obligatoire pour une acceptation sous dérogation.');
        }
        $controller = $opts['controlled_by'] ?? null;
        if ($controller === null) {
            throw new \RuntimeException('Dérogation : le contrôleur ayant constaté la non-conformité doit être identifié.');
        }
        // [#8] Maker-checker : approbateur ≠ contrôleur (jamais d'auto-approbation).
        app(MakerCheckerService::class)->assert(
            (int) $controller, 'quality_derogation.approve',
            "la dérogation qualité de la ligne de réception #{$item->id}", $item
        );

        return $this->decide($item, 'derogation_acceptance', $qty, $opts);
    }

    // -------------------------------------------------------------------------

    private function decide(ReceptionItem $item, string $type, float $qty, array $opts): PurchaseQualityDecision
    {
        if ($qty <= 0) {
            throw new \RuntimeException('La quantité de la décision qualité doit être positive.');
        }

        return DB::transaction(function () use ($item, $type, $qty, $opts) {
            // 1. Verrou de la ligne (sérialise les décisions concurrentes).
            $item = ReceptionItem::lockForUpdate()->findOrFail($item->id);

            // [#3/#19] Une ligne historique non classée (UNKNOWN) n'est PAS utilisable
            // par le nouveau workflow : certification historique préalable requise.
            if ($item->accepted_quantity === null && $item->quarantine_quantity === null) {
                throw new \RuntimeException(
                    'Ligne historique non classée (disposition inconnue) : la certification '
                    . 'historique manuelle est requise avant toute décision qualité.'
                );
            }

            // 2. Idempotence durable par ligne.
            $key = trim((string) ($opts['idempotency_key'] ?? ''));
            if ($key !== '') {
                $existing = PurchaseQualityDecision::where('reception_item_id', $item->id)
                    ->where('idempotency_key', $key)->first();
                if ($existing) {
                    if ($existing->type !== $type || abs((float) $existing->quantity - $qty) > 0.0001) {
                        throw new \RuntimeException('Clé d\'idempotence réutilisée avec un contenu différent — refus.');
                    }

                    return $existing; // rejeu : même décision, aucune double libération
                }
            }

            // 3. Reliquat de quarantaine suffisant.
            $quarBefore = (float) $item->quarantine_quantity;
            if ($qty > $quarBefore + 0.0001) {
                throw new \RuntimeException(sprintf(
                    'Décision qualité (%s) supérieure à la quarantaine restante : %s demandé, %s disponible.',
                    $type, $qty, $quarBefore
                ));
            }

            $acceptedBefore = (float) $item->accepted_quantity;
            $isAcceptance   = in_array($type, ['release', 'derogation_acceptance'], true);

            // 4. Document de décision (historisé, jamais écrasé).
            $decision = PurchaseQualityDecision::create([
                'company_id'        => $item->reception?->company_id,
                'reception_id'      => $item->reception_id,
                'reception_item_id' => $item->id,
                'coil_id'           => $opts['coil_id'] ?? null,
                'lot_number'        => $item->lot_number,
                'type'              => $type,
                'quantity'          => $qty,
                'quarantine_before' => $quarBefore,
                'quarantine_after'  => $quarBefore - $qty,
                'accepted_before'   => $acceptedBefore,
                'accepted_after'    => $isAcceptance ? $acceptedBefore + $qty : $acceptedBefore,
                'criteria'          => $opts['criteria'] ?? null,
                'reason'            => $opts['reason'] ?? null,
                'requested_by'      => $opts['requested_by'] ?? Auth::id(),
                'controlled_by'     => $opts['controlled_by'] ?? Auth::id(),
                'approved_by'       => Auth::id(),
                'idempotency_key'   => $key !== '' ? $key : null,
            ]);

            // 5. Quantités de la ligne (le reçu physique ne change JAMAIS).
            $item->update([
                'quarantine_quantity' => $quarBefore - $qty,
                'accepted_quantity'   => $isAcceptance ? $acceptedBefore + $qty : $acceptedBefore,
                'rejected_quantity'   => $type === 'reject_after_control'
                    ? (float) $item->rejected_quantity + $qty
                    : (float) $item->rejected_quantity,
                'quality_status'      => ($quarBefore - $qty) > 0 ? 'en_attente'
                    : ($isAcceptance || (float) $item->accepted_quantity > 0 ? 'accepte' : 'rejete'),
            ]);

            // 6. Mouvements de stock atomiques : DEP-QUAR → destination.
            $companyId = (int) ($item->reception?->company_id ?? currentCompany()?->id);
            $quarWh    = app(QuarantineService::class)->quarantineWarehouse($companyId);
            $destWh    = $isAcceptance
                ? ($item->reception?->warehouse_id
                    ?? throw new \RuntimeException('Dépôt utilisable de destination introuvable pour la libération.'))
                : $this->refusalWarehouseId($companyId);

            $this->stockService->recordMovement([
                'product_id'        => $item->product_id,
                'warehouse_id'      => $quarWh->id,
                'dest_warehouse_id' => $destWh,
                'type'              => 'transfert',
                'quantity'          => $qty,
                'unit_cost'         => (float) $item->unit_cost,
                'occurred_at'       => now(),
                'reference_type'    => 'quality_decision',
                'reference_id'      => $decision->id,
                'lot_number'        => $item->lot_number,
                'notes'             => match ($type) {
                    'release'               => 'Libération qualité — réception ' . $item->reception?->number,
                    'derogation_acceptance' => 'Acceptation sous dérogation — réception ' . $item->reception?->number,
                    default                 => 'Refus après contrôle — réception ' . $item->reception?->number,
                },
            ]);

            // 6bis. [#11] Propagation du statut qualité au LOT et aux BOBINES de la
            // réception : la quarantaine est une disposition transversale, pas
            // seulement un emplacement. Après décision, le reliquat de quarantaine
            // détermine le statut (libéré / partiellement libéré / refusé).
            $this->propagateQualityStatus($item, $type, (float) ($quarBefore - $qty), $decision->id);

            // 7. Agrégat commande (cache réconciliable).
            if ($isAcceptance && $item->purchase_order_item_id && ($poItem = $item->purchaseOrderItem)) {
                $poItem->update([
                    'accepted_quantity' => min((float) $poItem->accepted_quantity + $qty, (float) $poItem->quantity),
                ]);
            }

            // 8. Journal d'audit chaîné.
            app(AuditService::class)->log('qualite.decision.' . $type, $decision, [], [
                'reception' => $item->reception?->number, 'ligne' => $item->id,
                'quantite' => $qty, 'quarantaine_avant' => $quarBefore,
                'quarantaine_apres' => $quarBefore - $qty, 'motif' => $opts['reason'] ?? null,
            ]);

            return $decision->fresh();
        });
    }

    /**
     * [#11] Propage la disposition qualité vers le lot et les bobines liés à la
     * ligne de réception. Statut dérivé de l'état APRÈS décision :
     *   - quarantaine restante > 0        → PARTIAL_RELEASE (une part reste bloquée) ;
     *   - plus de quarantaine, acceptation → RELEASED ;
     *   - plus de quarantaine, refus       → REJECTED (retour fournisseur attendu).
     */
    private function propagateQualityStatus(ReceptionItem $item, string $type, float $quarantineAfter, int $decisionId): void
    {
        $isAcceptance = in_array($type, ['release', 'derogation_acceptance'], true);
        $status = $quarantineAfter > 0.0001
            ? \App\Modules\Production\Models\Coil::QUALITY_PARTIAL_RELEASE
            : ($isAcceptance
                ? \App\Modules\Production\Models\Coil::QUALITY_RELEASED
                : \App\Modules\Production\Models\Coil::QUALITY_REJECTED);

        \App\Modules\Production\Models\Coil::where('reception_id', $item->reception_id)
            ->where('product_id', $item->product_id)
            ->update(['quality_status' => $status, 'quality_decision_id' => $decisionId]);

        \App\Models\StockLot::where('source_type', \App\Models\Reception::class)
            ->where('source_id', $item->reception_id)
            ->where('product_id', $item->product_id)
            ->update(['quality_status' => $status]);
    }

    /**
     * [#10] Dépôt de REFUS (matière physiquement présente, hors stock utilisable,
     * en attente de retour fournisseur). Créé à la demande : DEP-REFUS.
     */
    private function refusalWarehouseId(int $companyId): int
    {
        return \App\Models\Warehouse::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'DEP-REFUS'],
            ['name' => 'Dépôt Refus fournisseur', 'is_active' => true]
        )->id;
    }
}
