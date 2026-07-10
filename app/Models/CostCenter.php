<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [§12 CDC] Centre de coûts / centre de profit / centre d'investissement.
 * Permet la comptabilité analytique : ventilation des charges par axe métier.
 */
class CostCenter extends Model
{
    use HasCompanyScope, SoftDeletes;

    protected $table = 'cost_centers';

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'parent_id', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo    { return $this->belongsTo(CostCenter::class, 'parent_id'); }
    public function children(): HasMany    { return $this->hasMany(CostCenter::class, 'parent_id'); }
    public function analyticLines(): HasMany { return $this->hasMany(AnalyticLine::class); }

    public function typeLabel(): string
    {
        return match($this->type) {
            'cost'       => 'Centre de coûts',
            'profit'     => 'Centre de profit',
            'investment' => "Centre d'investissement",
            default      => $this->type,
        };
    }

    public function totalDebit(): float
    {
        return (float) $this->analyticLines()->where('amount', '>', 0)->sum('amount');
    }

    public function totalCredit(): float
    {
        return (float) $this->analyticLines()->where('amount', '<', 0)->sum('amount');
    }

    public function balance(): float
    {
        return (float) $this->analyticLines()->sum('amount');
    }
}
