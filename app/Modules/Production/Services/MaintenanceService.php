<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\MaintenancePart;
use App\Modules\Production\Models\ProductionMachine;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Maintenance machines : interventions préventives / correctives,
 * disponibilité (OEE composante), MTBF / MTTR, et alertes préventif dû.
 */
class MaintenanceService
{
    public function __construct(private StockService $stock) {}

    /**
     * [CDC §13.8] Enregistre une sortie de pièce de rechange pour une
     * intervention — sortie de stock réelle (warehouse → product_stocks),
     * traçable, au lieu d'un simple coût forfaitaire saisi à la main.
     */
    public function consumePart(MachineMaintenance $m, int $productId, float $quantity, int $warehouseId): MaintenancePart
    {
        if ($m->status === 'termine') {
            throw ValidationException::withMessages(['status' => 'Intervention déjà clôturée — pièce non ajoutable.']);
        }

        return DB::transaction(function () use ($m, $productId, $quantity, $warehouseId) {
            $unitCost = (float) (\App\Models\ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)->value('avg_cost') ?? 0);

            $movement = $this->stock->recordMovement([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'type'           => 'sortie',
                'quantity'       => $quantity,
                'unit_cost'      => $unitCost,
                'occurred_at'    => now(),
                'reference_type' => 'maintenance',
                'reference_id'   => $m->id,
                'notes'          => 'Pièce consommée — intervention ' . ($m->title ?: '#' . $m->id),
            ]);

            return MaintenancePart::create([
                'company_id'             => $m->company_id,
                'machine_maintenance_id' => $m->id,
                'product_id'             => $productId,
                'warehouse_id'           => $warehouseId,
                'quantity'               => $quantity,
                'unit_cost'              => (int) round($unitCost),
                'stock_movement_id'      => $movement->id,
                'created_by'             => Auth::id(),
            ]);
        });
    }

    /** Démarre une intervention → machine en maintenance. */
    public function start(MachineMaintenance $m): void
    {
        if ($m->status === 'termine') {
            throw ValidationException::withMessages(['status' => 'Intervention déjà terminée.']);
        }
        DB::transaction(function () use ($m) {
            $m->update(['status' => 'en_cours', 'started_at' => $m->started_at ?? now()]);
            // [Sync ERP] transition d'état journalisée (non idempotente : une
            // intervention rouverte doit pouvoir re-bloquer la machine).
            app(\App\Services\Sync\SyncOrchestrator::class)->run(
                sourceModule: 'maintenance',
                targetModule: 'production',
                eventName: 'maintenance.started',
                action: 'set_machine_unavailable',
                source: $m,
                callback: fn () => $m->machine?->update(['status' => 'maintenance']),
                payload: ['machine_id' => $m->machine_id],
                idempotent: false,
            );
        });
    }

    /** Clôture une intervention → temps d'arrêt + coût, machine réactivée. */
    public function finish(MachineMaintenance $m, ?float $downtimeMinutes = null, ?int $cost = null): void
    {
        if ($m->status === 'termine') {
            return;
        }
        $downtime = $downtimeMinutes;
        if ($downtime === null && $m->started_at) {
            $downtime = round($m->started_at->diffInSeconds(now()) / 60, 2);
        }
        DB::transaction(function () use ($m, $downtime, $cost) {
            $m->update([
                'status'           => 'termine',
                'ended_at'         => now(),
                'started_at'       => $m->started_at ?? now(),
                'downtime_minutes' => $downtime ?? 0,
                'cost'             => $cost ?? $m->cost,
            ]);
            // [Sync ERP] transition d'état journalisée : machine relâchée,
            // production relançable.
            app(\App\Services\Sync\SyncOrchestrator::class)->run(
                sourceModule: 'maintenance',
                targetModule: 'production',
                eventName: 'maintenance.closed',
                action: 'restore_machine_available',
                source: $m,
                callback: fn () => $m->machine?->update(['status' => 'active']),
                payload: ['machine_id' => $m->machine_id, 'downtime_minutes' => $downtime ?? 0],
                idempotent: false,
            );
            \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new \App\Events\MaintenanceInterventionClosed($m)));
        });
    }

    /** KPI disponibilité / MTBF / MTTR d'une machine sur une période. */
    public function machineKpis(ProductionMachine $machine, int $periodDays = 30): array
    {
        $from = Carbon::now()->subDays($periodDays);
        $periodMinutes = $periodDays * 24 * 60;

        $done = MachineMaintenance::with('parts')
            ->where('machine_id', $machine->id)
            ->where('status', 'termine')
            ->where('ended_at', '>=', $from)->get();

        $downtime    = (float) $done->sum('downtime_minutes');
        $preventive  = $done->where('type', 'preventive');
        $corrective  = $done->where('type', 'corrective');
        $failures    = $corrective->count();
        $corrDowntime = (float) $corrective->sum('downtime_minutes');
        $uptime      = max(0, $periodMinutes - $downtime);

        return [
            'availability'   => $periodMinutes > 0 ? round($uptime / $periodMinutes * 100, 1) : 100,
            'downtime_h'     => round($downtime / 60, 1),
            'failures'       => $failures,
            'preventive_count' => $preventive->count(),
            'mtbf_h'         => $failures > 0 ? round($uptime / 60 / $failures, 1) : null,   // temps moyen entre pannes
            'mttr_h'         => $failures > 0 ? round($corrDowntime / 60 / $failures, 1) : null, // temps moyen de réparation
            // [CDC §13.8] coût total = saisie manuelle + pièces de rechange réellement consommées
            'cost'           => (int) $done->sum(fn (MachineMaintenance $m) => $m->totalCost()),
            'parts_cost'     => (int) $done->sum(fn (MachineMaintenance $m) => $m->parts->sum(fn ($p) => $p->quantity * $p->unit_cost)),
        ];
    }

    /** Machines dont la maintenance préventive est due. */
    public function dueList(): array
    {
        return ProductionMachine::whereNotNull('maintenance_frequency_days')
            ->where('maintenance_frequency_days', '>', 0)
            ->where('is_active', true)
            ->get()
            ->filter(function ($machine) {
                $last = MachineMaintenance::where('machine_id', $machine->id)
                    ->where('type', 'preventive')->where('status', 'termine')
                    ->max('ended_at');
                $due = $last
                    ? Carbon::parse($last)->addDays((int) $machine->maintenance_frequency_days)
                    : Carbon::now()->subDay();

                return $due->lte(now());
            })
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code, 'frequency' => $m->maintenance_frequency_days])
            ->values()->all();
    }
}
