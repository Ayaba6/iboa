<?php

namespace App\Services;

use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * [PAI-07] Génération et suivi des virements/paiements de paie à partir
 * d'un run de paie validé.
 */
class PayrollPaymentService
{
    /**
     * Crée (ou met à jour) une ligne de paiement par salarié du run.
     * Idempotent : ne double jamais une ligne (unique run+employee).
     * Ne recrée pas une ligne déjà payée.
     */
    public function generateForRun(PayrollRun $run): int
    {
        $run->loadMissing('items.employee');
        $n = 0;

        DB::transaction(function () use ($run, &$n) {
            foreach ($run->items as $item) {
                $emp = $item->employee;
                $existing = PayrollPayment::where('payroll_run_id', $run->id)
                    ->where('employee_id', $item->employee_id)->first();

                if ($existing && $existing->status === 'paye') {
                    continue; // ne pas écraser un paiement déjà effectué
                }

                PayrollPayment::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'employee_id' => $item->employee_id],
                    [
                        'company_id'         => $run->company_id,
                        'employee_name'      => $item->employee_name ?? $emp?->full_name,
                        'employee_matricule' => $item->employee_matricule ?? $emp?->matricule,
                        'net_amount'         => (int) $item->salaire_net,
                        'method'             => $emp?->payment_mode ?? 'virement',
                        'bank_name'          => $emp?->bank_name,
                        'bank_account'       => $emp?->bank_account ?? $emp?->bank_account_number,
                        'status'             => 'en_attente',
                    ]
                );
                $n++;
            }
        });

        return $n;
    }

    /** Marque une ligne payée + propage paid_at sur le run si tout est payé. */
    public function markPaid(PayrollPayment $payment, ?string $reference = null): void
    {
        DB::transaction(function () use ($payment, $reference) {
            $payment->update([
                'status'    => 'paye',
                'paid_at'   => now()->toDateString(),
                'reference' => $reference ?: $payment->reference,
            ]);

            $run = $payment->payrollRun;
            if ($run && ! $run->payments()->where('status', '!=', 'paye')->exists()) {
                $run->update(['paid_at' => now(), 'status' => 'paye']);
            }
        });
    }

    /** Marque tout un run comme payé (virement groupé exécuté). */
    public function markRunPaid(PayrollRun $run, ?string $reference = null): int
    {
        $count = 0;
        DB::transaction(function () use ($run, $reference, &$count) {
            $count = $run->payments()->where('status', 'en_attente')->update([
                'status'    => 'paye',
                'paid_at'   => now()->toDateString(),
                'reference' => $reference,
            ]);
            $run->update(['paid_at' => now(), 'status' => 'paye']);
        });

        return $count;
    }

    /**
     * Fichier bancaire des virements (CSV point-virgule).
     * Colonnes : Matricule;Nom;Banque;Compte;Montant;Devise.
     */
    public function bankFileContent(PayrollRun $run): string
    {
        $lines = ['Matricule;Nom;Banque;Compte;Montant;Devise'];
        foreach ($run->payments()->where('method', 'virement')->orderBy('employee_name')->get() as $p) {
            $lines[] = implode(';', [
                $p->employee_matricule,
                str_replace(';', ' ', (string) $p->employee_name),
                str_replace(';', ' ', (string) $p->bank_name),
                $p->bank_account,
                $p->net_amount,
                'XOF',
            ]);
        }

        return implode("\r\n", $lines) . "\r\n";
    }
}
