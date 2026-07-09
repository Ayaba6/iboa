<?php

namespace App\Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [QUALITÉ] Caractéristique contrôlée d'un contrôle qualité (spec min/max, méthode, résultat). */
class QualityInspectionCharacteristic extends Model
{
    protected $fillable = [
        'quality_inspection_id', 'number', 'name', 'spec_min', 'spec_max', 'unit',
        'control_method', 'frequency', 'result', 'conformity', 'sort_order',
    ];
    protected $casts = ['number' => 'integer', 'sort_order' => 'integer'];

    public function inspection(): BelongsTo { return $this->belongsTo(QualityInspection::class, 'quality_inspection_id'); }
}
