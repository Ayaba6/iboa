<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $table = 'stock_transfer_items';

    protected $fillable = [
        'stock_transfer_id', 'product_id', 'quantity', 'received_quantity',
        'unit_cost', 'lot_number', 'serial_number', 'expiry_date',
        'label', 'sort_order', 'requested_quantity', 'weight', 'volume',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'expiry_date'       => 'date',
    ];

    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }

    public function hasDiscrepancy(): bool
    {
        return $this->received_quantity !== null && (float) $this->received_quantity !== (float) $this->quantity;
    }
}
