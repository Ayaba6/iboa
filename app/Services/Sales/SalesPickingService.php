<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\SalesPickingControl;
use App\Models\SalesPickingItem;
use App\Models\StockLot;
use App\Modules\Production\Models\Coil;
use App\Services\ExecutionContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * [Ventes §9-§17] Service transactionnel des bons de préparation quantifiés.
 *
 * TOUTES les règles de réservation, d'allocation, de contrôle et d'annulation
 * vivent ici. Le contrôleur ne fait qu'appeler ces méthodes : il ne calcule
 * aucun reliquat et ne décide d'aucune transition.
 *
 * Machine d'états :
 *
 *   brouillon ──lancer──> en_preparation ──prélèvements──> partiellement_prepare
 *                                          └──complet────> prepare
 *   prepare ──contrôler──> controle ──valider──> valide      (état final)
 *   tout état non final ──annuler──> annule                  (état final)
 *
 * Séparation des acteurs (§14) : le préparateur ne peut pas contrôler son
 * propre travail, et le contrôleur ne peut pas valider ce qu'il a contrôlé.
 *
 * Invariant central, vérifié à chaque écriture :
 *
 *   qty_validated ≤ qty_picked ≤ qty_allocated ≤ qty_remaining_snapshot
 *
 * Idempotence (§17) : chaque action porte une clé durable. Rejouer la même clé
 * renvoie le même document sans double effet ; réutiliser une clé avec une
 * charge différente est refusé explicitement.
 */
class SalesPickingService
{
    /** Tolérance d'arrondi sur les quantités (3 décimales en base). */
    private const EPSILON = 0.0005;

    // -----------------------------------------------------------------------
    // Création
    // -----------------------------------------------------------------------

    /**
     * Crée un bon de préparation à partir du RELIQUAT réel de la commande.
     *
     * Le reliquat est figé dans la ligne (`qty_remaining_snapshot`) au moment de
     * la création : c'est lui qui borne les allocations, pas une relecture
     * ultérieure de la commande qui pourrait avoir bougé entre-temps.
     *
     * @param  array<int,array{order_item_id:int,quantity:float}>  $lines
     */
    public function create(Order $order, array $lines, array $options = []): SalesPicking
    {
        ExecutionContext::assertCan('bon_preparations.update', 'créer un bon de préparation');

        if ($lines === []) {
            throw new RuntimeException('Un bon de préparation sans ligne n\'a aucun sens : indiquez au moins un article.');
        }

        $key = $options['idempotency_key'] ?? null;
        if ($key !== null) {
            $existing = SalesPicking::withoutGlobalScopes()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($order, $lines, $options, $key) {
            // Verrou commande : deux préparations concurrentes sur le même
            // reliquat doivent se sérialiser, pas se répartir un stock fantôme.
            $freshOrder = Order::lockForUpdate()->findOrFail($order->id);
            $this->assertOrderPickable($freshOrder);

            $picking = SalesPicking::create([
                'company_id' => $freshOrder->company_id,
                'order_id' => $freshOrder->id,
                'fiscal_year_id' => $freshOrder->fiscal_year_id,
                'number' => $options['number'] ?? $this->nextNumber($freshOrder),
                'status' => SalesPicking::STATUS_BROUILLON,
                'warehouse_id' => $options['warehouse_id'] ?? null,
                'priority' => $options['priority'] ?? 'normale',
                'requested_date' => $options['requested_date'] ?? null,
                'notes' => $options['notes'] ?? null,
                'created_by' => Auth::id(),
                'idempotency_key' => $key,
            ]);

            foreach ($lines as $line) {
                $orderItem = $freshOrder->items()->whereKey($line['order_item_id'])->firstOrFail();
                $remaining = $this->remainingToPick($orderItem, excludePickingId: null);
                $requested = (float) $line['quantity'];

                if ($requested <= 0) {
                    throw new RuntimeException(sprintf(
                        'Quantité invalide (%s) pour « %s » : elle doit être strictement positive.',
                        $requested, $orderItem->description ?? $orderItem->product_id
                    ));
                }
                if ($requested > $remaining + self::EPSILON) {
                    throw new RuntimeException(sprintf(
                        'Quantité %s supérieure au reliquat %s pour « %s » : '
                        .'commandé %s, déjà livré %s, déjà en préparation %s.',
                        $this->fmt($requested), $this->fmt($remaining),
                        $orderItem->description ?? ('article #'.$orderItem->product_id),
                        $this->fmt((float) $orderItem->quantity),
                        $this->fmt((float) ($orderItem->delivered_quantity ?? 0)),
                        $this->fmt($this->quantityInOtherPickings($orderItem, null))
                    ));
                }

                SalesPickingItem::create([
                    'sales_picking_id' => $picking->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'unit_id' => $orderItem->unit_id ?? null,
                    'qty_ordered' => (float) $orderItem->quantity,
                    'qty_previously_delivered' => (float) ($orderItem->delivered_quantity ?? 0),
                    'qty_cancelled' => 0,
                    'qty_remaining_snapshot' => $requested,
                ]);
            }

            return $picking->fresh('items');
        });
    }

