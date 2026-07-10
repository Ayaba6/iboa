<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    use HasFactory;

    protected $table = 'product_price_tiers';

    protected $fillable = [
        'product_id',
        'client_id',
        'client_category',
        'unit_id',
        'famille_tarifaire',
        'label',
        'price',
        'discount_percent',
        'margin_min_percent',
        'starts_at',
        'ends_at',
        'min_quantity',
        'is_active',
    ];

    protected $casts = [
        'price'            => 'integer',
        'discount_percent' => 'decimal:2',
        'is_active'        => 'boolean',
        'starts_at'        => 'date',
        'ends_at'          => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
