<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// [Maquette X3] Contrat commercial (vente/achat) avec lignes contractuelles.
class CommercialContract extends Model
{
    use HasAttachments, HasCompanyScope, SoftDeletes;

    protected $fillable = [
        'company_id', 'number', 'contract_type', 'client_id', 'supplier_id',
        'description', 'currency_code', 'sales_rep_id', 'contract_date',
        'starts_at', 'ends_at', 'is_framework', 'status', 'priority',
        'project_reference', 'category', 'payment_terms', 'incoterm',
        'warehouse_id', 'billing_currency', 'client_contact', 'supplier_contact',
        'transport_mode', 'validity_days', 'observations', 'total_ht', 'created_by',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'starts_at'     => 'date',
        'ends_at'       => 'date',
        'is_framework'  => 'boolean',
        'total_ht'      => 'decimal:2',
        'validity_days' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CommercialContractItem::class)->orderBy('sort_order');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
