<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [Maquette Numérotation] paramètres globaux de numérotation — singleton par société.
class NumberingSetting extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'default_fiscal_year_id',
        'separator',
        'digits',
        'reset_on_close',
        'company_prefix',
        'include_year',
        'include_month',
        'preview_format',
        'gap_policy',
        'per_site',
        'per_journal',
        'date_format',
        'comments',
    ];

    protected $casts = [
        'reset_on_close' => 'boolean',
        'include_year'   => 'boolean',
        'include_month'  => 'boolean',
        'per_site'       => 'boolean',
        'per_journal'    => 'boolean',
        'digits'         => 'integer',
    ];

    public function defaultFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'default_fiscal_year_id');
    }
}
