<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-03] Candidature rattachée à un besoin de recrutement.
 */
class JobCandidate extends Model
{
    use HasFactory;
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'recruitment_id', 'first_name', 'last_name', 'email', 'phone',
        'source', 'cv_path', 'status', 'rating', 'notes', 'applied_at', 'hired_employee_id',
    ];

    protected $casts = [
        'rating'     => 'integer',
        'applied_at' => 'date',
    ];

    public const STATUSES = [
        'recu'          => 'Reçu',
        'preselectionne'=> 'Présélectionné',
        'entretien'     => 'Entretien',
        'retenu'        => 'Retenu',
        'rejete'        => 'Rejeté',
        'embauche'      => 'Embauché',
    ];

    public function recruitment(): BelongsTo
    {
        return $this->belongsTo(Recruitment::class);
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
