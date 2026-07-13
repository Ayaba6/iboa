<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Plan d'optimisation de découpe (maquette Optimisation de découpe). */
class CuttingOptimization extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'site', 'atelier', 'production_line_id', 'type_optimisation',
        'coil_id', 'product_id', 'profil', 'thickness', 'coil_width', 'useful_width',
        'standard_length', 'method', 'execution_date', 'priorite', 'status', 'notes',
        'allow_order_mixing', 'min_reusable_offcut', 'cut_tolerance_mm', 'respect_client_lot',
        'group_by_color', 'optimize_by_delivery_date', 'valorize_offcuts',
        'total_requested_m', 'optimized_m', 'material_yield', 'estimated_waste_m',
        'reusable_offcut_m', 'scrap_m', 'strips_per_coil', 'width_yield',
        'cuts_count', 'coils_used', 'plan', 'created_by',
    ];
    protected $casts = [
        'thickness' => 'decimal:2', 'coil_width' => 'decimal:2', 'useful_width' => 'decimal:2',
        'standard_length' => 'decimal:2', 'execution_date' => 'date',
        'allow_order_mixing' => 'boolean', 'min_reusable_offcut' => 'decimal:2',
        'cut_tolerance_mm' => 'decimal:2', 'respect_client_lot' => 'boolean',
        'group_by_color' => 'boolean', 'optimize_by_delivery_date' => 'boolean', 'valorize_offcuts' => 'boolean',
        'total_requested_m' => 'decimal:2', 'optimized_m' => 'decimal:2', 'material_yield' => 'decimal:2',
        'estimated_waste_m' => 'decimal:2', 'reusable_offcut_m' => 'decimal:2', 'scrap_m' => 'decimal:2',
        'strips_per_coil' => 'integer', 'width_yield' => 'decimal:2',
        'cuts_count' => 'integer', 'coils_used' => 'integer',
        'plan' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class, 'production_line_id'); }
    public function coil(): BelongsTo { return $this->belongsTo(Coil::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function lines(): HasMany { return $this->hasMany(CuttingOptimizationLine::class)->orderBy('sort_order'); }
}
