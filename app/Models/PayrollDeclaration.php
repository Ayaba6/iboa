<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [PAI-08] Déclaration sociale/fiscale figée (CNSS, IUTS) issue d'un run de paie.
 */
class PayrollDeclaration extends Model
{
    use HasFactory;
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'period_month', 'period_year', 'type',
        'base_amount', 'salarial_amount', 'patronal_amount', 'total_amount', 'headcount',
        'status', 'deposited_at', 'paid_at', 'receipt_number', 'notes', 'created_by',
    ];

    protected $casts = [
        'base_amount'     => 'decimal:2',
        'salarial_amount' => 'decimal:2',
        'patronal_amount' => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'headcount'       => 'integer',
        'deposited_at'    => 'date',
        'paid_at'         => 'date',
    ];

    public const TYPES = [
        'cnss' => 'CNSS',
        'iuts' => 'IUTS',
    ];

    public const STATUSES = [
        'a_deposer' => 'À déposer',
        'depose'    => 'Déposée',
        'paye'      => 'Payée',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? strtoupper($this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function periodLabel(): string
    {
        return str_pad((string) $this->period_month, 2, '0', STR_PAD_LEFT).'/'.$this->period_year;
    }
}
