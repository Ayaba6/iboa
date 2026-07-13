<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionDowntime;
use App\Modules\Production\Models\ProductionOrderOperation;
use App\Modules\Production\Models\WorkCenter;

/**
 * [PRODUCTION] Plan de charge — capacité vs charge planifiée par centre de travail.
 *
 * Charge = somme des temps prévus des opérations (Work Orders) non terminées,
 * sur des OF actifs (lancés / en cours), rattachées au centre.
 * Capacité = capacité journalière × rendement × horizon (jours).
 */
class PlanningService
{
    public function loadByWorkCenter(int $horizonDays = 7): array
    {
        $centers = WorkCenter::where('is_active', true)->orderBy('name')->get();

        // Charge prévue (minutes) par centre, opérations non terminées d'OF actifs
        $load = ProductionOrderOperation::query()
            ->whereNotNull('work_center_id')
            ->where('status', '!=', 'done')
            ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))
            ->selectRaw('work_center_id, SUM(planned_minutes) m, COUNT(*) n')
            ->groupBy('work_center_id')
            ->get()->keyBy('work_center_id');

        $rows = $centers->map(function ($wc) use ($load, $horizonDays) {
            $planned   = (float) ($load[$wc->id]->m ?? 0);
            $opsCount  = (int) ($load[$wc->id]->n ?? 0);
            $capacity  = (float) $wc->capacity_hours_per_day * 60 * ((float) $wc->efficiency_rate / 100) * $horizonDays;
            $occupation = $capacity > 0 ? round($planned / $capacity * 100, 1) : ($planned > 0 ? 100 : 0);

            $status = match (true) {
                $occupation > 100 => 'surcharge',
                $occupation >= 80 => 'charge',
                $planned <= 0     => 'libre',
                default           => 'ok',
            };

            return [
                'id'         => $wc->id,
                'name'       => $wc->name,
                'code'       => $wc->code,
                'ops'        => $opsCount,
                'planned_h'  => round($planned / 60, 1),
                'capacity_h' => round($capacity / 60, 1),
                'occupation' => $occupation,
                'status'     => $status,
            ];
        })->values();

        return [
            'horizon'    => $horizonDays,
            'rows'       => $rows,
            'overloaded' => $rows->where('status', 'surcharge')->count(),
            'total_planned_h' => round($rows->sum('planned_h'), 1),
            'total_capacity_h' => round($rows->sum('capacity_h'), 1),
        ];
    }

    /**
     * [PRO Plan de charge] Charge agrégée par MACHINE (via les centres de travail
     * rattachés à chaque machine). La disponibilité tient compte des arrêts déclarés
     * sur l'horizon (capacité nette = capacité brute − temps d'arrêt).
     */
    public function loadByMachine(int $horizonDays = 7): array
    {
        $centers = WorkCenter::where('is_active', true)->whereNotNull('machine_id')
            ->with('machine:id,name,code')->get();

        $load     = $this->plannedMinutesByWorkCenter();
        $downtime = ProductionDowntime::query()
            ->where('started_at', '>=', now()->subDays($horizonDays))
            ->whereNotNull('duration_minutes')->whereNotNull('machine_id')
            ->selectRaw('machine_id, SUM(duration_minutes) m')->groupBy('machine_id')->pluck('m', 'machine_id');

        $rows = $centers->groupBy('machine_id')->map(function ($group) use ($load, $downtime, $horizonDays) {
            $machine  = $group->first()->machine;
            $planned  = $group->sum(fn ($wc) => (float) ($load[$wc->id]->m ?? 0));
            $capacity = $group->sum(fn ($wc) => (float) $wc->capacity_hours_per_day * 60 * ((float) $wc->efficiency_rate / 100) * $horizonDays);
            $stops    = (float) ($downtime[$group->first()->machine_id] ?? 0);
            $netCap   = max(0, $capacity - $stops);
            $occupation = $netCap > 0 ? round($planned / $netCap * 100, 1) : ($planned > 0 ? 100 : 0);

            return [
                'id'         => $machine?->id,
                'name'       => $machine?->name ?? '— Non affectée',
                'code'       => $machine?->code,
                'planned_h'  => round($planned / 60, 1),
                'capacity_h' => round($capacity / 60, 1),
                'downtime_h' => round($stops / 60, 1),
                'net_capacity_h' => round($netCap / 60, 1),
                'occupation' => $occupation,
                'status'     => $this->occupationStatus($occupation, $planned),
            ];
        })->sortByDesc('occupation')->values();

        return $this->wrap($horizonDays, $rows);
    }

    /**
     * [PRO Plan de charge] Charge agrégée par ÉQUIPE (default_team des centres de travail).
     */
    public function loadByTeam(int $horizonDays = 7): array
    {
        $centers = WorkCenter::where('is_active', true)->get();
        $load    = $this->plannedMinutesByWorkCenter();

        $rows = $centers->groupBy(fn ($wc) => $wc->default_team ?: '— Non affectée')->map(function ($group, $team) use ($load, $horizonDays) {
            $planned  = $group->sum(fn ($wc) => (float) ($load[$wc->id]->m ?? 0));
            $capacity = $group->sum(fn ($wc) => (float) $wc->capacity_hours_per_day * 60 * ((float) $wc->efficiency_rate / 100) * $horizonDays);
            $occupation = $capacity > 0 ? round($planned / $capacity * 100, 1) : ($planned > 0 ? 100 : 0);

            return [
                'name'       => $team,
                'centers'    => $group->count(),
                'planned_h'  => round($planned / 60, 1),
                'capacity_h' => round($capacity / 60, 1),
                'occupation' => $occupation,
                'status'     => $this->occupationStatus($occupation, $planned),
            ];
        })->sortByDesc('occupation')->values();

        return $this->wrap($horizonDays, $rows);
    }

    /** Minutes planifiées (opérations non terminées d'OF actifs) par centre de travail. */
    private function plannedMinutesByWorkCenter(): \Illuminate\Support\Collection
    {
        return ProductionOrderOperation::query()
            ->whereNotNull('work_center_id')
            ->where('status', '!=', 'done')
            ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))
            ->selectRaw('work_center_id, SUM(planned_minutes) m, COUNT(*) n')
            ->groupBy('work_center_id')
            ->get()->keyBy('work_center_id');
    }

    private function occupationStatus(float $occupation, float $planned): string
    {
        return match (true) {
            $occupation > 100 => 'surcharge',
            $occupation >= 80 => 'charge',
            $planned <= 0     => 'libre',
            default           => 'ok',
        };
    }

    private function wrap(int $horizonDays, \Illuminate\Support\Collection $rows): array
    {
        return [
            'horizon'          => $horizonDays,
            'rows'             => $rows,
            'overloaded'       => $rows->where('status', 'surcharge')->count(),
            'total_planned_h'  => round($rows->sum('planned_h'), 1),
            'total_capacity_h' => round($rows->sum('capacity_h'), 1),
        ];
    }
}
