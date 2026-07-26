<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'description',
        'unit_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_rate_id',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'received_quantity',
        'accepted_quantity',
        'invoiced_quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'unit_price'        => 'integer',
        'discount_percent'  => 'decimal:2',
        'tax_rate_value'    => 'decimal:2',
        'line_total_ht'     => 'integer',
        'line_tax'          => 'integer',
        'line_total_ttc'    => 'integer',
        'received_quantity' => 'decimal:4',
        'invoiced_quantity' => 'decimal:4',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
