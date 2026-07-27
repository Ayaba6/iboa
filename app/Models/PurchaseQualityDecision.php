<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQualityDecision extends Model
{
    protected $fillable = [
        'company_id', 'reception_id', 'reception_item_id', 'coil_id', 'lot_number',
        'type', 'quantity', 'quarantine_before', 'quarantine_after',
        'accepted_before', 'accepted_after', 'criteria', 'reason',
        'requested_by', 'controlled_by', 'approved_by', 'status',
        'replaces_decision_id', 'idempotency_key',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'quarantine_before' => 'decimal:4',
        'quarantine_after'  => 'decimal:4',
        'accepted_before'   => 'decimal:4',
        'accepted_after'    => 'decimal:4',
        'criteria'          => 'array',
    ];

    public function receptionItem(): BelongsTo
    {
        return $this->belongsTo(ReceptionItem::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }
}
