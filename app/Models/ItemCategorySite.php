<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [X3 §9] Surcharge d'une catégorie d'article pour un site (agence). */
class ItemCategorySite extends Model
{
    protected $fillable = [
        'item_category_id', 'site_id',
        'mp_warehouse_id', 'pf_warehouse_id', 'receipt_warehouse_id', 'production_line_id',
        'lead_time_days', 'stock_min', 'stock_max', 'stock_securite', 'mrp_planned',
    ];

    protected $casts = [
        'mrp_planned' => 'boolean',
        'stock_min' => 'decimal:2', 'stock_max' => 'decimal:2', 'stock_securite' => 'decimal:2',
    ];

    public function category(): BelongsTo { return $this->belongsTo(ItemCategory::class, 'item_category_id'); }
    public function site(): BelongsTo { return $this->belongsTo(Warehouse::class, 'site_id'); }
}
