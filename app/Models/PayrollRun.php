<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

class PayrollRun extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'journal_entry_id',
        'period_month', 'period_year', 'status',
        'total_brut', 'total_cnss_employee', 'total_cnss_employer',
        'total_iuts', 'total_net', 'employee_count',
        'notes', 'validated_by', 'validated_at', 'paid_at', 'created_by',
        'calculation_parameters_snapshot',
    ];

    protected $casts = [
        'period_month'       => 'integer',
        'period_year'        => 'integer',
        'total_brut'         => 'integer',
        'total_cnss_employee'=> 'integer',
        'total_cnss_employer'=> 'integer',
        'total_iuts'         => 'integer',
        'total_net'          => 'integer',
        'employee_count'     => 'integer',
        'validated_at'       => 'datetime',
        'paid_at'            => 'date',
        'calculation_parameters_snapshot' => 'array',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function fiscalYear(): BelongsTo{ return $this->belongsTo(FiscalYear::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function validatedBy(): BelongsTo { return $this->belongsTo(User::class, 'validated_by'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany       { return $this->hasMany(PayrollItem::class); }
    public function payments(): HasMany     { return $this->hasMany(\App\Models\PayrollPayment::class); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'Brouillon',
            'calcule'   => 'Calculé',
            'valide'    => 'Validé',
            'paye'      => 'Payé',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'gray',
            'calcule'   => 'blue',
            'valide'    => 'green',
            'paye'      => 'emerald',
            default     => 'gray',
        };
    }

    public function isEditable(): bool { return in_array($this->status, ['brouillon', 'calcule']); }
    public function isValidated(): bool { return in_array($this->status, ['valide', 'paye']); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($q, int $companyId) { return $q->where('company_id', $companyId); }
}
