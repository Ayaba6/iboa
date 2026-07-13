<?php

namespace App\Modules\Quality\Models;

use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [QUA-05] Action corrective / préventive (CAPA) d'une non-conformité.
 */
class CorrectiveAction extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'non_conformity_id', 'reference', 'type', 'root_cause', 'action_plan',
        'responsible_id', 'due_date', 'status', 'completed_at',
        'effectiveness_comment', 'is_effective', 'verified_by_id', 'verified_at', 'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'date',
        'verified_at'  => 'date',
        'is_effective' => 'boolean',
    ];

    public const TYPES = [
        'corrective' => 'Corrective',
        'preventive' => 'Préventive',
    ];

    public const STATUSES = [
        'a_faire'   => 'À faire',
        'en_cours'  => 'En cours',
        'faite'     => 'Réalisée',
        'verifiee'  => 'Vérifiée',
        'cloturee'  => 'Clôturée',
    ];

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'verified_by_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** En retard : délai dépassé et non encore réalisée/vérifiée. */
    public function isOverdue(): bool
    {
        return $this->due_date
            && ! in_array($this->status, ['faite', 'verifiee', 'cloturee'], true)
            && $this->due_date->isPast();
    }
}
