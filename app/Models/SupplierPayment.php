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

class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasAttachments, HasCompanyScope;

    protected $table = 'supplier_payments';

    protected $fillable = [
        'company_id',
        'supplier_id',
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
        'payment_object',
        'cost_center',
        'analytic_section',
        'project',
        'site',
        'phone_number',
        'status',
        'notes',
        'observations',
        'allocated_amount',
        'unallocated_amount',
        'created_by',
        'journal_entry_id',   // [AUDIT-ERP-A]
        // [TRESO] Workflow validation décaissement
        'validation_status',
        'required_role',
        'pending_allocations',
        'submitted_by',
        'submitted_at',
        'validated_by',
        'validated_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'payment_date'        => 'date',
        'value_date'          => 'date',
        'amount'              => 'integer',
        'bank_fees'           => 'integer',
        'net_amount'          => 'integer',
        'allocated_amount'    => 'integer',
        'unallocated_amount'  => 'integer',
        'exchange_rate'       => 'decimal:6',
        'pending_allocations' => 'array',
        'submitted_at'        => 'datetime',
        'validated_at'        => 'datetime',
        'rejected_at'         => 'datetime',
    ];

    // [TRESO] Helpers workflow
    public function isPendingValidation(): bool
    {
        return $this->validation_status === 'en_attente_validation';
    }

    public function isValidated(): bool
    {
        return $this->validation_status === 'valide';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** [AUDIT-ERP-B] Écriture comptable générée lors du paiement fournisseur. */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
