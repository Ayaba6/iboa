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

class DeliveryNote extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow, HasAttachments;

    const DOCUMENT_TYPE = 'delivery_note';

    protected $table = 'delivery_notes';

    protected $fillable = [
        'company_id',
        'client_id',
        'order_id',
        'number',
        'issued_at',
        'status',
        'warehouse_id',
        'delivery_address',
        'carrier',
        'shipping_mode',
        'incoterm',
        'weight_kg',
        'packages_count',
        'expected_delivery_at',
        'delivery_contact',
        'tracking_number',
        'notes',
        'created_by',
        'validated_by',
        'validated_at',
        'currency_code',
        'total_quantity',
        'submitted_by',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'issued_at'            => 'date',
        'validated_at'         => 'datetime',
        'submitted_at'         => 'datetime',
        'rejected_at'          => 'datetime',
        'expected_delivery_at' => 'date',
        'total_quantity'       => 'decimal:4',
        'weight_kg'            => 'decimal:3',
        'packages_count'       => 'integer',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
        return $this->hasMany(DeliveryNoteItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'delivery_note_id');
    }

    /** [AUDIT-ERP-C] Mouvements de stock générés par ce bon de livraison. */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
                    ->where('reference_type', 'delivery_note');
    }

    // ── Accessors workflow ────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'Brouillon',
            'en_attente_validation' => 'En attente de validation',
            'valide'                => 'Validé',
            'livre'                 => 'Livré',
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
            'livre'                 => 'teal',
            'annule'                => 'red',
            default                 => 'gray',
        };
    }

    protected function getValidatedStatuses(): array
    {
        return ['valide', 'livre'];
    }
}
