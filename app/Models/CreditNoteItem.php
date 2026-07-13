<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $table = 'credit_note_items';

    /** [VEN Retour] Sort des biens retournés. */
    public const DISPOSITIONS = [
        'restock' => 'Remis en stock',
        'rebut'   => 'Rebut (non remis en stock)',
    ];

    protected $fillable = [
        'credit_note_id',
        'product_id',
        'disposition',
        'description',
        'unit_id',
        'quantity',
        'unit_price',
        'tax_rate_id',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:4',
        'unit_price'     => 'integer',
        'tax_rate_value' => 'decimal:2',
        'line_total_ht'  => 'integer',
        'line_tax'       => 'integer',
        'line_total_ttc' => 'integer',
        'sort_order'     => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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
