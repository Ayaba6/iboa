<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-09] Ligne de dépense d'une note de frais.
 */
class ExpenseLine extends Model
{
    protected $fillable = [
        'expense_report_id', 'sort_order', 'expense_date', 'category', 'description', 'amount', 'tax_amount', 'has_receipt',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'expense_date' => 'date',
        'amount'      => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'has_receipt' => 'boolean',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class, 'expense_report_id');
    }
}
