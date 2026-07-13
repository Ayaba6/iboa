<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [RH-03] Besoin de recrutement (poste à pourvoir).
 */
class Recruitment extends Model
{
    use HasFactory;
    use HasCompanyScope;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'job_position_id', 'department_id', 'reference', 'title',
        'contract_type', 'positions_count', 'status', 'opened_at', 'closed_at',
        'description', 'requirements', 'created_by',
    ];

    protected $casts = [
        'positions_count' => 'integer',
        'opened_at'       => 'date',
        'closed_at'       => 'date',
    ];

    public const STATUSES = [
        'ouvert'   => 'Ouvert',
        'en_cours' => 'En cours',
        'pourvu'   => 'Pourvu',
        'annule'   => 'Annulé',
    ];

    public const CONTRACT_TYPES = [
        'cdi'     => 'CDI',
        'cdd'     => 'CDD',
        'stage'   => 'Stage',
        'interim' => 'Intérim',
    ];

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(JobCandidate::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Nombre de candidats embauchés sur ce besoin. */
    public function getHiredCountAttribute(): int
    {
        return $this->candidates()->where('status', 'embauche')->count();
    }
}