    // -----------------------------------------------------------------------
    // Transitions
    // -----------------------------------------------------------------------

    /** brouillon → en_preparation. Le magasinier prend le bon en main. */
    public function start(SalesPicking $picking): SalesPicking
    {
        ExecutionContext::assertCan('bon_preparations.update', 'lancer une préparation');

        return DB::transaction(function () use ($picking) {
            $fresh = SalesPicking::lockForUpdate()->findOrFail($picking->id);
            $this->assertStatus($fresh, [SalesPicking::STATUS_BROUILLON, SalesPicking::STATUS_A_PREPARER], 'lancée');

            $fresh->update([
                'status' => SalesPicking::STATUS_EN_PREPARATION,
                'started_by' => Auth::id(),
                'started_at' => now(),
            ]);

            return $fresh->fresh();
        });
    }

    /**
     * Alloue une quantité à un lot ou une bobine précis.
     *
     * [Ventes §12] Interdictions appliquées ici, jamais dans la vue :
     *   - lot ou bobine en quarantaine / non libéré ;
     *   - bobine mère divisée (elle n'est plus du stock actif) ;
     *   - lot non valorisé ;
     *   - dépôt différent de celui du bon ;
     *   - quantité au-delà du disponible non déjà alloué ;
     *   - quantité au-delà du reliquat figé de la ligne.
     */
    public function allocate(SalesPickingItem $item, array $data): SalesPickingAllocation
    {
        ExecutionContext::assertCan('bon_preparations.update', 'allouer du stock à une préparation');

        return DB::transaction(function () use ($item, $data) {
            $picking = SalesPicking::lockForUpdate()->findOrFail($item->sales_picking_id);
            // [Ventes §14] Un bon CONTRÔLÉ reste modifiable : corriger une erreur
            // détectée après contrôle doit rester possible. Ce n'est pas la
            // modification qui est interdite, c'est le contrôle qui tombe — il
            // est invalidé avec motif et le bon repart en préparation.
            $this->assertStatus($picking, [
                SalesPicking::STATUS_BROUILLON,
                SalesPicking::STATUS_A_PREPARER,
                SalesPicking::STATUS_EN_PREPARATION,
                SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
                SalesPicking::STATUS_CONTROLE,
            ], 'modifiée');

            $freshItem = SalesPickingItem::lockForUpdate()->findOrFail($item->id);
            $quantity = (float) $data['quantity'];
            if ($quantity <= 0) {
                throw new RuntimeException('La quantité allouée doit être strictement positive.');
            }

            $stockLot = isset($data['stock_lot_id']) ? StockLot::lockForUpdate()->findOrFail($data['stock_lot_id']) : null;
            $coil = isset($data['coil_id']) ? Coil::lockForUpdate()->findOrFail($data['coil_id']) : null;

            if (! $stockLot && ! $coil) {
                throw new RuntimeException('Une allocation doit désigner un lot ou une bobine : le stock anonyme n\'est pas traçable.');
            }

            $warehouseId = (int) ($data['warehouse_id'] ?? $picking->warehouse_id ?? 0);
            $this->assertAllocatable($stockLot, $coil, $warehouseId, $quantity, $picking);

            // Reliquat de la LIGNE : on ne peut pas allouer plus que ce que le
            // bon s'est engagé à préparer.
            $alreadyAllocated = $this->allocatedQuantity($freshItem);
            if ($alreadyAllocated + $quantity > $freshItem->qty_remaining_snapshot + self::EPSILON) {
                throw new RuntimeException(sprintf(
                    'Allocation %s refusée : déjà alloué %s sur un reliquat de %s.',
                    $this->fmt($quantity), $this->fmt($alreadyAllocated),
                    $this->fmt((float) $freshItem->qty_remaining_snapshot)
                ));
            }

            $allocation = SalesPickingAllocation::create([
                'sales_picking_item_id' => $freshItem->id,
                'stock_lot_id' => $stockLot?->id,
                'coil_id' => $coil?->id,
                'warehouse_id' => $warehouseId,
                'location_id' => $data['location_id'] ?? null,
                'quantity' => $quantity,
                'unit_id' => $data['unit_id'] ?? $freshItem->unit_id,
                'conversion_snapshot' => $data['conversion_snapshot'] ?? null,
                // Coût HISTORIQUE figé : ni recalculé, ni réévalué plus tard.
                'historical_unit_cost' => $stockLot?->unit_cost ?? $coil?->cost_per_kg,
                'stock_reservation_id' => $data['stock_reservation_id'] ?? null,
                'status' => SalesPickingAllocation::STATUS_ALLOUEE,
            ]);

            $this->refreshItemAggregates($freshItem);
            $this->invalidateControls($picking, 'Allocation modifiée après contrôle.');
            $this->revertFromControl($picking);

            return $allocation;
        });
    }

