<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\MaintenancePlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [CDC §13.8/§14] Plans de maintenance préventive — un plan = une périodicité
 * nommée par machine (ex. "Graissage hebdomadaire", "Révision trimestrielle").
 * Plusieurs plans peuvent coexister sur une même machine, contrairement au
 * simple champ ProductionMachine.maintenance_frequency_days (1 seule échéance).
 */
class MaintenancePlanService
{
    public function create(array $data): MaintenancePlan
    {
        $data['company_id']   = currentCompany()->id;
        $data['next_due_at']  = $data['next_due_at'] ?? now()->addDays((int) $data['frequency_days']);
        $data['is_active']    = $data['is_active'] ?? true;
        $data['created_by']   = Auth::id();

        return MaintenancePlan::create($data);
    }

    public function update(MaintenancePlan $plan, array $data): MaintenancePlan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    /** Plans actifs dont l'échéance est atteinte. */
    public function dueePlans(): Collection
    {
        return MaintenancePlan::with('machine')
            ->where('is_active', true)
            ->whereDate('next_due_at', '<=', now()->toDateString())
            ->get();
    }

    /**
     * Génère une MachineMaintenance (préventive, brouillon "planifie") pour
     * chaque plan dû, et avance next_due_at de frequency_days. Idempotent par
     * appel : un plan déjà généré aujourd'hui ne l'est pas une seconde fois
     * tant que sa nouvelle échéance n'est pas de nouveau atteinte.
     */
    public function generateDueInterventions(): Collection
    {
        return DB::transaction(function () {
            $generated = collect();

            foreach ($this->dueePlans() as $plan) {
                $intervention = MachineMaintenance::create([
                    'company_id'          => $plan->company_id,
                    'machine_id'          => $plan->machine_id,
                    'maintenance_plan_id' => $plan->id,
                    'type'                => 'preventive',
                    'title'               => $plan->name,
                    'status'              => 'planifie',
                    'planned_at'          => now(),
                    'notes'               => $plan->instructions,
                    'created_by'          => Auth::id(),
                ]);

                $plan->update([
                    'last_generated_at' => now()->toDateString(),
                    'next_due_at'       => now()->addDays($plan->frequency_days)->toDateString(),
                ]);

                $generated->push($intervention);
            }

            return $generated;
        });
    }
}
