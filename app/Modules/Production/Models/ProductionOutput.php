<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOutput extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;
    protected $fillable = ['company_id','production_order_id','product_id','length','color','thickness','quantity','total_meters','unit_id','stock_movement_id','warehouse_id','lot_number','notes','produced_at','status','validated_by','validated_at','created_by'];
    protected $casts = ['length'=>'decimal:2','thickness'=>'decimal:2','quantity'=>'decimal:2','total_meters'=>'decimal:2','produced_at'=>'date','validated_at'=>'datetime'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }
    public function validatedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'validated_by'); }

    /** [CDC §13.3] Visa chef d'équipe posé ? */
    public function isValidated(): bool { return $this->status === 'validee'; }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionOutputFactory::new();
    }
}
