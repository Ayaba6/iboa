<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tranche d'un barème versionné (payroll_parameter_versions). */
class PayrollBaremeBracket extends Model
{
    protected $fillable = ['bareme_id', 'borne_min', 'borne_max', 'taux', 'ordre'];

    protected $casts = [
        'borne_min' => 'integer',
        'borne_max' => 'integer',
        'taux'      => 'float',
        'ordre'     => 'integer',
    ];

    public function bareme(): BelongsTo
    {
        return $this->belongsTo(PayrollParameterVersion::class, 'bareme_id');
    }
}
