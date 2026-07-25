<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItemLotReturn extends Model
{
    protected $fillable = [
        'credit_note_item_id', 'delivery_allocation_id', 'source_stock_lot_id',
        'returned_stock_lot_id', 'warehouse_id', 'quantity', 'unit_cost_snapshot',
        'total_cost', 'stock_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function deliveryAllocation(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItemLotAllocation::class, 'delivery_allocation_id');
    }
}
