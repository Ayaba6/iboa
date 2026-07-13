<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-10] Participant à une session de formation (présence, évaluation, habilitation).
 */
class TrainingParticipant extends Model
{
    protected $fillable = [
        'training_session_id', 'employee_id', 'status', 'score', 'passed',
        'certificate_number', 'certificate_expiry', 'comment',
    ];

    protected $casts = [
        'score'              => 'decimal:2',
        'passed'             => 'boolean',
        'certificate_expiry' => 'date',
    ];

    public const STATUSES = [
        'inscrit' => 'Inscrit',
        'present' => 'Présent',
        'absent'  => 'Absent',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Habilitation expirée ou proche de l'échéance (< 60 j). */
    public function certificateExpiringSoon(int $days = 60): bool
    {
        return $this->certificate_expiry
            && $this->certificate_expiry->lte(now()->addDays($days));
    }
}
