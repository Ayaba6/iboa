<?php

namespace App\Models;

use App\Modules\Production\Models\Coil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [Ventes §12] Allocation d'une ligne de préparation à un lot / une bobine /
 * un dépôt précis, au coût HISTORIQUE figé.
 *
 * Interdictions appliquées par SalesPickingService à la création :
 *   - lot en quarantaine ou non libéré ;
 *   - bobine non libérée ou bobine mère divisée ;
 *   - lot non valorisé quand la règle l'exige ;
 *   - quantité supérieure au disponible non réservé ;
 *   - dépôt différent de celui du bon.
 */
class SalesPickingAllocation extends Model
{
    public const STATUS_ALLOUEE = 'allouee';

    public const STATUS_PRELEVEE = 'prelevee';

    public const STATUS_ANNULEE = 'annulee';

    protected $fillable = [
        'sales_picking_item_id', 'stock_lot_id', 'coil_id', 'warehouse_id',
        'location_id', 'quantity', 'unit_id', 'conversion_snapshot',
        'historical_unit_cost', 'stock_reservation_id', 'status',
    ];

    protected $casts = [
        'quantity' => 'float',
        'historical_unit_cost' => 'float',
        'conversion_snapshot' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesPickingItem::class, 'sales_picking_item_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function coil(): BelongsTo
    {
        return $this->belongsTo(Coil::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
