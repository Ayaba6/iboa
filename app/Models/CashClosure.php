<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Clôture journalière de caisse.
 */
class CashClosure extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id',
        'cash_account_id',
        'number',
        // [Parité SAGE X3]
        'site', 'cashier_name', 'supervisor_name', 'currency_code',
        'closure_date',
        'theoretical_balance',
        'counted_balance',
        'difference',
        'denominations',
        'status',
        'difference_reason',
        'notes',
        'journal_entry_id',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'closure_date'        => 'date',
        'theoretical_balance' => 'integer',
        'counted_balance'     => 'integer',
        'difference'          => 'integer',
        'denominations'       => 'array',
        'validated_at'        => 'datetime',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isValidatable(): bool
    {
        return $this->status === 'brouillon';
    }

    public function hasDifference(): bool
    {
        return (int) $this->difference !== 0;
    }
}
