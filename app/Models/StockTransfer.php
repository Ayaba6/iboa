<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [STOCK-PRO] Transfert inter-dépôts.
 *
 * Cycle de vie :
 *   brouillon ──ship──▶ en_transit ──receive──▶ recu
 *        │                  │
 *        └──── cancel ──────┴────────────────── annule
 */
class StockTransfer extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope, HasCreator;

    protected $table = 'stock_transfers';

    protected $fillable = [
        'company_id', 'number',
        'from_warehouse_id', 'to_warehouse_id',
        'status', 'transfer_date',
        'shipped_at', 'received_at',
        'created_by', 'shipped_by', 'received_by', 'cancelled_by',
        'reason', 'notes',
        // [Maquette X3]
        'transfer_time', 'type', 'priority', 'currency_code', 'reference',
        'source_document_date', 'responsible_id', 'carrier', 'transport_mode',
        'vehicle', 'driver', 'planned_date', 'planned_time', 'transport_cost',
        'grouping', 'packages_count', 'total_weight', 'total_volume',
        'controlled_by', 'controlled_at', 'validated_by', 'validated_at', 'validation_status',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'shipped_at'    => 'datetime',
        'received_at'   => 'datetime',
        'source_document_date' => 'date',
        'planned_date'  => 'date',
        'controlled_at' => 'datetime',
        'validated_at'  => 'datetime',
        'grouping'      => 'boolean',
        'transport_cost'=> 'decimal:2',
    ];

    // ─────────────── Relations ───────────────
    public function company(): BelongsTo            { return $this->belongsTo(Company::class); }
    public function fromWarehouse(): BelongsTo      { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse(): BelongsTo        { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function items(): HasMany                { return $this->hasMany(StockTransferItem::class)->orderBy('sort_order'); }
    public function createdBy(): BelongsTo          { return $this->belongsTo(User::class, 'created_by'); }
    public function shippedBy(): BelongsTo          { return $this->belongsTo(User::class, 'shipped_by'); }
    public function receivedBy(): BelongsTo         { return $this->belongsTo(User::class, 'received_by'); }
    public function cancelledBy(): BelongsTo        { return $this->belongsTo(User::class, 'cancelled_by'); }

    // ─────────────── Helpers ───────────────
    public function isDraft(): bool        { return $this->status === 'brouillon'; }
    public function isInTransit(): bool    { return $this->status === 'en_transit'; }
    public function isReceived(): bool     { return $this->status === 'recu'; }
    public function isCancelled(): bool    { return $this->status === 'annule'; }

    public function canShip(): bool        { return $this->isDraft(); }
    public function canReceive(): bool     { return $this->isInTransit(); }
    public function canCancel(): bool      { return in_array($this->status, ['brouillon', 'en_transit']); }
    public function canEdit(): bool        { return $this->isDraft(); }

    public function statusLabel(): string
    {
        return [
            'brouillon'  => 'Brouillon',
            'en_transit' => 'En transit',
            'recu'       => 'Reçu',
            'annule'     => 'Annulé',
        ][$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return [
            'brouillon'  => 'gray',
            'en_transit' => 'amber',
            'recu'       => 'emerald',
            'annule'     => 'red',
        ][$this->status] ?? 'gray';
    }

    public function totalQuantity(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function hasDiscrepancy(): bool
    {
        return $this->isReceived() && $this->items->some(fn($i) =>
            $i->received_quantity !== null && (float) $i->received_quantity !== (float) $i->quantity
        );
    }
}
