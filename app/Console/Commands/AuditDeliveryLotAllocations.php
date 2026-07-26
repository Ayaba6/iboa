<?php

namespace App\Console\Commands;

use App\Models\CreditNoteItemLotReturn;
use App\Models\DeliveryNoteItem;
use App\Models\DeliveryNoteItemLotAllocation;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AuditDeliveryLotAllocations extends Command
{
    protected $signature = 'a3:audit-delivery-allocations {--report= : Chemin du rapport JSON}';

    protected $description = 'Audit COGS, allocations de lots, mouvements et retours des BL';

    public function handle(): int
    {
        $anomalies = [];
        $warnings = [];
        $tolerance = 0.0001;

        $items = DeliveryNoteItem::query()
            ->with(['deliveryNote', 'lotAllocations.stockLot'])
            ->whereHas('deliveryNote', fn ($query) => $query->whereIn('status', ['valide', 'livre', 'annule']))
            ->get();

        foreach ($items as $item) {
            $active = $item->lotAllocations->whereNull('reversed_at');
            $reversed = $item->lotAllocations->whereNotNull('reversed_at');
            $isCancelled = $item->deliveryNote?->status === 'annule';

            if (! $isCancelled && abs((float) $active->sum('quantity') - abs((float) $item->quantity)) > $tolerance) {
                $legacyMovementExists = StockMovement::where('reference_type', 'delivery_note')
                    ->where('reference_id', $item->delivery_note_id)
                    ->where('product_id', $item->product_id)
                    ->where('type', 'sortie')
                    ->exists();
                if ($active->isEmpty() && $legacyMovementExists) {
                    $warnings[] = $this->issue('legacy_delivery_without_proven_allocation', $item, 'BL historique avec sortie stock mais sans allocation prouvée : aucun backfill fictif autorisé.');
                } else {
                    $anomalies[] = $this->issue('allocation_quantity_mismatch', $item, 'Somme allouée différente de la quantité livrée.');
                }
            }
            if ($isCancelled && $active->isNotEmpty()) {
                $anomalies[] = $this->issue('active_allocation_after_cancellation', $item, 'Allocation active après annulation du BL.');
            }

            foreach ($isCancelled ? $reversed : $active as $allocation) {
                if ((float) $allocation->quantity <= 0 || (float) $allocation->unit_cost_snapshot <= 0) {
                    $anomalies[] = $this->issue('invalid_snapshot', $item, 'Quantité ou coût historique figé nul.', $allocation->id);
                }
                if ((int) $allocation->warehouse_id !== (int) $item->deliveryNote?->warehouse_id) {
                    $anomalies[] = $this->issue('wrong_warehouse', $item, 'Allocation effectuée dans un dépôt différent du BL.', $allocation->id);
                }
                if (abs((float) $allocation->total_cost - round((float) $allocation->quantity * (float) $allocation->unit_cost_snapshot, 2)) > 0.01) {
                    $anomalies[] = $this->issue('cost_mismatch', $item, 'COGS figé différent de quantité × coût historique.', $allocation->id);
                }
                if (! $allocation->stock_movement_id || ! $allocation->stockMovement) {
                    $anomalies[] = $this->issue('allocation_without_movement', $item, 'Allocation sans mouvement de stock lié.', $allocation->id);
                }
            }
        }

        DeliveryNoteItemLotAllocation::query()->with('deliveryNoteItem.deliveryNote')->get()->each(function ($allocation) use (&$anomalies, $tolerance): void {
            $returned = (float) CreditNoteItemLotReturn::where('delivery_allocation_id', $allocation->id)->sum('quantity');
            if ($returned - (float) $allocation->quantity > $tolerance) {
                $anomalies[] = $this->issue('return_exceeds_delivery', $allocation->deliveryNoteItem, 'Quantité retournée supérieure à la quantité livrée.', $allocation->id);
            }
        });

        $linkedMovementIds = DeliveryNoteItemLotAllocation::whereNotNull('stock_movement_id')->pluck('stock_movement_id');
        StockMovement::query()->where('reference_type', 'delivery_note')->where('type', 'sortie')
            ->whereNotNull('stock_lot_id')
            ->whereNotIn('id', $linkedMovementIds)->get()->each(function ($movement) use (&$anomalies): void {
                $anomalies[] = ['code' => 'movement_without_allocation', 'movement_id' => $movement->id, 'delivery_note_id' => $movement->reference_id, 'message' => 'Mouvement de livraison sans allocation de lot.'];
            });

        $report = [
            'meta' => ['generated_at' => now()->toIso8601String(), 'git_sha' => $this->gitSha(), 'database' => config('database.connections.'.config('database.default').'.database')],
            'counts' => ['delivery_items' => $items->count(), 'allocations' => DeliveryNoteItemLotAllocation::count(), 'returns' => CreditNoteItemLotReturn::count(), 'warnings' => count($warnings), 'anomalies' => count($anomalies)],
            'warnings' => $warnings,
            'anomalies' => $anomalies,
        ];
        if ($path = $this->option('report')) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        foreach ($anomalies as $anomaly) {
            $this->error(($anomaly['code'] ?? 'anomaly').' | '.($anomaly['message'] ?? ''));
        }
        foreach ($warnings as $warning) {
            $this->warn(($warning['code'] ?? 'warning').' | '.($warning['message'] ?? ''));
        }
        $this->line(sprintf('Lignes BL: %d | Allocations: %d | Retours: %d | Avertissements: %d | Anomalies: %d', $report['counts']['delivery_items'], $report['counts']['allocations'], $report['counts']['returns'], count($warnings), count($anomalies)));

        return $anomalies === [] ? self::SUCCESS : self::FAILURE;
    }

    private function issue(string $code, ?DeliveryNoteItem $item, string $message, ?int $allocationId = null): array
    {
        return ['code' => $code, 'delivery_note_id' => $item?->delivery_note_id, 'delivery_note_item_id' => $item?->id, 'allocation_id' => $allocationId, 'message' => $message];
    }

    private function gitSha(): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}
