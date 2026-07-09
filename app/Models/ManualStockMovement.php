<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// [Maquette X3] Entête d'un mouvement manuel de stock — les lignes sont des
// StockMovement (reference_type = mouvement_manuel, reference_id = id).
class ManualStockMovement extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'number', 'movement_type', 'occurred_at', 'occurred_time',
        'status', 'currency_code', 'warehouse_from_id', 'warehouse_to_id',
        'location_from', 'location_to', 'reason', 'comment',
        'project_reference', 'analytic_section', 'accounting_date',
        'is_blocked', 'lines', 'unblocked_at', 'unblocked_by', 'total_value', 'created_by',
    ];

    protected $casts = [
        'occurred_at'     => 'date',
        'accounting_date' => 'date',
        'is_blocked'      => 'boolean',
        'lines'           => 'array',
        'unblocked_at'    => 'datetime',
        'total_value'     => 'decimal:2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'mouvement_manuel');
    }

    public function warehouseFrom(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_from_id');
    }

    public function warehouseTo(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_to_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
