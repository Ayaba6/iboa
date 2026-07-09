<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    use HasFactory, HasCreator, HasCompanyScope, HasAttachments;

    protected $table = 'inventory_sessions';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'number',
        'type',
        'status',
        'notes',
        'started_at',
        'closed_at',
        'validated_at',
        'validated_by',
        'created_by',
        // [PARITÉ SAGE X3]
        'site', 'responsible', 'counting_type', 'counting_method', 'valuation_method',
        'valuation_currency', 'currency_code', 'freeze_stock', 'include_lots',
        'include_locations', 'location_scope', 'article_scope', 'comment',
    ];

    protected $casts = [
        'freeze_stock'      => 'boolean',
        'include_lots'      => 'boolean',
        'include_locations' => 'boolean',
        'started_at'        => 'datetime',
        'closed_at'         => 'datetime',
        'validated_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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
        return $this->hasMany(InventoryItem::class, 'inventory_session_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return in_array($this->status, ['ouvert', 'en_cours']);
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'en_cours';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ouvert'   => 'Ouvert',
            'en_cours' => 'En cours',
            'valide'   => 'Validé',
            'annule'   => 'Annulé',
            default    => $this->status,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'tournant' => 'Tournant',
            'annuel'   => 'Annuel',
            'complet'  => 'Complet',
            default    => $this->type ?? 'Complet',
        };
    }
}
