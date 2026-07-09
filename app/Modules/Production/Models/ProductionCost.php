<?php
namespace App\Modules\Production\Models;
use App\Models\Company;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCost extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;
    protected $fillable = ['company_id','production_order_id','material_cost','labor_cost','machine_cost','energy_cost','maintenance_cost','packaging_cost','overhead_cost','total_cost','standard_total','variance','cost_per_meter','cost_per_unit','margin','created_by'];
    protected $casts = ['material_cost'=>'integer','labor_cost'=>'integer','machine_cost'=>'integer','energy_cost'=>'integer','maintenance_cost'=>'integer','packaging_cost'=>'integer','overhead_cost'=>'integer','total_cost'=>'integer','standard_total'=>'integer','variance'=>'integer','cost_per_meter'=>'decimal:2','cost_per_unit'=>'decimal:2','margin'=>'integer'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionCostFactory::new();
    }
}
