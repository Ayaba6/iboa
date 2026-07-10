<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingBudget extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'code', 'label', 'version',
        'period_from', 'period_to', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_from' => 'integer',
        'period_to'   => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingBudgetLine::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return ['en_cours' => 'En cours', 'valide' => 'Validé', 'cloture' => 'Clôturé'][$this->status] ?? $this->status;
    }
}
