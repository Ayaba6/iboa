<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

/**
 * [RH-PRO] Prêt salarié — remboursement mensuel sur plusieurs périodes.
 */
class EmployeeLoan extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'loan_number',
        'amount', 'monthly_deduction', 'remaining_balance', 'nb_months',
        'status', 'start_date', 'end_date',
        'notes', 'reason',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'amount'             => 'integer',
        'monthly_deduction'  => 'integer',
        'remaining_balance'  => 'integer',
        'nb_months'          => 'integer',
        'start_date'         => 'date',
        'end_date'           => 'date',
        'approved_at'        => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo{ return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function payments(): HasMany    { return $this->hasMany(EmployeeLoanPayment::class); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'actif'     => 'En cours',
            'rembourse' => 'Remboursé',
            'annule'    => 'Annulé',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'actif'     => 'blue',
            'rembourse' => 'emerald',
            'annule'    => 'red',
            default     => 'gray',
        };
    }

    public function getTotalPaidAttribute(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function getProgressPctAttribute(): int
    {
        if ($this->amount <= 0) {
            return 0;
        }
        return (int) min(100, round($this->total_paid / $this->amount * 100));
    }

    public function isActive(): bool { return $this->status === 'actif'; }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)  { return $q->where('status', 'actif'); }
    public function scopeForCompany($q, int $cId) { return $q->where('company_id', $cId); }
}