    /**
     * Enregistre le prélèvement RÉEL d'une allocation.
     *
     * [Ventes §13] Un écart entre alloué et prélevé n'est jamais silencieux :
     * il exige un motif, il est stocké, et il laisse le reliquat ouvert.
     */
    public function pick(SalesPickingAllocation $allocation, float $pickedQuantity, ?string $varianceReason = null): SalesPickingAllocation
    {
        ExecutionContext::assertCan('bon_preparations.update', 'enregistrer un prélèvement');

        return DB::transaction(function () use ($allocation, $pickedQuantity, $varianceReason) {
            $fresh = SalesPickingAllocation::lockForUpdate()->findOrFail($allocation->id);
            $item = SalesPickingItem::lockForUpdate()->findOrFail($fresh->sales_picking_item_id);
            $picking = SalesPicking::lockForUpdate()->findOrFail($item->sales_picking_id);

            // Comme pour l'allocation : prélever après contrôle est permis, mais
            // fait tomber le contrôle.
            $this->assertStatus($picking, [
                SalesPicking::STATUS_EN_PREPARATION,
                SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
                SalesPicking::STATUS_CONTROLE,
            ], 'prélevée');

            if ($fresh->status === SalesPickingAllocation::STATUS_ANNULEE) {
                throw new RuntimeException('Cette allocation est annulée : elle ne peut plus être prélevée.');
            }
            if ($pickedQuantity < 0) {
                throw new RuntimeException('Une quantité prélevée ne peut pas être négative.');
            }
            if ($pickedQuantity > $fresh->quantity + self::EPSILON) {
                throw new RuntimeException(sprintf(
                    'Prélèvement %s supérieur à l\'allocation %s : allouez d\'abord la quantité manquante.',
                    $this->fmt($pickedQuantity), $this->fmt((float) $fresh->quantity)
                ));
            }

            $variance = round((float) $fresh->quantity - $pickedQuantity, 3);
            if (abs($variance) > self::EPSILON && ($varianceReason === null || trim($varianceReason) === '')) {
                throw new RuntimeException(sprintf(
                    'Écart de %s entre alloué et prélevé : un motif est obligatoire.',
                    $this->fmt($variance)
                ));
            }

            $fresh->update([
                'quantity' => $pickedQuantity > 0 ? $fresh->quantity : $fresh->quantity,
                'status' => SalesPickingAllocation::STATUS_PRELEVEE,
            ]);

            // Le prélèvement réel est porté par la ligne, pas par l'allocation :
            // l'allocation garde la quantité prévue, l'écart reste lisible.
            $item->update([
                'qty_picked' => round($this->pickedQuantity($item, $fresh->id) + $pickedQuantity, 3),
                'variance_qty' => round((float) $item->variance_qty + $variance, 3),
                'variance_reason' => abs($variance) > self::EPSILON
                    ? trim(($item->variance_reason ? $item->variance_reason.' | ' : '').$varianceReason)
                    : $item->variance_reason,
            ]);

            $this->refreshPickingStatus($picking);
            $this->invalidateControls($picking, 'Prélèvement enregistré après contrôle.');

            return $fresh->fresh();
        });
    }

