<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasCompanyScope;

    protected $table = 'commissions';

    protected $fillable = [
        'company_id',
        'sales_rep_id',
        'client_id',
        'payment_id',
        'base_amount',
        'commission_rate',
        'commission_amount',
        'period',
        'status',
        'notes',
    ];

    protected $casts = [
        'base_amount'       => 'decimal:2',
        'commission_rate'   => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ClientPayment::class, 'payment_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function statusOptions(): array
    {
        return [
            'calculee' => 'Calculée',
            'validee'  => 'Validée',
            'payee'    => 'Payée',
        ];
    }
}
