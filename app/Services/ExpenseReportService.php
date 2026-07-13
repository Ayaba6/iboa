<?php

namespace App\Services;

use App\Models\ExpenseReport;

/**
 * [RH-09] Cycle de vie des notes de frais.
 */
class ExpenseReportService
{
    /** Recalcule le total à partir des lignes. */
    public function refreshTotal(ExpenseReport $report): void
    {
        $report->update(['total_amount' => round((float) $report->lines()->sum('amount'), 2)]);
    }

    public function submit(ExpenseReport $report): void
    {
        $report->update(['status' => 'soumise', 'reject_reason' => null]);
    }

    public function approve(ExpenseReport $report, ?int $approverId): void
    {
        $report->update([
            'status'      => 'approuvee',
            'approved_by' => $approverId,
            'approved_at' => now()->toDateString(),
        ]);
    }

    public function reject(ExpenseReport $report, ?string $reason): void
    {
        $report->update(['status' => 'rejetee', 'reject_reason' => $reason]);
    }

    public function markReimbursed(ExpenseReport $report, ?string $method): void
    {
        $report->update([
            'status'         => 'remboursee',
            'payment_method' => $method ?: $report->payment_method,
            'reimbursed_at'  => now()->toDateString(),
        ]);
    }
}
