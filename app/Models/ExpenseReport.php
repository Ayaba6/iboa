<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [RH-09] Note de frais d'un salarié.
 */
class ExpenseReport extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'reference', 'title', 'report_date', 'status',
        'total_amount', 'payment_method', 'reject_reason', 'approved_by', 'approved_at',
        'reimbursed_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'report_date'   => 'date',
        'approved_at'   => 'date',
        'reimbursed_at' => 'date',
        'total_amount'  => 'decimal:2',
    ];

    public const STATUSES = [
        'brouillon'  => 'Brouillon',
        'soumise'    => 'Soumise',
        'approuvee'  => 'Approuvée',
        'rejetee'    => 'Rejetée',
        'remboursee' => 'Remboursée',
    ];

    public const CATEGORIES = [
        'transport'   => 'Transport',
        'hebergement' => 'Hébergement',
        'repas'       => 'Repas',
        'carburant'   => 'Carburant',
        'fourniture'  => 'Fourniture',
        'telephone'   => 'Téléphone',
        'autre'       => 'Autre',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['brouillon', 'rejetee'], true);
    }
}
