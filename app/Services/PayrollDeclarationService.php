<?php

namespace App\Services;

use App\Models\PayrollDeclaration;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * [PAI-08] Génération et figeage des déclarations CNSS / IUTS à partir d'un run de paie.
 * Les montants sont agrégés depuis les payroll_items et archivés (historique légal).
 */
class PayrollDeclarationService
{
    /**
     * Crée (ou met à jour tant que non déposée) les déclarations CNSS + IUTS du run.
     * Idempotent : ne réécrit pas une déclaration déjà déposée/payée.
     *
     * @return array<int,\App\Models\PayrollDeclaration>
     */
    public function generateForRun(PayrollRun $run): array
    {
        $run->loadMissing('items');
        $items    = $run->items;
        $headcount = $items->count();

        $rows = [
            'cnss' => [
                'base_amount'     => (float) $items->sum('cnss_base'),
                'salarial_amount' => (float) $items->sum('cnss_employee'),
                'patronal_amount' => (float) $items->sum('cnss_employer'),
                'total_amount'    => (float) $items->sum('cnss_employee') + (float) $items->sum('cnss_employer'),
            ],
            'iuts' => [
                'base_amount'     => (float) $items->sum('salaire_imposable'),
                'salarial_amount' => (float) $items->sum('iuts_amount'),
                'patronal_amount' => 0.0,
                'total_amount'    => (float) $items->sum('iuts_amount'),
            ],
        ];

        $out = [];
        DB::transaction(function () use ($run, $rows, $headcount, &$out) {
            foreach ($rows as $type => $amounts) {
                $existing = PayrollDeclaration::where('payroll_run_id', $run->id)->where('type', $type)->first();
                if ($existing && in_array($existing->status, ['depose', 'paye'], true)) {
                    $out[] = $existing; // déjà figée et transmise — on ne touche pas
                    continue;
                }

                $out[] = PayrollDeclaration::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'type' => $type],
                    array_merge($amounts, [
                        'company_id'   => $run->company_id,
                        'period_month' => $run->period_month,
                        'period_year'  => $run->period_year,
                        'headcount'    => $headcount,
                        'status'       => 'a_deposer',
                        'created_by'   => auth()->id(),
                    ])
                );
            }
        });

        return $out;
    }

    /** Marque une déclaration déposée (avec N° d'accusé de télédéclaration). */
    public function markDeposited(PayrollDeclaration $declaration, ?string $receipt = null): void
    {
        $declaration->update([
            'status'         => 'depose',
            'deposited_at'   => now()->toDateString(),
            'receipt_number' => $receipt ?: $declaration->receipt_number,
        ]);
    }

    /** Marque une déclaration payée. */
    public function markPaid(PayrollDeclaration $declaration): void
    {
        $declaration->update([
            'status'  => 'paye',
            'paid_at' => now()->toDateString(),
        ]);
    }
}