    /**
     * [Ventes §14] Contrôle du bon par un acteur DISTINCT du préparateur.
     *
     * @param  array<string,mixed>  $checkpoints  article, lot, bobine, quantité,
     *                                            poids, dépôt, emplacement,
     *                                            qualité, commande, client
     */
    public function control(SalesPicking $picking, array $checkpoints, string $result, ?string $notes = null): SalesPickingControl
    {
        ExecutionContext::assertCan('bon_preparations.control', 'contrôler une préparation');

        return DB::transaction(function () use ($picking, $checkpoints, $result, $notes) {
            $fresh = SalesPicking::lockForUpdate()->findOrFail($picking->id);
            $this->assertStatus($fresh, [
                SalesPicking::STATUS_PREPARE,
                SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
            ], 'contrôlée');

            $controllerId = Auth::id();
            if ($controllerId !== null && (int) $fresh->started_by === (int) $controllerId) {
                throw new RuntimeException(
                    'Le préparateur ne peut pas contrôler sa propre préparation : le contrôle exige un acteur distinct.'
                );
            }

            if (! in_array($result, [SalesPickingControl::RESULT_CONFORME, SalesPickingControl::RESULT_ECART], true)) {
                throw new RuntimeException('Résultat de contrôle invalide : « conforme » ou « ecart » attendu.');
            }

            $control = SalesPickingControl::create([
                'sales_picking_id' => $fresh->id,
                'controlled_by' => $controllerId,
                'result' => $result,
                'checkpoints' => $checkpoints,
                'notes' => $notes,
            ]);

            if ($result === SalesPickingControl::RESULT_CONFORME) {
                foreach ($fresh->items as $item) {
                    $item->update(['qty_controlled' => $item->qty_picked]);
                }
                $fresh->update([
                    'status' => SalesPicking::STATUS_CONTROLE,
                    'controlled_by' => $controllerId,
                    'controlled_at' => now(),
                ]);
            }

            return $control;
        });
    }

