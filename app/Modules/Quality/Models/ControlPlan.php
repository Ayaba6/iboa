<?php

namespace App\Modules\Quality\Models;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [QUA-01] Plan de contrôle qualité (référentiel de caractéristiques).
 */
class ControlPlan extends Model
{
    use HasCompanyScope;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'reference', 'name', 'product_id', 'product_family_id',
        'stage', 'is_active', 'description', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const STAGES = [
        'reception'  => 'Contrôle réception',
        'production' => 'Contrôle en cours de production',
        'final'      => 'Contrôle final',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'product_family_id');
    }

    public function characteristics(): HasMany
    {
        return $this->hasMany(ControlPlanCharacteristic::class)->orderBy('sort_order');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }
}
