<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [X3 §10] Paramètres d'un article pour un site (priorité maximale). */
class ProductSite extends Model
{
    protected $fillable = [
        'product_id', 'site_id',
        'mp_warehouse_id', 'pf_warehouse_id', 'receipt_warehouse_id', 'production_line_id',
        'lead_time_days', 'stock_min', 'stock_max', 'stock_securite',
    ];

    protected $casts = [
        'stock_min' => 'decimal:2', 'stock_max' => 'decimal:2', 'stock_securite' => 'decimal:2',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function site(): BelongsTo { return $this->belongsTo(Warehouse::class, 'site_id'); }
}
