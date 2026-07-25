<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItemLotAllocation extends Model
{
    protected $fillable = [
        'delivery_note_item_id', 'stock_lot_id', 'warehouse_id', 'location_id',
        'quantity', 'unit_cost_snapshot', 'total_cost', 'stock_movement_id',
        'allocated_by', 'allocated_at', 'reversed_at', 'reversed_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'allocated_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function deliveryNoteItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItem::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
