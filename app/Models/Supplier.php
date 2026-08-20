<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SupplierInvoice;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'suppliers';

    protected $fillable = [
        'site_id',
        'code',
        'type',
        'civility',
        'name',
        'trade_name',
        'phone',
        'phone2',
        'mobile',
        'email',
        'website',
        'boite_postale',
        'address',
        'address_line2',
        'postal_code',
        'city',
        'quartier',
        'region',
        'gps_lat',
        'gps_lng',
        'country',
        'canal',
        'famille_tarifaire',
        'ifu',
        'numero_contribuable',
        'rccm',
        'category',
        'groupe_fournisseur',
        'secteur_activite',
        'currency',
        'language',
        'tax_rate_id',
        'default_discount',
        'payment_mode',
        'payment_days',
        'credit_limit',
        'compte_collectif',
        'soumis_tva',
        'blocage_achat',
        'depot_reception_id',
        'mode_livraison',
        'transporteur',
        'delai_livraison',
        'compte_tiers',
        'condition_paiement',
        'echeance',
        'banque',
        'rib_iban',
        'numero_compte',
        'swift',
        'rating',
        'avg_delivery_days',
        'balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rating'           => 'decimal:1',
        'balance'          => 'integer',
        'is_active'        => 'boolean',
        'soumis_tva'       => 'boolean',
        'blocage_achat'    => 'boolean',
        'default_discount' => 'decimal:2',
        'credit_limit'     => 'decimal:2',
        'gps_lat'          => 'decimal:6',
        'gps_lng'          => 'decimal:6',
        'payment_days'     => 'integer',
        'delai_livraison'  => 'integer',
    ];

    public function site(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Warehouse::class, 'site_id'); }
    public function depotReception(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_reception_id'); }
    public function taxRate(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function purchaseConditions(): HasMany
    {
        return $this->hasMany(SupplierPurchaseCondition::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * Recalculate and persist the supplier's outstanding balance.
     * Balance = sum of remaining_amount on validated (confirmed payable) supplier invoices.
     * Only 'validee' and 'partiellement_payee' are real obligations; brouillon/recue
     * have not been confirmed yet and must not inflate the balance.
     * Call this after any event that modifies supplier invoice amounts or statuses.
     */
    public function recalculateBalance(): void
    {
        // [FIX-SOLDES-02] Only count invoices that are legally confirmed payables.
        $outstanding = $this->invoices()
            ->whereIn('status', ['validee', 'partiellement_payee'])
            ->sum('remaining_amount');

        $this->update(['balance' => (int) $outstanding]);
    }
}
