<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends Model
{
    protected $table = 'delivery_note_items';

    protected $fillable = [
        'delivery_note_id',
        'order_item_id',
        'product_id',
        'description',
        'unit_id',
        'quantity',
        'nb_toles',
        'metrage_par_tole',
        'unit_price',
        'lot_number',
        'stock_lot_id',
        'serial_number',
        'expiry_date',
        'sort_order',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_price'  => 'integer',
        'expiry_date' => 'date',
        'sort_order'  => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** [Décision 23/07] Lot de stock formellement livré par cette ligne. */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
