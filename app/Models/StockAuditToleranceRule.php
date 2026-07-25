<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditToleranceRule extends Model
{
    protected $fillable = [
        'company_id',
        'item_category_id',
        'product_id',
        'warehouse_id',
        'absolute_tolerance_qty',
        'tolerance_unit',
        'relative_tolerance_percent',
        'selection_mode',
        'effective_at',
        'status',
        'notes',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'absolute_tolerance_qty' => 'decimal:4',
        'relative_tolerance_percent' => 'decimal:4',
        'effective_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
