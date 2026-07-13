<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-11] Critère / objectif d'une évaluation (pondéré, noté auto + manager).
 */
class AppraisalCriterion extends Model
{
    protected $fillable = [
        'appraisal_id', 'sort_order', 'label', 'weight', 'self_rating', 'manager_rating', 'comment',
    ];

    protected $casts = [
        'sort_order'     => 'integer',
        'weight'         => 'integer',
        'self_rating'    => 'decimal:1',
        'manager_rating' => 'decimal:1',
    ];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }
}
