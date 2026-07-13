<?php

namespace App\Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [QUA-01] Caractéristique d'un plan de contrôle (méthode, fréquence, tolérance).
 */
class ControlPlanCharacteristic extends Model
{
    protected $fillable = [
        'control_plan_id', 'sort_order', 'name', 'method', 'unit', 'frequency',
        'sampling', 'target_value', 'tolerance_min', 'tolerance_max', 'is_critical', 'responsible',
    ];

    protected $casts = [
        'sort_order'    => 'integer',
        'target_value'  => 'decimal:4',
        'tolerance_min' => 'decimal:4',
        'tolerance_max' => 'decimal:4',
        'is_critical'   => 'boolean',
    ];

    public function controlPlan(): BelongsTo
    {
        return $this->belongsTo(ControlPlan::class);
    }

    /** Teste si une valeur mesurée est dans les tolérances (null si non bornée). */
    public function isWithinTolerance(float $value): ?bool
    {
        if ($this->tolerance_min === null && $this->tolerance_max === null) {
            return null;
        }
        if ($this->tolerance_min !== null && $value < (float) $this->tolerance_min) {
            return false;
        }
        if ($this->tolerance_max !== null && $value > (float) $this->tolerance_max) {
            return false;
        }

        return true;
    }
}
