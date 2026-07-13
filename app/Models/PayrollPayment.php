<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [PAI-07] Ligne de paiement de paie (net à payer d'un salarié pour un run).
 */
class PayrollPayment extends Model
{
    use HasFactory;
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'employee_name', 'employee_matricule',
        'net_amount', 'method', 'bank_name', 'bank_account', 'cash_account_id',
        'status', 'reference', 'paid_at', 'reject_reason',
    ];

    protected $casts = [
        'net_amount' => 'integer',
        'paid_at'    => 'date',
    ];

    public const METHODS = ['virement' => 'Virement', 'especes' => 'Espèces', 'cheque' => 'Chèque', 'mobile_money' => 'Mobile Money'];

    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }

    public function statusLabel(): string
    {
        return ['en_attente' => 'En attente', 'paye' => 'Payé', 'rejete' => 'Rejeté'][$this->status] ?? $this->status;
    }
}
