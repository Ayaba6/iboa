<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNoteItem extends Model
{
    protected $table = 'delivery_note_items';

    protected $fillable = [
        'delivery_note_id',
        'order_item_id',
        // [Ventes §4.3] Ligne de préparation validée dont ce BL provient.
        // NULL = aucune préparation (flux direct ou BL historique), jamais
        // « préparation inconnue ».
        'sales_picking_item_id',
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
        'quantity' => 'decimal:4',
        'unit_price' => 'integer',
        'expiry_date' => 'date',
        'sort_order' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Allocations de coût et de traçabilité réellement sorties pour cette ligne. */
    public function lotAllocations(): HasMany
    {
        return $this->hasMany(DeliveryNoteItemLotAllocation::class);
    }

    /** [Décision 23/07] Lot de stock formellement livré par cette ligne. */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
