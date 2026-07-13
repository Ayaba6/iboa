<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Traits\HasCommercialWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow;

    const DOCUMENT_TYPE = 'credit_note';

    protected $table = 'credit_notes';

    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'number',
        'status',
        'issued_at',
        'reason',
        'is_replacement',
        'replacement_delivery_id',
        'currency_code',
        'subtotal_ht',
        'total_tax',
        'total_ttc',
        'applied_amount',
        'remaining_credit',
        'notes',
        'created_by',
        'validated_by',
        'validated_at',
        'submitted_by',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'issued_at'        => 'date',
        'is_replacement'   => 'boolean',
        'subtotal_ht'      => 'integer',
        'total_tax'        => 'integer',
        'total_ttc'        => 'integer',
        'applied_amount'   => 'integer',
        'remaining_credit' => 'integer',
        'validated_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'rejected_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** [VEN Retour] BL de remplacement généré depuis cet avoir. */
    public function replacementDelivery(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'replacement_delivery_id');
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
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order');
    }

    // [FIX-MINEUR] Missing inverse relation for payment allocations
    public function allocations(): HasMany
    {
        return $this->hasMany(ClientPaymentAllocation::class, 'credit_note_id');
    }

    // ── Accessors workflow ────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'Brouillon',
            'en_attente_validation' => 'En attente de validation',
            'valide'                => 'Validé',
            'applique'              => 'Appliqué',
            'annule'                => 'Annulé',
            default                 => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'gray',
            'en_attente_validation' => 'yellow',
            'valide'                => 'green',
            'applique'              => 'teal',
            'annule'                => 'red',
            default                 => 'gray',
        };
    }

    protected function getValidatedStatuses(): array
    {
        return ['valide', 'applique'];
    }
}
