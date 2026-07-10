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
 * [TRESO] Opération diverse de caisse (entrée/sortie manuelle).
 */
class CashOperation extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id',
        'cash_account_id',
        'number',
        'direction',
        'category',
        'amount',
        'operation_date',
        'label',
        'status',
        'journal_entry_id',
        'created_by',
        // [PARITÉ SAGE X3]
        'site', 'operation_type', 'reference', 'requester', 'cashier_name',
        'currency_code', 'exchange_rate', 'fees', 'net_amount', 'value_date',
        'general_account', 'counterpart_account', 'cost_center', 'analytic_section',
        'payment_method', 'comment', 'lines',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'operation_date' => 'date',
        // [PARITÉ SAGE X3]
        'value_date'     => 'date',
        'fees'           => 'integer',
        'net_amount'     => 'integer',
        'exchange_rate'  => 'decimal:6',
        'lines'          => 'array',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isCancellable(): bool
    {
        return $this->status === 'valide';
    }

    public function directionLabel(): string
    {
        return $this->direction === 'entree' ? 'Entrée' : 'Sortie';
    }
}
