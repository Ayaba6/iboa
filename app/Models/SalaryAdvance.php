<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

class SalaryAdvance extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'amount', 'advance_date', 'reason',
        'status', 'approved_by', 'approved_at', 'recovered_in_run_id',
        'recovered_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'advance_date' => 'date',
        'approved_at'  => 'datetime',
        'recovered_at' => 'date',
    ];

    const STATUSES = [
        'en_attente' => ['label' => 'En attente', 'color' => 'yellow'],
        'approuve'   => ['label' => 'Approuvé',   'color' => 'blue'],
        'rembourse'  => ['label' => 'Remboursé',  'color' => 'green'],
        'annule'     => ['label' => 'Annulé',     'color' => 'red'],
    ];

    public function company(): BelongsTo      { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo     { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo   { return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
    public function recoveredIn(): BelongsTo  { return $this->belongsTo(PayrollRun::class, 'recovered_in_run_id'); }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }
    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }

    public function scopePending($q)  { return $q->where('status', 'en_attente'); }
    public function scopeApproved($q) { return $q->where('status', 'approuve'); }
}
