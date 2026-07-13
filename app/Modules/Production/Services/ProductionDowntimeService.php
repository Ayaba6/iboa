<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionDowntime;
use App\Modules\Production\Models\ProductionOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [PRO Temps d'arrêt] Déclaration et statistiques des arrêts de production.
 */
class ProductionDowntimeService
{
    /**
     * Déclare un arrêt. Si $data['ended_at'] est fourni, l'arrêt est clôturé et
     * sa durée calculée ; sinon il reste ouvert (en cours).
     *
     * La machine est déduite de l'OF (via sa ligne) si non fournie.
     */
    public function declare(array $data): ProductionDowntime
    {
        return DB::transaction(function () use ($data) {
            $started = Carbon::parse($data['started_at'] ?? now());
            $ended   = !empty($data['ended_at']) ? Carbon::parse($data['ended_at']) : null;

            if ($ended && $ended->lt($started)) {
                throw new \RuntimeException('La fin de l\'arrêt ne peut pas précéder son début.');
            }

            $machineId = $data['machine_id'] ?? $this->resolveMachineFromOrder($data['production_order_id'] ?? null);

            return ProductionDowntime::create([
                'company_id'          => currentCompany()->id,
                'production_order_id' => $data['production_order_id'] ?? null,
                'machine_id'          => $machineId,
                'work_center_id'      => $data['work_center_id'] ?? null,
                'category'            => in_array($data['category'] ?? '', array_keys(ProductionDowntime::CATEGORIES), true)
                    ? $data['category'] : 'non_planifie',
                'reason'              => in_array($data['reason'] ?? '', array_keys(ProductionDowntime::REASONS), true)
                    ? $data['reason'] : 'autre',
                'description'         => $data['description'] ?? null,
                'started_at'          => $started,
                'ended_at'            => $ended,
                'duration_minutes'    => $ended ? (int) abs($started->diffInMinutes($ended)) : null,
                'declared_by'         => Auth::id(),
            ]);
        });
    }

    /** Clôture un arrêt en cours et calcule sa durée. */
    public function close(ProductionDowntime $downtime, ?string $endedAt = null): ProductionDowntime
    {
        if (! $downtime->isOngoing()) {
            throw new \RuntimeException('Cet arrêt est déjà clôturé.');
        }
        $ended = $endedAt ? Carbon::parse($endedAt) : now();
        if ($ended->lt($downtime->started_at)) {
            throw new \RuntimeException('La fin de l\'arrêt ne peut pas précéder son début.');
        }

        $downtime->update([
            'ended_at'         => $ended,
            'duration_minutes' => (int) abs($downtime->started_at->diffInMinutes($ended)),
        ]);

        return $downtime->fresh();
    }

    /** Total (minutes + nombre) des arrêts par machine sur N jours. */
    public function statsByMachine(int $days = 30): \Illuminate\Support\Collection
    {
        return ProductionDowntime::query()
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('duration_minutes')
            ->selectRaw('machine_id, SUM(duration_minutes) minutes, COUNT(*) n')
            ->groupBy('machine_id')
            ->get()
            ->keyBy('machine_id');
    }

    /** Répartition des minutes d'arrêt par cause sur N jours. */
    public function statsByReason(int $days = 30): array
    {
        $rows = ProductionDowntime::query()
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('duration_minutes')
            ->selectRaw('reason, SUM(duration_minutes) minutes, COUNT(*) n')
            ->groupBy('reason')
            ->pluck('minutes', 'reason');

        $out = [];
        foreach (ProductionDowntime::REASONS as $k => $label) {
            if (($rows[$k] ?? 0) > 0) {
                $out[] = ['reason' => $k, 'label' => $label, 'minutes' => (int) $rows[$k]];
            }
        }
        usort($out, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);

        return $out;
    }

    private function resolveMachineFromOrder(?int $orderId): ?int
    {
        if (! $orderId) {
            return null;
        }
        $order = ProductionOrder::with('productionLine')->find($orderId);

        return $order?->productionLine?->machine_id;
    }
}
