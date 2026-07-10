<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'description',
        'unit_id',
        'quantity',
        'nb_toles',
        'metrage_par_tole',
        'unit_price',
        'discount_percent',
        'tax_rate_id',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'delivered_quantity',
        'invoiced_quantity',
        'warehouse_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity'           => 'decimal:4',
        'unit_price'         => 'integer',
        'discount_percent'   => 'decimal:2',
        'tax_rate_value'     => 'decimal:2',
        'line_total_ht'      => 'integer',
        'line_tax'           => 'integer',
        'line_total_ttc'     => 'integer',
        'delivered_quantity' => 'decimal:4',
        'invoiced_quantity'  => 'decimal:4',
        'sort_order'         => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
