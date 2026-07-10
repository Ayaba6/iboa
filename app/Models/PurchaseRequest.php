<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'company_id',
        'requested_by',
        'approved_by',
        'purchase_order_id',
        'number',
        'department',
        'justification',
        'status',
        'needed_at',
        'notes',
        'rejection_reason',
        'total_estimated',
        // [Maquette Demande d'achat]
        'priority', 'project_reference', 'warehouse_id',
        'submitted_at',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'needed_at'    => 'date',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'total_estimated' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return in_array($this->status, ['brouillon', 'rejete']);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'brouillon';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'soumis';
    }

    public function canBeConverted(): bool
    {
        return $this->status === 'approuve';
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'brouillon' => 'Brouillon',
            'soumis'    => 'Soumis',
            'approuve'  => 'Approuvé',
            'rejete'    => 'Rejeté',
            'converti'  => 'Converti',
            'annule'    => 'Annulé',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'brouillon' => 'gray',
            'soumis'    => 'blue',
            'approuve'  => 'green',
            'rejete'    => 'red',
            'converti'  => 'indigo',
            'annule'    => 'gray',
            default     => 'gray',
        };
    }
}
