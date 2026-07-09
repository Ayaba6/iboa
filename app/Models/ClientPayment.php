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

class ClientPayment extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasAttachments, HasCompanyScope;

    protected $table = 'client_payments';

    protected $fillable = [
        'company_id',
        'client_id',
        'cash_account_id',
        'payment_method_id',
        'number',
        'amount',
        'bank_fees',
        'net_amount',
        'currency_code',
        'exchange_rate',
        'payment_date',
        'value_date',
        'reference',
        'piece_number',
        'bank_reference',
        'treasury_journal',
        'payment_condition',
        'cost_center',
        'analytic_section',
        'project',
        'salesperson',
        'site',
        'phone_number',
        'status',
        'notes',
        'observations',
        'allocated_amount',
        'unallocated_amount',
        'is_acompte',
        'created_by',
        'journal_entry_id',   // [AUDIT-ERP-A]
    ];

    protected $casts = [
        'payment_date'       => 'date',
        'value_date'         => 'date',
        'amount'             => 'integer',
        'bank_fees'          => 'integer',
        'net_amount'         => 'integer',
        'allocated_amount'   => 'integer',
        'unallocated_amount' => 'integer',
        'exchange_rate'      => 'decimal:6',
        'is_acompte'         => 'boolean',
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

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ClientPaymentAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** [AUDIT-ERP-B] Écriture comptable générée lors de l'encaissement. */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
