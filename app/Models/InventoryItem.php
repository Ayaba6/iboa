<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_items';

    protected $fillable = [
        'inventory_session_id',
        'product_id',
        'warehouse_id',
        'theoretical_quantity',
        'counted_quantity',
        'variance_quantity',
        'unit_cost',
        'variance_value',
        'notes',
        'counted_at',
        'counted_by',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:4',
        'counted_quantity'     => 'decimal:4',
        'variance_quantity'    => 'decimal:4',
        'unit_cost'            => 'decimal:2',
        'variance_value'       => 'decimal:2',
        'counted_at'           => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function inventorySession(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function product(): BelongsTo
    {
        // withTrashed : les sessions historiques doivent afficher le nom du
        // produit même si l'article a été supprimé depuis (sinon lignes « — »).
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function varianceSign(): string
    {
        if ((float) $this->variance_quantity > 0) return '+';
        return '';
    }
}
