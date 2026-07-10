<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'supplier_returns';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'reception_id',
        'supplier_invoice_id',
        'number',
        'status',
        'reason',
        'returned_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_tax',
        'total_ttc',
        'notes',
        // [Maquette Retour fournisseur]
        'return_type', 'warehouse_id', 'priority', 'project_reference',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'returned_at'  => 'date',
        'validated_at' => 'datetime',
        'subtotal_ht'  => 'integer',
        'total_tax'    => 'integer',
        'total_ttc'    => 'integer',
        'exchange_rate'=> 'decimal:6',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === 'brouillon';
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'brouillon'        => 'Brouillon',
            'valide'           => 'Validé',
            'envoye'           => 'Envoyé',
            'recu_fournisseur' => 'Reçu fournisseur',
            'annule'           => 'Annulé',
            default            => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'brouillon'        => 'gray',
            'valide'           => 'blue',
            'envoye'           => 'indigo',
            'recu_fournisseur' => 'green',
            'annule'           => 'red',
            default            => 'gray',
        };
    }
}
