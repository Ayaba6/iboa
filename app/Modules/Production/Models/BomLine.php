<?php
namespace App\Modules\Production\Models;
use App\Models\Product;
use App\Models\Unit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    use HasFactory;
    protected $fillable = [
        'bill_of_material_id','product_id','label','quantity_per_meter','unit_id','waste_rate','sort_order',
        // [SAGE parité]
        'sequence','groupe','type_composant','coef','depot_sortie_id','lot_obligatoire','statut',
        // [PRO-01] substitution
        'substitute_product_id','substitute_note',
    ];
    protected $casts = [
        'quantity_per_meter'=>'decimal:4','waste_rate'=>'decimal:2',
        'coef'=>'decimal:6','lot_obligatoire'=>'boolean','sequence'=>'integer',
    ];

    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function substitute(): BelongsTo { return $this->belongsTo(Product::class, 'substitute_product_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function depotSortie(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_sortie_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\BomLineFactory::new();
    }
}
