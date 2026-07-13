<?php

namespace App\Services;

use App\Models\CareerEvent;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * [RH-05] Enregistrement des mouvements de carrière + application à la fiche salarié.
 * L'évènement capture l'état antérieur (from_*) et applique le nouvel état (to_*)
 * à l'employé si la date d'effet est atteinte.
 */
class CareerMovementService
{
    /**
     * Crée un évènement de carrière. Renseigne automatiquement les valeurs
     * « from » depuis la fiche courante et applique les « to » si daté à ce jour.
     */
    public function record(Employee $employee, array $data): CareerEvent
    {
        return DB::transaction(function () use ($employee, $data) {
            $event = CareerEvent::create(array_merge($data, [
                'company_id'           => $employee->company_id,
                'employee_id'          => $employee->id,
                'from_job_position_id' => $employee->job_position_id,
                'from_department_id'   => $employee->department_id,
                'from_category'        => $employee->category,
                'from_fonction'        => $employee->fonction,
                'created_by'           => auth()->id(),
                'applied'              => false,
            ]));

            $effective = $event->effective_date;
            if ($effective && ! $effective->isFuture()) {
                $this->apply($event);
            }

            return $event->fresh();
        });
    }

    /** Applique les valeurs cibles de l'évènement à la fiche salarié (idempotent). */
    public function apply(CareerEvent $event): void
    {
        if ($event->applied) {
            return;
        }

        $employee = $event->employee;
        $changes = array_filter([
            'job_position_id' => $event->to_job_position_id,
            'department_id'   => $event->to_department_id,
            'category'        => $event->to_category,
            'fonction'        => $event->to_fonction,
        ], fn ($v) => $v !== null && $v !== '');

        if ($changes) {
            $employee->update($changes);
        }
        $event->update(['applied' => true]);
    }
}
