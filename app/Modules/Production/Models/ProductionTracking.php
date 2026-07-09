<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION — parité X3] Journal des suivis de fabrication. */
class ProductionTracking extends Model
{
    use HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'number', 'production_order_id', 'tracking_date',
        'track_operations', 'track_production', 'track_materials',
        'site', 'notes', 'created_by',
    ];

    protected $casts = [
        'tracking_date'    => 'date',
        'track_operations' => 'boolean',
        'track_production' => 'boolean',
        'track_materials'  => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }

    /** Libellé des suivis effectués (ex. « Opérations · Production »). */
    public function tracksLabel(): string
    {
        return collect([
            $this->track_operations ? 'Opérations' : null,
            $this->track_production ? 'Production' : null,
            $this->track_materials  ? 'Matière' : null,
        ])->filter()->implode(' · ') ?: '—';
    }
}
