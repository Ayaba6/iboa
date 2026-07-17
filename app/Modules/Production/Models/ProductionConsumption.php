<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\StockMovement;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionConsumption extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;
    protected $fillable = ['company_id','production_order_id','coil_id','weight_consumed','length_consumed','cost','stock_movement_id','consumption_source','reversed_at','reversed_by','consumed_at','created_by'];
    protected $casts = ['weight_consumed'=>'decimal:2','length_consumed'=>'decimal:2','cost'=>'integer','consumed_at'=>'date'];

    /** Consommations effectives (non annulées) — à utiliser dans tous les agrégats. */
    public function scopeActive($q) { return $q->whereNull('reversed_at'); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function coil(): BelongsTo { return $this->belongsTo(Coil::class); }
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionConsumptionFactory::new();
    }
}
