<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope;

    protected $fillable = [
        'company_id', 'department_id', 'job_position_id', 'matricule', 'photo_path',
        'last_name', 'first_name', 'gender', 'birth_date', 'birth_place', 'nationality',
        'cin_number', 'cnss_number',
        'email', 'phone', 'address', 'city',
        'job_title', 'category', 'hiring_date', 'leave_date', 'status',
        'family_status', 'nb_children',
        'bank_name', 'bank_account', 'bank_code', 'bank_branch',
        'bank_account_number', 'bank_rib_key', 'payment_mode',
        'emergency_contact_name', 'emergency_contact_phone',
        'education_level', 'fonction',
        'created_by', 'user_id',
    ];

    protected $casts = [
        'birth_date'  => 'date',
        'hiring_date' => 'date',
        'leave_date'  => 'date',
        'nb_children' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }
    public function jobPosition(): BelongsTo { return $this->belongsTo(JobPosition::class); }
    public function user(): BelongsTo        { return $this->belongsTo(\App\Models\User::class); }
    public function contracts(): HasMany       { return $this->hasMany(EmployeeContract::class); }
    public function allowances(): HasMany      { return $this->hasMany(EmployeeAllowance::class); }
    public function payrollItems(): HasMany    { return $this->hasMany(PayrollItem::class); }
    public function payrollVariables(): HasMany{ return $this->hasMany(PayrollVariable::class); }
    public function salaryAdvances(): HasMany  { return $this->hasMany(SalaryAdvance::class); }
    public function leaveRequests(): HasMany   { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances(): HasMany   { return $this->hasMany(LeaveBalance::class); }
    public function documents(): HasMany       { return $this->hasMany(EmployeeDocument::class); }
    public function loans(): HasMany           { return $this->hasMany(EmployeeLoan::class); }
    public function activeLoans(): HasMany     { return $this->hasMany(EmployeeLoan::class)->where('status', 'actif'); }
    public function attendances(): HasMany     { return $this->hasMany(Attendance::class); }

    public function activeContract(): HasOne
    {
        return $this->hasOne(EmployeeContract::class)->where('status', 'actif')->latestOfMany('start_date');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim($this->last_name . ' ' . $this->first_name);
    }

    /** Parts fiscales IUTS (quotient familial Burkina Faso) */
    public function getNbPartsAttribute(): float
    {
        $parts = match ($this->family_status) {
            'marie'  => 2.0,
            'veuf'   => 1.5,
            default  => 1.0, // célibataire, divorcé
        };
        $parts += $this->nb_children * 0.5;
        return min($parts, 5.0); // plafond 5 parts
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'cadre'          => 'Cadre',
            'agent_maitrise' => 'Agent de maîtrise',
            'employe'        => 'Employé',
            'ouvrier'        => 'Ouvrier',
            default          => $this->category,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'actif'       => 'Actif',
            'suspendu'    => 'Suspendu',
            'licencie'    => 'Licencié',
            'demissionne' => 'Démissionné',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'actif'    => 'green',
            'suspendu' => 'yellow',
            default    => 'red',
        };
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Ancienneté en années complètes */
    public function getAncienneteAttribute(): int
    {
        return $this->hiring_date ? (int) $this->hiring_date->diffInYears(now()) : 0;
    }

    /** RIB formaté : code_banque code_guichet num_compte clé */
    public function getRibFormatteAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->bank_code, $this->bank_branch,
            $this->bank_account_number, $this->bank_rib_key,
        ])));
    }

    public function scopeActive($q)       { return $q->where('status', 'actif'); }
    public function scopeWithContract($q) { return $q->whereHas('activeContract'); }
}
