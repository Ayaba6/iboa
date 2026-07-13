<?php

namespace App\Services;

use App\Models\EmployeeDeparture;
use Illuminate\Support\Facades\DB;

/**
 * [RH-13] Solde de tout compte et clôture d'un départ salarié.
 */
class DepartureService
{
    /** Total du solde de tout compte = indemnités + congés soldés + autres. */
    public function computeTotal(EmployeeDeparture $departure): float
    {
        return round(
            (float) $departure->severance_amount
            + (float) $departure->notice_amount
            + (float) $departure->leave_balance_amount
            + (float) $departure->other_amount,
            2
        );
    }

    /** Recalcule et enregistre le total STC. */
    public function refreshTotal(EmployeeDeparture $departure): void
    {
        $departure->update(['total_stc' => $this->computeTotal($departure)]);
    }

    /**
     * Clôture le départ : fige le STC, marque le salarié sorti et pose sa date de sortie.
     */
    public function finalize(EmployeeDeparture $departure): void
    {
        DB::transaction(function () use ($departure) {
            $departure->update([
                'total_stc'    => $this->computeTotal($departure),
                'status'       => 'cloture',
                'finalized_at' => now()->toDateString(),
            ]);

            $departure->employee?->update([
                'status'     => 'sorti',
                'leave_date' => $departure->effective_date,
            ]);
        });
    }
}