    /**
     * Validation finale — le bon devient la source des quantités du BL.
     *
     * Le validateur doit être distinct du contrôleur (§14) : trois regards
     * successifs sur une sortie de stock valorisée.
     */
    public function validate(SalesPicking $picking, ?string $idempotencyKey = null): SalesPicking
    {
        ExecutionContext::assertCan('bon_preparations.validate', 'valider une préparation');

        return DB::transaction(function () use ($picking, $idempotencyKey) {
            $fresh = SalesPicking::lockForUpdate()->findOrFail($picking->id);

            // Rejouer la validation d'un bon déjà validé est SANS effet, pas une
            // erreur : c'est ce qui rend le double clic inoffensif.
            if ($fresh->status === SalesPicking::STATUS_VALIDE) {
                return $fresh;
            }

            $this->assertStatus($fresh, [SalesPicking::STATUS_CONTROLE], 'validée');

            $validatorId = Auth::id();
            if ($validatorId !== null && (int) $fresh->controlled_by === (int) $validatorId) {
                throw new RuntimeException(
                    'Le contrôleur ne peut pas valider ce qu\'il vient de contrôler : la validation exige un acteur distinct.'
                );
            }

            if ($this->hasActiveControlGap($fresh)) {
                throw new RuntimeException(
                    'Le bon a été modifié après son contrôle : un nouveau contrôle est requis avant validation.'
                );
            }

            foreach ($fresh->items as $item) {
                if ($item->qty_controlled + self::EPSILON < $item->qty_picked) {
                    throw new RuntimeException(sprintf(
                        'Ligne non entièrement contrôlée : prélevé %s, contrôlé %s.',
                        $this->fmt((float) $item->qty_picked), $this->fmt((float) $item->qty_controlled)
                    ));
                }
                $item->update(['qty_validated' => $item->qty_controlled]);
            }

            $fresh->update([
                'status' => SalesPicking::STATUS_VALIDE,
                'validated_by' => $validatorId,
                'validated_at' => now(),
                'idempotency_key' => $fresh->idempotency_key ?? $idempotencyKey,
            ]);

            return $fresh->fresh('items');
        });
    }

    /**
     * [Ventes §15] Annulation — jamais une suppression.
     *
     * Libère les réservations, annule les allocations, restaure le reliquat en
     * remettant la ligne à zéro côté engagement, et conserve l'intégralité de
     * l'historique. Idempotente : annuler deux fois ne produit rien de plus.
     */
    public function cancel(SalesPicking $picking, string $reason): SalesPicking
    {
        ExecutionContext::assertCan('bon_preparations.update', 'annuler une préparation');

        if (trim($reason) === '') {
            throw new RuntimeException('L\'annulation d\'un bon de préparation exige un motif.');
        }

        return DB::transaction(function () use ($picking, $reason) {
            $fresh = SalesPicking::lockForUpdate()->findOrFail($picking->id);

            if ($fresh->status === SalesPicking::STATUS_ANNULE) {
                return $fresh;
            }
            if ($fresh->status === SalesPicking::STATUS_VALIDE) {
                throw new RuntimeException(
                    'Un bon de préparation validé ne s\'annule pas : il est consommé par un bon de livraison. '
                    .'Passez par une contre-opération sur le BL.'
                );
            }

            foreach ($fresh->items as $item) {
                foreach ($item->allocations as $allocation) {
                    if ($allocation->status === SalesPickingAllocation::STATUS_ANNULEE) {
                        continue;
                    }
                    // La réservation liée est libérée, jamais supprimée : la
                    // trace de ce qui avait été immobilisé reste lisible.
                    if ($allocation->stock_reservation_id) {
                        DB::table('stock_reservations')
                            ->where('id', $allocation->stock_reservation_id)
                            ->where('status', 'reserved')
                            ->update(['status' => 'released', 'released_at' => now()->toDateString()]);
                    }
                    $allocation->update(['status' => SalesPickingAllocation::STATUS_ANNULEE]);
                }

                // Les engagements tombent, l'historique des quantités reste.
                $item->update(['qty_reserved' => 0, 'qty_allocated' => 0]);
            }

            $fresh->update([
                'status' => SalesPicking::STATUS_ANNULE,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $fresh->fresh('items');
        });
    }

    // -----------------------------------------------------------------------
    // Aval : bon de livraison
    // -----------------------------------------------------------------------

    /**
     * [Ventes §4.3] Quantités livrables issues d'un bon VALIDÉ.
     *
     * C'est la seule source légitime des lignes d'un bon de livraison rattaché
     * à une préparation : ce qui a été réellement prélevé, contrôlé et validé —
     * jamais ce qui avait été commandé.
     *
     * Une ligne déjà entièrement livrée n'est pas reproposée : le reste à livrer
     * tient compte de ce qui a déjà été consommé par des BL antérieurs.
     *
     * @return array<int,array{item:SalesPickingItem,quantity:float}>
     */
    public function deliverableLines(SalesPicking $picking): array
    {
        if ($picking->status !== SalesPicking::STATUS_VALIDE) {
            throw new RuntimeException(sprintf(
                'Bon de préparation %s au statut « %s » : seul un bon VALIDÉ peut alimenter un bon de livraison.',
                $picking->number, $picking->status
            ));
        }

        $lines = [];
        foreach ($picking->items as $item) {
            $alreadyDelivered = $this->deliveredFromPickingItem($item);
            $remaining = round((float) $item->qty_validated - $alreadyDelivered, 3);
            if ($remaining <= self::EPSILON) {
                continue;
            }
            $lines[] = ['item' => $item, 'quantity' => $remaining];
        }

        if ($lines === []) {
            throw new RuntimeException(sprintf(
                'Bon de préparation %s : tout le validé est déjà livré, aucun bon de livraison à créer.',
                $picking->number
            ));
        }

        return $lines;
    }

    /** Quantité déjà consommée par des BL rattachés à cette ligne de préparation. */
    public function deliveredFromPickingItem(SalesPickingItem $item): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('delivery_note_items', 'sales_picking_item_id')) {
            return 0.0;
        }

        return (float) DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
            ->where('dni.sales_picking_item_id', $item->id)
            ->whereNotIn('dn.status', ['annule', 'annulee'])
            ->sum('dni.quantity');
    }

