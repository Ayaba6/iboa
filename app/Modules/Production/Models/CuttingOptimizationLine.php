<?php

namespace App\Modules\Production\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Demande d'une optimisation de découpe (ligne de commande à couper). */
class CuttingOptimizationLine extends Model
{
    protected $fillable = [
        'cutting_optimization_id', 'order_reference', 'client', 'product_id',
        'requested_length_m', 'quantity', 'total_m', 'priorite', 'delivery_date',
        'status', 'sort_order',
    ];
    protected $casts = [
        'requested_length_m' => 'decimal:2', 'quantity' => 'integer', 'total_m' => 'decimal:2',
        'delivery_date' => 'date', 'sort_order' => 'integer',
    ];

    public function optimization(): BelongsTo { return $this->belongsTo(CuttingOptimization::class, 'cutting_optimization_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
