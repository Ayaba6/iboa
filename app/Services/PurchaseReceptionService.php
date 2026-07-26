<?php

namespace App\Services;

use App\Models\Reception;
use App\Services\Sync\Handlers\ReplayReceptionStockSync;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS — Réceptions] Service transactionnel CENTRAL du workflow de réception.
 *
 * Sort les règles métier du contrôleur (qui ne doit que valider la requête,
 * appeler le service et rendre la réponse). Centralise : verrouillage de la
 * commande/réception, persistance des quantités, entrées de stock (sync
 * idempotente), génération lots/bobines, mise à jour des agrégats de commande,
 * statut, événement post-commit.
 *
 * Étape 1 (ce commit) : REFACTOR à comportement constant — la logique est
 * déplacée telle quelle depuis ReceptionController::validateReception. Les
 * enrichissements (ventilation accepté/quarantaine/refusé, tolérances,
 * valorisation provisoire, annulation technique) arrivent dans les commits
 * suivants du lot Réceptions.
 */
class PurchaseReceptionService
{
    /**
     * Valide une réception : persiste les quantités reçues, synchronise le stock,
     * génère les lots/bobines éligibles, met à jour la commande liée.
     *
     * @param  array<int,array{received_quantity?:mixed,lot_number?:?string,expiry_date?:?string}>  $items  indexé par reception_item_id
     * @return array{0:int,1:int}  [mouvements créés, lignes ignorées]
     *
     * @throws \RuntimeException
     */
    public function validate(Reception $reception, int $warehouseId, array $items): array
    {
        // [SEC-PHASE2 §2] Maker-checker : celui qui a saisi la réception ne la
        // valide pas — l'entrée de stock est certifiée par un second regard.
        app(MakerCheckerService::class)->assert(
            $reception->created_by, 'reception.validate', "la réception {$reception->number}", $reception
        );

        $movementsCreated = 0;
        $linesSkipped     = 0;

        DB::transaction(function () use ($reception, $warehouseId, $items, &$movementsCreated, &$linesSkipped) {
            // Verrou : empêche la double-validation concurrente (TOCTOU).
            $reception = Reception::lockForUpdate()->findOrFail($reception->id);
            if ($reception->status !== 'brouillon') {
                throw new \RuntimeException('Seules les réceptions en brouillon peuvent être validées.');
            }

            // Passe 1 — persister les quantités VENTILÉES + agrégats BC.
            foreach ($items as $itemId => $itemData) {
                $item = $reception->items()->find($itemId);
                if (! $item) {
                    continue;
                }

                $receivedQty = (float) ($itemData['received_quantity'] ?? 0);

                // [#1/#7] Ventilation accepté / quarantaine / refusé. Rétro-compatible :
                // sans ventilation explicite, tout le reçu est accepté (comportement
                // historique). L'invariant reçu = accepté + quarantaine + refusé est
                // imposé sinon.
                $hasBreakdown = array_key_exists('accepted_quantity', $itemData)
                    || array_key_exists('quarantine_quantity', $itemData)
                    || array_key_exists('refused_quantity', $itemData);

                if ($hasBreakdown) {
                    $accepted   = (float) ($itemData['accepted_quantity'] ?? 0);
                    $quarantine = (float) ($itemData['quarantine_quantity'] ?? 0);
                    $refused    = (float) ($itemData['refused_quantity'] ?? 0);
                    if (abs(($accepted + $quarantine + $refused) - $receivedQty) > 0.0001) {
                        throw new \RuntimeException(sprintf(
                            'Ventilation incohérente ligne %s : accepté %s + quarantaine %s + refusé %s ≠ reçu %s.',
                            $item->id, $accepted, $quarantine, $refused, $receivedQty
                        ));
                    }
                } else {
                    $accepted = $receivedQty;
                    $quarantine = 0.0;
                    $refused = 0.0;
                }

                $item->update([
                    'received_quantity'   => $receivedQty,
                    'accepted_quantity'   => $accepted,
                    'quarantine_quantity' => $quarantine,
                    'rejected_quantity'   => $refused,
                    'disposition_origin'  => 'saisie',
                    'quality_status'      => $quarantine > 0 ? 'en_attente' : ($refused > 0 && $accepted <= 0 ? 'rejete' : 'accepte'),
                    'lot_number'          => $itemData['lot_number']  ?? $item->lot_number,
                    'expiry_date'         => $itemData['expiry_date'] ?? $item->expiry_date,
                ]);

                if ($item->purchase_order_item_id) {
                    $poItem = $item->purchaseOrderItem;
                    if ($poItem) {
                        $totalReceived = $poItem->received_quantity + $receivedQty;
                        $totalAccepted = (float) $poItem->accepted_quantity + $accepted;
                        $poItem->update([
                            'received_quantity' => min($totalReceived, $poItem->quantity),
                            'accepted_quantity' => min($totalAccepted, $poItem->quantity),
                        ]);
                    }
                }
            }

            // Passe 2 — entrées stock depuis les quantités PERSISTÉES (journalisées,
            // idempotentes, relançables via sync_logs).
            $reception->update(['warehouse_id' => $warehouseId]);
            app(SyncOrchestrator::class)->run(
                sourceModule: 'achats',
                targetModule: 'stock',
                eventName: 'reception.validated',
                action: 'create_stock_entries',
                source: $reception,
                callback: function () use ($reception, &$movementsCreated, &$linesSkipped) {
                    [$movementsCreated, $linesSkipped] =
                        app(ReplayReceptionStockSync::class)($reception->fresh('items'));
                },
                payload: ['warehouse_id' => $warehouseId],
                handlerClass: ReplayReceptionStockSync::class,
            );

            $reception->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            // Génération automatique bobines/lots pour les articles à suivi (filtrée,
            // sans double entrée de stock — traçabilité pure sur ce chemin).
            try {
                app(\App\Modules\Production\Services\CoilReceptionService::class)
                    ->createFromReception($reception->fresh('items.product.itemCategory'), onlyTracked: true);
            } catch (\Illuminate\Validation\ValidationException) {
                // Déjà générées ou rien d'éligible — silencieux.
            }

            // Mise à jour du statut de la commande liée.
            $po = $reception->purchaseOrder;
            if ($po) {
                $po->load('items');
                $allReceived = $po->items->every(
                    fn ($i) => (float) $i->received_quantity >= (float) $i->quantity
                );
                $po->update(['status' => $allReceived ? 'recu' : 'partiellement_recu']);
            }

            DB::afterCommit(fn () => event(new \App\Events\ReceptionValidated($reception)));
        });

        return [$movementsCreated, $linesSkipped];
    }
}
