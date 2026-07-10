<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'name',
        'code',
        'type',
        'provider',
        'is_mobile_money',
        'requires_reference',
        'attachment_required',
        'cash_account_id',
        'journal_code',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_mobile_money'    => 'boolean',
        'requires_reference' => 'boolean',
        'is_active'          => 'boolean',
        'sort_order'         => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function clientPayments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class);
    }
}
