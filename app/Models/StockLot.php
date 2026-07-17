<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    protected $table = 'stock_lots';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'lot_number',
        'supplier_lot_number',
        'serial_number',
        'expiry_date',
        'quantity',
        'initial_quantity',
        'reserved_quantity',
        'stock_uom',
        'kg_per_linear_meter',
        'unit_cost',
        'received_at',
        'source_type',
        'source_id',
        'created_by',
        'status',
    ];

    protected $casts = [
        'expiry_date'  => 'date',
        'received_at'  => 'date',
        'quantity'     => 'decimal:4',
        'unit_cost'    => 'decimal:0',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('status', 'disponible');
    }

    /** Lots expiring within $days days (still available). */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'disponible')
                     ->whereNotNull('expiry_date')
                     ->where('expiry_date', '>=', now()->toDateString())
                     ->where('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '<', now()->toDateString())
                     ->where('status', 'disponible');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /** Days until expiry. Negative = already expired. Null = no expiry date. */
    public function daysUntilExpiry(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'disponible' => 'Disponible',
            'reserve'    => 'Réservé',
            'expire'     => 'Expiré',
            'consomme'   => 'Consommé',
            default      => $this->status,
        };
    }
}
