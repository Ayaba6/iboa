<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceptionItem extends Model
{
    protected $table = 'reception_items';

    protected $fillable = [
        'reception_id',
        'purchase_order_item_id',
        'product_id',
        'description',
        'unit_id',
        'expected_quantity',
        'received_quantity',
        'rejected_quantity',
        'accepted_quantity',
        'quarantine_quantity',
        'disposition_origin',
        'unit_cost',
        'lot_number',
        'expiry_date',
        'quality_status',
    ];

    protected $casts = [
        'expected_quantity'   => 'decimal:4',
        'received_quantity'   => 'decimal:4',
        'rejected_quantity'   => 'decimal:4',
        'accepted_quantity'   => 'decimal:4',
        'quarantine_quantity' => 'decimal:4',
        'unit_cost'           => 'integer',
        'expiry_date'         => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
