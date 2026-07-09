<?php

namespace App\Models;

use App\Models\Traits\HasReferenceCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerm extends Model
{
    use HasReferenceCache;

    // [PERF-PHASE3] Cache de référence — tri par nombre de jours
    protected const REFERENCE_ORDER_COLUMN = 'days';

    protected $fillable = [
        'name',
        'days',
        'end_of_month',
        'additional_days',
        'deposit_required',
        'deposit_rate',
        'installments_count',
        'block_on_overdue',
        'requires_validation',
        'is_active',
    ];

    protected $casts = [
        'days'            => 'integer',
        'end_of_month'    => 'boolean',
        'additional_days' => 'integer',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ────────────────────────────────���───────────────────────

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    // ── Business logic ───────────────────────────────────────────────────────

    /**
     * Calculate the due date from a given issue date according to these terms.
     *
     * Algorithm (OHADA/French standard):
     *   1. Add `days` to the issue date.
     *   2. If `end_of_month` is true, snap forward to the last day of that month.
     *   3. Add `additional_days` on top.
     */
    public function calculateDueDate(Carbon $issuedAt): Carbon
    {
        $due = $issuedAt->copy()->addDays($this->days);

        if ($this->end_of_month) {
            $due = $due->endOfMonth()->startOfDay();
        }

        if ($this->additional_days > 0) {
            $due = $due->addDays($this->additional_days);
        }

        return $due;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
