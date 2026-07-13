<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [RH-10] Session de formation.
 */
class TrainingSession extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'title', 'competence', 'provider', 'location',
        'start_date', 'end_date', 'cost', 'max_participants', 'status', 'description', 'created_by',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'cost'             => 'decimal:2',
        'max_participants' => 'integer',
    ];

    public const STATUSES = [
        'planifiee' => 'Planifiée',
        'en_cours'  => 'En cours',
        'terminee'  => 'Terminée',
        'annulee'   => 'Annulée',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Coût par participant (répartition du coût total sur les inscrits). */
    public function getCostPerParticipantAttribute(): ?float
    {
        $n = $this->participants()->count();

        return ($this->cost !== null && $n > 0) ? round((float) $this->cost / $n, 2) : null;
    }
}
