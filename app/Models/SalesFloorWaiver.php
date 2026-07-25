<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesFloorWaiver extends Model
{
    protected $fillable = [
        'company_id', 'document_type', 'document_id', 'line_type', 'line_id',
        'product_id', 'unit_id', 'quantity', 'proposed_price', 'minimum_price',
        'cost_basis', 'cost_source', 'margin_rate', 'expected_margin', 'gap',
        'reason', 'justification_path', 'pricing_signature', 'status',
        'requested_by', 'submitted_at', 'decided_by', 'decided_at',
        'expires_at', 'decision_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:4', 'proposed_price' => 'decimal:2',
        'minimum_price' => 'decimal:2', 'cost_basis' => 'decimal:2',
        'margin_rate' => 'decimal:2', 'expected_margin' => 'decimal:2',
        'gap' => 'decimal:2', 'submitted_at' => 'datetime',
        'decided_at' => 'datetime', 'expires_at' => 'datetime',
    ];
}
