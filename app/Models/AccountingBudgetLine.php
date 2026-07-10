<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBudgetLine extends Model
{
    protected $fillable = [
        'accounting_budget_id', 'account_id', 'cost_center', 'axe',
        'initial_amount', 'revised_amount', 'committed_amount',
    ];

    protected $casts = [
        'initial_amount'   => 'integer',
        'revised_amount'   => 'integer',
        'committed_amount' => 'integer',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(AccountingBudget::class, 'accounting_budget_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
