<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [STO-12] Perte / casse de stock déclarée.
 */
class StockLoss extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'reference', 'product_id', 'warehouse_id', 'quantity', 'lot_number',
        'type', 'cause', 'photo_path', 'responsible_id', 'unit_cost', 'estimated_value',
        'status', 'reject_reason', 'declared_by', 'validated_by', 'validated_at', 'notes',
    ];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'unit_cost'       => 'decimal:2',
        'estimated_value' => 'decimal:2',
        'validated_at'    => 'date',
    ];

    public const TYPES = [
        'casse'         => 'Casse',
        'perte'         => 'Perte',
        'vol'           => 'Vol',
        'peremption'    => 'Péremption',
        'deterioration' => 'Détérioration',
        'autre'         => 'Autre',
    ];

    public const STATUSES = [
        'declaree' => 'Déclarée',
        'validee'  => 'Validée',
        'rejetee'  => 'Rejetée',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
