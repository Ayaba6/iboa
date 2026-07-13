<?php

namespace App\Modules\Quality\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\User;
use App\Modules\Production\Models\ProductionBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [QUA-07] Décision de libération qualité d'un lot de fabrication.
 */
class QualityRelease extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'production_batch_id', 'control_plan_id', 'reference', 'quantity',
        'status', 'decision_comment', 'derogation_reference', 'decided_by', 'decided_at', 'created_by',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public const STATUSES = [
        'en_attente' => 'En attente',
        'libere'     => 'Libéré',
        'refuse'     => 'Refusé',
        'derogation' => 'Sous dérogation',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function controlPlan(): BelongsTo
    {
        return $this->belongsTo(ControlPlan::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Lot disponible à l'expédition : libéré ou sous dérogation. */
    public function isReleased(): bool
    {
        return in_array($this->status, ['libere', 'derogation'], true);
    }
}
