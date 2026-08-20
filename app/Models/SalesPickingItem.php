<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [Ventes §11] Ligne de préparation — modèle des quantités SÉPARÉES.
 *
 * Jamais un champ unique pour représenter réservation, prélèvement et
 * validation : chaque étape a sa colonne, l'écart est explicite et motivé.
 *
 * Invariant central (vérifié par le service et par l'audit) :
 *
 *   qty_validated ≤ qty_picked ≤ qty_allocated ≤ qty_remaining_snapshot
 */
class SalesPickingItem extends Model
{
    protected $fillable = [
        'sales_picking_id', 'order_item_id', 'product_id', 'unit_id',
        'qty_ordered', 'qty_previously_delivered', 'qty_cancelled',
        'qty_remaining_snapshot', 'qty_reserved', 'qty_allocated',
        'qty_picked', 'qty_controlled', 'qty_validated',
        'variance_qty', 'variance_reason',
    ];

    protected $casts = [
        'qty_ordered' => 'float',
        'qty_previously_delivered' => 'float',
        'qty_cancelled' => 'float',
        'qty_remaining_snapshot' => 'float',
        'qty_reserved' => 'float',
        'qty_allocated' => 'float',
        'qty_picked' => 'float',
        'qty_controlled' => 'float',
        'qty_validated' => 'float',
        'variance_qty' => 'float',
    ];

    public function picking(): BelongsTo
    {
        return $this->belongsTo(SalesPicking::class, 'sales_picking_id');
    }

    /**
     * [BUG-A3-SALES-LINE-IMMUTABLE-012] `withTrashed()` VOLONTAIRE.
     *
     * Ce document est historique : il atteste ce qui a été préparé, livré ou
     * facturé. Si la ligne de commande d'origine est retirée par la suite,
     * Eloquent masquerait l'enregistrement et la relation rendrait `null` —
     * une clé étrangère intacte en base, mais un lien invisible dans le code.
     *
     * Ne PAS reproduire ce `withTrashed()` sur les relations qui calculent la
     * commande active : celles-là doivent exclure les lignes retirées.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SalesPickingAllocation::class, 'sales_picking_item_id');
    }
}
