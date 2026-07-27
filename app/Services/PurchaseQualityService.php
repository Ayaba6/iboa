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

            // 6bis. [#5] Propagation CIBLÉE : la décision ne met à jour que les
            // bobines/lots explicitement visés (`targets`), avec une quantité par
            // cible, tracée dans purchase_quality_decision_allocations. Sans cible,
            // AUCUNE bobine n'est touchée (la décision reste au niveau de la ligne).
            $this->allocateToTargets($item, $decision, $type, (array) ($opts['targets'] ?? []));

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
     * [#5/#1] Applique la décision aux cibles EXPLICITES (bobines / lots), avec
     * une quantité par cible et des soldes quantitatifs mis à jour. Chaque cible
     * produit une ligne d'allocation (append-only) portant disposition avant/après.
     *
     * Format attendu : $targets = [['coil_id' => 12, 'quantity' => 40], ['stock_lot_id' => 3, 'quantity' => 10]]
     * Aucune cible ⇒ aucune bobine/lot modifié (jamais de mise à jour en bloc).
     *
     * @throws \RuntimeException si la somme des cibles dépasse la quantité décidée
     */
    private function allocateToTargets(ReceptionItem $item, PurchaseQualityDecision $decision, string $type, array $targets): void
    {
        if ($targets === []) {
            return;
        }
        $isAcceptance = in_array($type, ['release', 'derogation_acceptance'], true);
        $sum = 0.0;
        foreach ($targets as $t) {
            $sum += (float) ($t['quantity'] ?? 0);
        }
        if ($sum > (float) $decision->quantity + 0.0001) {
            throw new \RuntimeException(sprintf(
                'Allocations qualité (%s) supérieures à la quantité décidée (%s).',
                $sum, $decision->quantity
            ));
        }

        foreach ($targets as $t) {
            $qty = (float) ($t['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $coil = isset($t['coil_id'])
                ? \App\Modules\Production\Models\Coil::lockForUpdate()->find($t['coil_id'])
                : null;
            $lot = isset($t['stock_lot_id'])
                ? \App\Models\StockLot::lockForUpdate()->find($t['stock_lot_id'])
                : null;

            $before = $coil?->quality_status ?? $lot?->quality_status;

            if ($coil) {
                $quarBefore = (float) ($coil->qty_quarantine ?? 0);
                if ($qty > $quarBefore + 0.0001) {
                    throw new \RuntimeException(sprintf(
                        'Bobine %s : décision de %s supérieure à sa quarantaine (%s).',
                        $coil->reference, $qty, $quarBefore
                    ));
                }
                // [RÈGLE A — bobine indivisible] Une bobine physique est une unité
                // qualité indivisible : la décision porte sur la TOTALITÉ de sa
                // quarantaine. Libérer/refuser une fraction exigerait une division
                // physique réelle (découpe/refendage) créant des bobines filles.
                if ($qty < $quarBefore - 0.0001) {
                    throw new \RuntimeException(sprintf(
                        'Bobine %s : libération/refus partiel interdit — une bobine physique est '
                        . 'une unité qualité indivisible (quarantaine %s, décision %s). '
                        . 'Traitez la bobine entière, ou divisez-la physiquement (découpe) pour '
                        . 'créer des bobines filles portant chacune leur disposition.',
                        $coil->reference, $quarBefore, $qty
                    ));
                }
                $quarAfter = $quarBefore - $qty;
                $coil->update([
                    'qty_quarantine'      => $quarAfter,
                    'qty_released'        => $isAcceptance ? (float) ($coil->qty_released ?? 0) + $qty : (float) ($coil->qty_released ?? 0),
                    'qty_rejected'        => $isAcceptance ? (float) ($coil->qty_rejected ?? 0) : (float) ($coil->qty_rejected ?? 0) + $qty,
                    'quality_status'      => $this->statusFor($isAcceptance, $quarAfter),
                    'quality_decision_id' => $decision->id, // dernière décision (l'historique reste dans les allocations)
                ]);
            }
            if ($lot) {
                $quarBefore = (float) ($lot->qty_quarantine ?? 0);
                $quarAfter  = max(0.0, $quarBefore - $qty);
                $lot->update([
                    'qty_quarantine' => $quarAfter,
                    'qty_released'   => $isAcceptance ? (float) ($lot->qty_released ?? 0) + $qty : (float) ($lot->qty_released ?? 0),
                    'qty_rejected'   => $isAcceptance ? (float) ($lot->qty_rejected ?? 0) : (float) ($lot->qty_rejected ?? 0) + $qty,
                    'quality_status' => $this->statusFor($isAcceptance, $quarAfter),
                ]);
            }

            DB::table('purchase_quality_decision_allocations')->insert([
                'quality_decision_id' => $decision->id,
                'reception_item_id'   => $item->id,
                'stock_lot_id'        => $lot?->id,
                'coil_id'             => $coil?->id,
                'quantity'            => $qty,
                'unit'                => 'KG',
                'disposition_before'  => $before,
                'disposition_after'   => $this->statusFor($isAcceptance, $coil ? (float) $coil->fresh()->qty_quarantine : 0.0),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    private function statusFor(bool $isAcceptance, float $quarantineAfter): string
    {
        if ($quarantineAfter > 0.0001) {
            return \App\Modules\Production\Models\Coil::QUALITY_PARTIAL_RELEASE;
        }

        return $isAcceptance
            ? \App\Modules\Production\Models\Coil::QUALITY_RELEASED
            : \App\Modules\Production\Models\Coil::QUALITY_REJECTED;
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
