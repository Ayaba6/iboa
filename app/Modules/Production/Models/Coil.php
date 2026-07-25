<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Models\Warehouse;
use Database\Factories\CoilFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Bobine de tôle — lot + suivi poids, liée à un produit matière 1re. */
class Coil extends Model
{
    use HasCompanyScope, HasCreator, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'product_id', 'supplier_id', 'reception_id', 'reference', 'lot_number', 'color', 'thickness', 'width',
        'initial_weight', 'remaining_weight', 'estimated_length', 'purchase_price', 'cost_per_kg',
        'received_at', 'status', 'notes', 'created_by', 'stock_lot_id', 'kg_per_linear_meter',
        // [Maquette Bobine] réception + caractéristiques + gestion
        'supplier_reference', 'warehouse_id', 'site', 'bl_number', 'origine', 'devise',
        'nuance', 'gross_weight', 'inner_diameter', 'outer_diameter', 'coating', 'surface_finish',
        'tolerance_thickness', 'barcode', 'brand', 'serial_number',
        'valuation_method', 'is_stock_managed', 'lot_tracking', 'allow_negative_stock',
        'valuation_status', 'valuation_reason', 'valuation_responsible_id',
    ];

    protected $casts = [
        'thickness' => 'decimal:2', 'width' => 'decimal:1', 'initial_weight' => 'decimal:2', 'remaining_weight' => 'decimal:2',
        'estimated_length' => 'decimal:2', 'purchase_price' => 'integer', 'cost_per_kg' => 'decimal:2', 'received_at' => 'date',
        'gross_weight' => 'decimal:3', 'inner_diameter' => 'decimal:2', 'outer_diameter' => 'decimal:2',
        'tolerance_thickness' => 'decimal:3',
        'is_stock_managed' => 'boolean', 'lot_tracking' => 'boolean', 'allow_negative_stock' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'disponible' && $this->valuation_status === 'valorisation_definitive' && (float) $this->cost_per_kg > 0 && (float) $this->remaining_weight > 0;
    }

    public function consumptionRate(): float
    {
        return $this->initial_weight > 0 ? (float) $this->remaining_weight / (float) $this->initial_weight : 0;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'disponible' => 'Disponible','en_production' => 'En production','epuisee' => 'Épuisée',default => $this->status
        };
    }

    protected static function newFactory()
    {
        return CoilFactory::new();
    }
}
