<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Traits\HasCommercialWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow, HasAttachments;

    const DOCUMENT_TYPE = 'quote';

    protected $table = 'quotes';

    // Statuts possibles
    const STATUS_DRAFT     = 'brouillon';
    const STATUS_VALIDATED = 'valide';
    const STATUS_CONVERTED = 'converti';
    const STATUS_EXPIRED   = 'expire';
    const STATUS_REFUSED   = 'refuse';
    const STATUS_CANCELLED = 'annule';

    protected $fillable = [
        'company_id',
        'client_id',
        'fiscal_year_id',
        'number',
        'reference',
        'status',
        'issued_at',
        'expires_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'global_discount_percent',
        'global_discount_amount',
        'notes',
        'terms',
        'footer_note',
        'created_by',
        'validated_by',
        'validated_at',
        'converted_to_order_id',
        'revision_of_id',
        'revision_number',
        'submitted_by',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        // [Maquette Nouveau devis]
        'contact_id', 'warehouse_id', 'sales_rep_id', 'delivery_address', 'validity_duration',
        'project_reference', 'price_list', 'price_mode', 'net_prices',
        'payment_terms', 'payment_method', 'fiscal_representative', 'fiscal_regime', 'default_tax_label',
        'source', 'origin', 'priority', 'desired_delivery_date', 'delivery_location', 'incoterm',
    ];

    protected $casts = [
        'issued_at'               => 'date',
        'expires_at'              => 'date',
        'subtotal_ht'             => 'integer',
        'total_discount'          => 'integer',
        'total_tax'               => 'integer',
        'total_ttc'               => 'integer',
        'global_discount_percent' => 'decimal:2',
        'global_discount_amount'  => 'integer',
        'exchange_rate'           => 'decimal:6',
        'validated_at'            => 'datetime',
        'submitted_at'            => 'datetime',
        'rejected_at'             => 'datetime',
        // [Maquette Nouveau devis]
        'net_prices'              => 'boolean',
        'desired_delivery_date'   => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // [Maquette Nouveau devis]
    public function contact(): BelongsTo { return $this->belongsTo(ClientContact::class, 'contact_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function salesRep(): BelongsTo { return $this->belongsTo(User::class, 'sales_rep_id'); }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_to_order_id');
    }

    /** Devis d'origine que cette version révise. */
    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    /** Révisions créées à partir de ce devis. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id');
    }

    /** Une révision non annulée/refusée remplace ce devis. */
    public function hasActiveRevision(): bool
    {
        return $this->revisions()->whereNotIn('status', ['annule', 'refuse'])->exists();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday();
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === 'valide';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'            => 'Brouillon',
            'en_attente_validation'=> 'En attente de validation',
            'envoye'               => 'Envoyé',
            'valide'               => $this->is_expired ? 'Expiré' : 'Validé',
            'converti'             => 'Converti',
            'expire'               => 'Expiré',
            'refuse'               => 'Refusé',
            'annule'               => 'Annulé',
            default                => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon'            => 'gray',
            'en_attente_validation'=> 'yellow',
            'envoye'               => 'blue',
            'valide'               => $this->is_expired ? 'orange' : 'green',
            'converti'             => 'green',
            'expire'               => 'orange',
            'refuse'               => 'red',
            'annule'               => 'red',
            default                => 'gray',
        };
    }

    protected function getValidatedStatuses(): array
    {
        return ['envoye', 'accepte', 'converti'];
    }
}