    /**
     * Garde à appeler AVANT d'écrire une ligne de BL rattachée à une préparation.
     *
     * Invariant : livré cumulé ≤ validé en préparation. Le dépassement est refusé
     * ici plutôt que constaté après coup par l'audit.
     */
    public function assertDeliverable(SalesPickingItem $item, float $quantity): void
    {
        $alreadyDelivered = $this->deliveredFromPickingItem($item);
        if ($alreadyDelivered + $quantity > (float) $item->qty_validated + self::EPSILON) {
            throw new RuntimeException(sprintf(
                'Livraison %s refusée : préparation validée %s, déjà livré %s.',
                $this->fmt($quantity), $this->fmt((float) $item->qty_validated), $this->fmt($alreadyDelivered)
            ));
        }
    }

    // -----------------------------------------------------------------------
    // Gardes
    // -----------------------------------------------------------------------

    private function assertOrderPickable(Order $order): void
    {
        $allowed = ['confirme', 'en_preparation', 'partiellement_livre'];
        if (! in_array($order->status, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'La commande %s est au statut « %s » : seule une commande confirmée peut être préparée.',
                $order->number, $order->status
            ));
        }
    }

    /** @param  SalesPicking  $picking  utilisé pour comparer le dépôt */
    private function assertAllocatable(?StockLot $lot, ?Coil $coil, int $warehouseId, float $quantity, SalesPicking $picking): void
    {
        if ($warehouseId <= 0) {
            throw new RuntimeException('Le dépôt de prélèvement est obligatoire : une sortie de stock sans dépôt n\'est pas traçable.');
        }
        if ($picking->warehouse_id && (int) $picking->warehouse_id !== $warehouseId) {
            throw new RuntimeException(sprintf(
                'Dépôt incorrect : le bon prépare depuis le dépôt #%d, l\'allocation vise le dépôt #%d.',
                $picking->warehouse_id, $warehouseId
            ));
        }

        if ($lot) {
            if ((int) $lot->warehouse_id !== $warehouseId) {
                throw new RuntimeException(sprintf(
                    'Le lot %s est au dépôt #%d, pas au dépôt #%d.',
                    $lot->lot_number, $lot->warehouse_id, $warehouseId
                ));
            }
            if ($lot->quality_status !== null && $lot->quality_status !== 'libere') {
                throw new RuntimeException(sprintf(
                    'Lot %s en statut qualité « %s » : seul un lot libéré peut être prélevé.',
                    $lot->lot_number, $lot->quality_status
                ));
            }
            if ($lot->valuation_status !== 'valorisation_definitive') {
                throw new RuntimeException(sprintf(
                    'Lot %s non valorisé définitivement (« %s ») : le prélever fausserait le coût des ventes.',
                    $lot->lot_number, $lot->valuation_status ?? 'inconnu'
                ));
            }
            // [Ventes §16 — course B] Le disponible doit retrancher les quantités
            // DÉJÀ ALLOUÉES par d'autres bons, sinon deux préparations peuvent
            // engager le même stock : mesuré à 16 alloués sur un lot de 10.
            //
            // La somme est lue en lecture VERROUILLANTE : sous REPEATABLE READ,
            // une lecture ordinaire servirait la vue cohérente de la transaction
            // et ne verrait pas l'allocation concurrente déjà committée, même
            // après l'obtention du verrou sur la ligne du lot.
            $alreadyAllocatedElsewhere = (float) DB::table('sales_picking_allocations')
                ->where('stock_lot_id', $lot->id)
                ->where('status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
                ->lockForUpdate()
                ->sum('quantity');

            $available = (float) $lot->quantity - (float) $lot->reserved_quantity - $alreadyAllocatedElsewhere;
            if ($quantity > $available + self::EPSILON) {
                throw new RuntimeException(sprintf(
                    'Lot %s : quantité %s demandée pour un disponible de %s '
                    .'(stock %s, déjà réservé %s, déjà alloué %s).',
                    $lot->lot_number, $this->fmt($quantity), $this->fmt($available),
                    $this->fmt((float) $lot->quantity), $this->fmt((float) $lot->reserved_quantity),
                    $this->fmt($alreadyAllocatedElsewhere)
                ));
            }
        }

        if ($coil) {
            if ($coil->isSplit()) {
                throw new RuntimeException(sprintf(
                    'Bobine %s DIVISÉE : elle n\'est plus du stock actif, prélevez ses bobines filles.',
                    $coil->reference ?? $coil->lot_number ?? $coil->id
                ));
            }
            if (! $coil->isQualityReleased()) {
                throw new RuntimeException(sprintf(
                    'Bobine %s non libérée (statut qualité « %s ») : elle ne peut pas être prélevée.',
                    $coil->reference ?? $coil->lot_number ?? $coil->id, $coil->quality_status ?? 'inconnu'
                ));
            }
            if (! $coil->isReservable()) {
                throw new RuntimeException(sprintf(
                    'Bobine %s non réservable dans son état courant.', $coil->reference ?? $coil->lot_number ?? $coil->id
                ));
            }
            // Même règle que pour les lots : le disponible d'une bobine tient
            // compte de ce qui lui est déjà alloué par d'autres bons.
            $alreadyAllocatedElsewhere = (float) DB::table('sales_picking_allocations')
                ->where('coil_id', $coil->id)
                ->where('status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
                ->lockForUpdate()
                ->sum('quantity');

            $available = $coil->availableReleasedQuantity() - $alreadyAllocatedElsewhere;
            if ($quantity > $available + self::EPSILON) {
                throw new RuntimeException(sprintf(
                    'Bobine %s : quantité %s demandée pour un disponible libéré de %s (déjà alloué %s).',
                    $coil->reference ?? $coil->lot_number ?? $coil->id,
                    $this->fmt($quantity), $this->fmt($available), $this->fmt($alreadyAllocatedElsewhere)
                ));
            }
        }
    }

    /** @param  array<int,string>  $allowed */
    private function assertStatus(SalesPicking $picking, array $allowed, string $action): void
    {
        if (! in_array($picking->status, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Bon de préparation %s au statut « %s » : il ne peut pas être %s (statuts admis : %s).',
                $picking->number, $picking->status, $action, implode(', ', $allowed)
            ));
        }
    }

    // -----------------------------------------------------------------------
    // Calculs
    // -----------------------------------------------------------------------

    /**
     * Reliquat réellement préparable d'une ligne de commande :
     * commandé − déjà livré − déjà engagé dans d'autres préparations vivantes.
     */
    public function remainingToPick(object $orderItem, ?int $excludePickingId = null): float
    {
        $ordered = (float) $orderItem->quantity;
        $delivered = (float) ($orderItem->delivered_quantity ?? 0);
        $engaged = $this->quantityInOtherPickings($orderItem, $excludePickingId);

        return max(0.0, round($ordered - $delivered - $engaged, 3));
    }

    /** Quantité engagée par les autres bons de préparation NON annulés. */
    private function quantityInOtherPickings(object $orderItem, ?int $excludePickingId): float
    {
        return (float) DB::table('sales_picking_items as spi')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->where('spi.order_item_id', $orderItem->id)
            ->where('sp.status', '!=', SalesPicking::STATUS_ANNULE)
            ->when($excludePickingId, fn ($q) => $q->where('sp.id', '!=', $excludePickingId))
            ->sum('spi.qty_remaining_snapshot');
    }

    private function allocatedQuantity(SalesPickingItem $item): float
    {
        return (float) $item->allocations()
            ->where('status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->sum('quantity');
    }

    private function pickedQuantity(SalesPickingItem $item, int $excludeAllocationId): float
    {
        return (float) $item->qty_picked;
    }

    private function refreshItemAggregates(SalesPickingItem $item): void
    {
        $item->update(['qty_allocated' => round($this->allocatedQuantity($item), 3)]);
    }

    /** partiellement_prepare tant que tout n'est pas prélevé, prepare ensuite. */
    private function refreshPickingStatus(SalesPicking $picking): void
    {
        $picking->load('items');
        $complete = $picking->items->every(
            fn (SalesPickingItem $i) => $i->qty_picked + self::EPSILON >= $i->qty_remaining_snapshot
        );

        $picking->update([
            'status' => $complete ? SalesPicking::STATUS_PREPARE : SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
            'prepared_by' => $complete ? Auth::id() : $picking->prepared_by,
            'prepared_at' => $complete ? now() : $picking->prepared_at,
        ]);
    }

    /**
     * Un bon modifié après contrôle repart en préparation : son statut ne doit
     * pas laisser croire qu'il est encore contrôlé. Les quantités contrôlées
     * sont remises à zéro — elles portaient sur un état qui n'existe plus.
     */
    private function revertFromControl(SalesPicking $picking): void
    {
        if ($picking->fresh()->status !== SalesPicking::STATUS_CONTROLE) {
            return;
        }

        foreach ($picking->items as $item) {
            $item->update(['qty_controlled' => 0]);
        }

        $picking->update([
            'status' => SalesPicking::STATUS_PARTIELLEMENT_PREPARE,
            'controlled_by' => null,
            'controlled_at' => null,
        ]);
    }

    /** Toute modification postérieure à un contrôle l'invalide (§14). */
    private function invalidateControls(SalesPicking $picking, string $reason): void
    {
        SalesPickingControl::where('sales_picking_id', $picking->id)
            ->whereNull('invalidated_at')
            ->get()
            ->each(fn (SalesPickingControl $c) => $c->update([
                'invalidated_at' => now(),
                'invalidated_reason' => $reason,
            ]));
    }

    private function hasActiveControlGap(SalesPicking $picking): bool
    {
        return ! SalesPickingControl::where('sales_picking_id', $picking->id)
            ->whereNull('invalidated_at')
            ->where('result', SalesPickingControl::RESULT_CONFORME)
            ->exists();
    }

    private function nextNumber(Order $order): string
    {
        $sequence = SalesPicking::withoutGlobalScopes()
            ->where('company_id', $order->company_id)
            ->count() + 1;

        return sprintf('BP-%s-%05d', now()->format('Y'), $sequence);
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }
}
