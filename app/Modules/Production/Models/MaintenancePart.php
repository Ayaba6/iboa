<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [CDC §13.8/§14] Pièce de rechange consommée lors d'une intervention de
 * maintenance — sortie de stock réelle (lien stock_movements), pas une simple
 * estimation de coût.
 */
class MaintenancePart extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'machine_maintenance_id', 'product_id', 'warehouse_id',
        'quantity', 'unit_cost', 'stock_movement_id', 'created_by',
    ];

    protected $casts = [
        'quantity'  => 'decimal:3',
        'unit_cost' => 'integer',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function maintenance(): BelongsTo { return $this->belongsTo(MachineMaintenance::class, 'machine_maintenance_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }

    protected static function newFactory()
    {
        return \Database\Factories\MaintenancePartFactory::new();
    }
}
