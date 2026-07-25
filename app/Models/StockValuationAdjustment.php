<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockValuationAdjustment extends Model
{
    protected $fillable = [
        'company_id', 'production_order_id', 'original_movement_id',
        'adjustment_movement_id', 'warehouse_id', 'quantity', 'old_unit_cost',
        'new_unit_cost', 'value_delta', 'reason', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'old_unit_cost' => 'decimal:2',
        'new_unit_cost' => 'decimal:2',
        'value_delta' => 'decimal:2',
    ];
}
