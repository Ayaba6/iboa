<?php

namespace App\Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [QUALITÉ] Caractéristique en défaut d'une non-conformité (spec vs valeur mesurée). */
class NonConformityCharacteristic extends Model
{
    protected $fillable = [
        'non_conformity_id', 'name', 'spec_min', 'spec_max', 'unit',
        'measured_value', 'result', 'sort_order',
    ];
    protected $casts = ['sort_order' => 'integer'];

    public function nonConformity(): BelongsTo { return $this->belongsTo(NonConformity::class); }
}
