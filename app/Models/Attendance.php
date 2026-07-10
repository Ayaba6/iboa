<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

class Attendance extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'date', 'status',
        'arrival_time', 'departure_time',
        'worked_hours', 'overtime_hours',
        'note', 'created_by',
    ];

    protected $casts = [
        'date'           => 'date',
        'worked_hours'   => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // ─── Status labels & colors ───────────────────────────────────────────────

    const STATUSES = [
        'present'  => ['label' => 'Présent',            'color' => 'green',  'icon' => '✅'],
        'absent'   => ['label' => 'Absent',             'color' => 'red',    'icon' => '❌'],
        'conge'    => ['label' => 'Congé',              'color' => 'blue',   'icon' => '🏖️'],
        'maladie'  => ['label' => 'Maladie',            'color' => 'orange', 'icon' => '🏥'],
        'mission'  => ['label' => 'Mission',            'color' => 'purple', 'icon' => '✈️'],
        'ferie'    => ['label' => 'Jour férié',         'color' => 'gray',   'icon' => '🎉'],
        'weekend'  => ['label' => 'Week-end',           'color' => 'gray',   'icon' => '😴'],
        'demi_j'   => ['label' => 'Demi-journée',       'color' => 'yellow', 'icon' => '🌗'],
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }

    public function getStatusIconAttribute(): string
    {
        return self::STATUSES[$this->status]['icon'] ?? '—';
    }

    public function isWorkingDay(): bool
    {
        return in_array($this->status, ['present', 'demi_j', 'mission']);
    }

    public function countAsAbsent(): bool
    {
        return $this->status === 'absent';
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
