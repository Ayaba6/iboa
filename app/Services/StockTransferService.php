<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InventorySession;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [STOCK-PRO] Cycle de vie des transferts inter-dépôts.
 *
 *   ┌───────────┐  ship    ┌───────────┐  receive  ┌──────┐
 *   │ brouillon │ ───────▶ │ en_transit│ ────────▶ │ recu │
 *   └─────┬─────┘          └─────┬─────┘           └──────┘
 *         │                      │
 *         └────── cancel ────────┴─────────────▶  annule
 *
 * - ship   : décrémente le stock source, marque en_transit
 * - receive: incrémente le stock destination, accepte des écarts (received_quantity)
 * - cancel : si en_transit → reverse l'expédition (réintègre stock source)
 *
 * Toutes les opérations sont transactionnelles avec lockForUpdate sur le transfert
 * pour éviter les doubles-validations concurrentes.
 */
class StockTransferService
{
    public function __construct(
        private DocumentSequenceService $sequenceService,
    ) {}

    /**
     * Crée un transfert en brouillon.
     */
    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $company = currentCompany();

            if (($data['from_warehouse_id'] ?? null) === ($data['to_warehouse_id'] ?? null)) {
                throw new \RuntimeException('Le dépôt source et le dépôt destination ne peuvent pas être identiques.');
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            // [Maquette X3] champs entête additionnels (transport, priorité, contrôles…)
            $extra = array_intersect_key($data, array_flip([
                'transfer_time', 'type', 'priority', 'currency_code', 'reference',
                'source_document_date', 'responsible_id', 'carrier', 'transport_mode',
                'vehicle', 'driver', 'planned_date', 'planned_time', 'transport_cost',
                'grouping', 'packages_count', 'total_weight', 'total_volume',
                'controlled_by', 'controlled_at', 'validation_status',
            ]));

            $transfer = StockTransfer::create($extra + [
                'company_id'        => $company->id,
                'number'            => $this->sequenceService->nextNumber($company, 'transfert_stock')
                                       ?? $this->fallbackNumber($company),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'transfer_date'     => $data['transfer_date'] ?? now()->toDateString(),
                'status'            => 'brouillon',
                'reason'            => $data['reason'] ?? null,
                'notes'             => $data['notes']  ?? null,
                'created_by'        => Auth::id(),
            ]);

            $this->syncItems($transfer, $items);

            return $transfer->fresh('items');
        });
    }

    /**
     * Met à jour un transfert en brouillon.
     */
    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        if (!$transfer->canEdit()) {
            throw new \RuntimeException("Ce transfert est « {$transfer->statusLabel()} » — modification interdite.");
        }

        return DB::transaction(function () use ($transfer, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $transfer->update(array_filter([
                'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
                'to_warehouse_id'   => $data['to_warehouse_id']   ?? null,
                'transfer_date'     => $data['transfer_date']     ?? null,
                'reason'            => $data['reason']            ?? null,
                'notes'             => $data['notes']             ?? null,
            ], fn($v) => $v !== null));

            if ($items !== null) {
                $transfer->items()->delete();
                $this->syncItems($transfer, $items);
            }

            return $transfer->fresh('items');
        });
    }

    /**
     * Étape 1 : Expédier — décrémente le stock source, statut en_transit.
     */
    public function ship(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            // Lock pour éviter double-expédition concurrente
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canShip()) {
                throw new \RuntimeException("Ce transfert ne peut pas être expédié (statut actuel : {$transfer->statusLabel()}).");
            }
            if ($transfer->items->isEmpty()) {
                throw new \RuntimeException('Impossible d\'expédier un transfert sans aucune ligne.');
            }

            // [CDC §8.5] Même garde-fou que StockService::recordMovement() : pas
            // d'expédition depuis un dépôt en cours d'inventaire.
            if (InventorySession::where('warehouse_id', $transfer->from_warehouse_id)->where('status', 'en_cours')->exists()) {
                throw new \RuntimeException('Expédition bloquée : un inventaire est en cours sur le dépôt source.');
            }

            // Vérifie stock dispo en amont — meilleur message d'erreur
            foreach ($transfer->items as $item) {
                $available = $this->availableQty($item->product_id, $transfer->from_warehouse_id);
                if ((float) $available < (float) $item->quantity) {
                    throw new \RuntimeException(sprintf(
                        'Stock insuffisant pour %s au dépôt source : disponible %s, demandé %s.',
                        $item->product?->name ?? '#'.$item->product_id,
                        number_format($available, 2, ',', ' '),
                        number_format($item->quantity, 2, ',', ' ')
                    ));
                }
            }

            // Décrément source + mouvement type sortie
            foreach ($transfer->items as $item) {
                $stock = ProductStock::firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                    ['quantity' => 0, 'reserved_quantity' => 0]
                );

                // [FIX valorisation] Si aucun coût n'a été saisi sur la ligne (cas normal :
                // l'UI ne demande pas de coût), reprendre le CMP du dépôt SOURCE et le
                // persister sur la ligne — la réception l'utilisera pour valoriser l'entrée.
                // Sans cela, sortie + entrée partaient à 0 F et le CMP destination était
                // écrasé à 0 (stock sous-évalué).
                if ((float) ($item->unit_cost ?? 0) <= 0) {
                    $sourceCost = (float) ($stock->avg_cost ?? 0);
                    if ($sourceCost <= 0) {
                        $sourceCost = (float) ($item->product?->weighted_avg_cost ?? 0);
                    }
                    if ($sourceCost > 0) {
                        $item->update(['unit_cost' => $sourceCost]);
                        $item->refresh();
                    }
                }

                $stock->decrement('quantity', (float) $item->quantity);
                $stock->update(['last_movement_at' => now()]);

                StockMovement::create([
                    'product_id'        => $item->product_id,
                    'warehouse_id'      => $transfer->from_warehouse_id,
                    'type'              => 'sortie',
                    'reference_type'    => StockTransfer::class,
                    'reference_id'      => $transfer->id,
                    'quantity'          => $item->quantity,
                    'unit_cost'         => $item->unit_cost ?? 0,
                    'total_cost'        => ($item->unit_cost ?? 0) * (float) $item->quantity,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id'   => $transfer->to_warehouse_id,
                    'lot_number'        => $item->lot_number,
                    'serial_number'     => $item->serial_number,
                    'expiry_date'       => $item->expiry_date,
                    'notes'             => "Transfert {$transfer->number} — expédition",
                    'created_by'        => Auth::id(),
                    'occurred_at'       => now(),
                ]);
            }

            $transfer->update([
                'status'     => 'en_transit',
                'shipped_at' => now(),
                'shipped_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    /**
     * Étape 2 : Recevoir — incrémente le stock destination avec qty effective.
     *
     * $receivedQuantities = [item_id => qty_effective]. Si non fourni pour une ligne,
     * on prend la quantité d'expédition par défaut (transfert sans écart).
     */
    public function receive(StockTransfer $transfer, array $receivedQuantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedQuantities) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canReceive()) {
                throw new \RuntimeException("Ce transfert ne peut pas être réceptionné (statut : {$transfer->statusLabel()}).");
            }

            // [CDC §8.5] Même garde-fou : pas de réception sur un dépôt en cours d'inventaire.
            if (InventorySession::where('warehouse_id', $transfer->to_warehouse_id)->where('status', 'en_cours')->exists()) {
                throw new \RuntimeException('Réception bloquée : un inventaire est en cours sur le dépôt destination.');
            }

            foreach ($transfer->items as $item) {
                $received = isset($receivedQuantities[$item->id])
                    ? max(0, (float) $receivedQuantities[$item->id])
                    : (float) $item->quantity;

                if ($received > (float) $item->quantity) {
                    throw new \RuntimeException(sprintf(
                        'Ligne %s : quantité reçue (%s) supérieure à la quantité expédiée (%s).',
                        $item->product?->name ?? '#'.$item->product_id,
                        $received, $item->quantity
                    ));
                }

                $item->update(['received_quantity' => $received]);

                if ($received > 0) {
                    $stock = ProductStock::firstOrCreate(
                        ['product_id' => $item->product_id, 'warehouse_id' => $transfer->to_warehouse_id],
                        ['quantity' => 0, 'reserved_quantity' => 0]
                    );

                    // [FIX valorisation] Recalcul du CMP destination à la réception :
                    // nouveau CMP = (stock×CMP + reçu×coût transféré) / (stock + reçu).
                    // Sans cela, avg_cost destination restait à 0 pour un dépôt qui ne
                    // possédait pas l'article → stock sous-évalué en valorisation.
                    $unitCost = (float) ($item->unit_cost ?? 0);
                    if ($unitCost > 0) {
                        $oldQty  = (float) $stock->quantity;
                        $oldAvg  = (float) ($stock->avg_cost ?? 0);
                        $newAvg  = ($oldQty + $received) > 0
                            ? (($oldQty * $oldAvg) + ($received * $unitCost)) / ($oldQty + $received)
                            : $unitCost;
                        $stock->update(['avg_cost' => round($newAvg, 4)]);
                    }

                    $stock->increment('quantity', $received);
                    $stock->update(['last_movement_at' => now()]);

                    StockMovement::create([
                        'product_id'        => $item->product_id,
                        'warehouse_id'      => $transfer->to_warehouse_id,
                        'type'              => 'entree',
                        'reference_type'    => StockTransfer::class,
                        'reference_id'      => $transfer->id,
                        'quantity'          => $received,
                        'unit_cost'         => $item->unit_cost ?? 0,
                        'total_cost'        => ($item->unit_cost ?? 0) * $received,
                        'from_warehouse_id' => $transfer->from_warehouse_id,
                        'to_warehouse_id'   => $transfer->to_warehouse_id,
                        'lot_number'        => $item->lot_number,
                        'serial_number'     => $item->serial_number,
                        'expiry_date'       => $item->expiry_date,
                        'notes'             => "Transfert {$transfer->number} — réception"
                                            . ($received < (float) $item->quantity ? " (écart -" . ($item->quantity - $received) . ")" : ''),
                        'created_by'        => Auth::id(),
                        'occurred_at'       => now(),
                    ]);
                }
            }

            $transfer->update([
                'status'      => 'recu',
                'received_at' => now(),
                'received_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    /**
     * Annule un transfert. Si en_transit, ré-incrémente le stock source.
     */
    public function cancel(StockTransfer $transfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canCancel()) {
                throw new \RuntimeException("Ce transfert ne peut pas être annulé (statut : {$transfer->statusLabel()}).");
            }

            // Si on annule un transfert déjà expédié, on réintègre le stock source.
            if ($transfer->isInTransit()) {
                foreach ($transfer->items as $item) {
                    $stock = ProductStock::firstOrCreate(
                        ['product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                        ['quantity' => 0, 'reserved_quantity' => 0]
                    );
                    $stock->increment('quantity', (float) $item->quantity);
                    $stock->update(['last_movement_at' => now()]);

                    StockMovement::create([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $transfer->from_warehouse_id,
                        'type'           => 'entree',
                        'reference_type' => StockTransfer::class,
                        'reference_id'   => $transfer->id,
                        'quantity'       => $item->quantity,
                        'unit_cost'      => $item->unit_cost ?? 0,
                        'total_cost'     => ($item->unit_cost ?? 0) * (float) $item->quantity,
                        'notes'          => "Transfert {$transfer->number} — ANNULÉ : {$reason}",
                        'created_by'     => Auth::id(),
                        'occurred_at'    => now(),
                    ]);
                }
            }

            $transfer->update([
                'status'       => 'annule',
                'reason'       => $reason,
                'cancelled_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    public function delete(StockTransfer $transfer): bool
    {
        if (!$transfer->isDraft()) {
            throw new \RuntimeException("Seul un transfert en brouillon peut être supprimé. Utilisez l'annulation.");
        }
        return $transfer->delete();
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function syncItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $i => $line) {
            if (empty($line['product_id']) || (float) ($line['quantity'] ?? 0) <= 0) continue;

            $transfer->items()->create([
                'product_id'         => $line['product_id'],
                'quantity'           => $line['quantity'],
                'requested_quantity' => $line['requested_quantity'] ?? $line['quantity'],
                'weight'             => $line['weight'] ?? null,
                'volume'             => $line['volume'] ?? null,
                'unit_cost'     => $line['unit_cost']     ?? null,
                'lot_number'    => $line['lot_number']    ?? null,
                'serial_number' => $line['serial_number'] ?? null,
                'expiry_date'   => $line['expiry_date']   ?? null,
                'label'         => $line['label']         ?? null,
                'sort_order'    => $i,
            ]);
        }
    }

    private function availableQty(int $productId, int $warehouseId): float
    {
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        return $stock ? (float) $stock->quantity - (float) ($stock->reserved_quantity ?? 0) : 0;
    }

    private function fallbackNumber(Company $company): string
    {
        $prefix = 'TRF-' . now()->format('Y');
        $last = StockTransfer::where('company_id', $company->id)
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('number');
        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
