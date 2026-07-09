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
 * [TRESO] Demande de paiement.
 */
class PaymentRequest extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id', 'supplier_id', 'payment_method_id', 'supplier_invoice_id',
        'number', 'internal_reference', 'object', 'beneficiary', 'amount', 'due_date', 'priority',
        'site', 'service', 'request_type', 'amount_ht', 'tax_amount', 'misc_fees', 'net_amount',
        'bank_account', 'cost_center', 'analytic_section',
        'status', 'required_role', 'supplier_payment_id', 'notes',
        'requested_by', 'submitted_at', 'validated_by', 'validated_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'amount_ht'    => 'integer',
        'tax_amount'   => 'integer',
        'misc_fees'    => 'integer',
        'net_amount'   => 'integer',
        'due_date'     => 'date',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at'  => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ── Helpers workflow ──────────────────────────────────────────────────────
    public function isEditable(): bool   { return $this->status === 'brouillon'; }
    public function isSubmittable(): bool { return $this->status === 'brouillon'; }
    public function isValidatable(): bool { return $this->status === 'soumis'; }
    public function isRejectable(): bool  { return $this->status === 'soumis'; }
    public function isPayable(): bool     { return $this->status === 'valide'; }

    public function beneficiaryName(): string
    {
        return $this->supplier?->name ?? $this->beneficiary ?? '—';
    }
}
