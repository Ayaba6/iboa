<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'label',
        'partner_name',
        'cost_center',
        'tax_code',
        'debit',
        'credit',
        'due_date',
        'reconciliation_ref',
        'sort_order',
        'lettered_at',
        'lettered_by',
    ];

    protected $casts = [
        'debit'        => 'integer',
        'credit'       => 'integer',
        'due_date'     => 'date',
        'lettered_at'  => 'datetime',
    ];

    /** La ligne est lettrée si reconciliation_ref est renseigné. */
    public function isLettered(): bool
    {
        return !empty($this->reconciliation_ref);
    }

    /** Solde de la ligne (débit positif, crédit négatif). */
    public function getBalanceAttribute(): int
    {
        return $this->debit - $this->credit;
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function letteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lettered_by');
    }
}
