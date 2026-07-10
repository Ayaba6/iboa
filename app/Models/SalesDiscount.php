<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [Paramétrage Vente] Remise commerciale paramétrable.
class SalesDiscount extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'name', 'discount_type', 'client_id', 'client_group',
        'client_category', 'product_id', 'product_family_id', 'rate_percent',
        'amount', 'min_quantity', 'cap_amount', 'starts_at', 'ends_at',
        'requires_validation', 'is_active', 'created_by',
    ];

    protected $casts = [
        'rate_percent'        => 'decimal:2',
        'amount'              => 'decimal:2',
        'min_quantity'        => 'decimal:3',
        'cap_amount'          => 'decimal:2',
        'starts_at'           => 'date',
        'ends_at'             => 'date',
        'requires_validation' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    /** Remises actives et valides à une date donnée. */
    public function scopeValidOn(Builder $q, $date): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $date));
    }
}
