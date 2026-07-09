<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [Maquette X3] Ligne contractuelle d'un contrat commercial.
class CommercialContractItem extends Model
{
    protected $fillable = [
        'commercial_contract_id', 'product_id', 'designation', 'unit',
        'quantity', 'unit_price', 'discount_percent', 'amount_ht',
        'starts_at', 'ends_at', 'warehouse_id', 'status', 'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'amount_ht'        => 'decimal:2',
        'starts_at'        => 'date',
        'ends_at'          => 'date',
        'sort_order'       => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CommercialContract::class, 'commercial_contract_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
