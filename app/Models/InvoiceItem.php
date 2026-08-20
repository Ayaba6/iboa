<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'delivery_note_item_id',
        // [Ventes §21.3] Rattachement de ligne à ligne avec la commande. Sans lui,
        // l'annulation d'une facture retrouvait la ligne d'origine par produit et
        // décrémentait toutes les lignes portant cet article.
        'order_item_id',
        'product_id',
        'description',
        'unit_id',
        'quantity',
        'nb_toles',
        'metrage_par_tole',
        'unit_price',
        'unit_cost',
        'discount_percent',
        'tax_rate_id',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'integer',
        'unit_cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_rate_value' => 'decimal:2',
        'line_total_ht' => 'integer',
        'line_tax' => 'integer',
        'line_total_ttc' => 'integer',
        'sort_order' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deliveryNoteItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItem::class);
    }

    /** [Ventes §21.3] Ligne de commande d'origine — nulle sur l'historique ambigu. */
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

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
