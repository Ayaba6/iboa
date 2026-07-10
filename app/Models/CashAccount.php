<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope;

    protected $table = 'cash_accounts';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'bank_name',
        'bank_branch',
        'account_number',
        'iban',
        'swift_bic',
        'payment_method_id',
        'currency_code',
        'opening_balance',
        'current_balance',
        'min_balance',
        'is_default',
        'is_active',
        'notes',
        // [PARITÉ SAGE X3]
        'account_group',
        'category',
        'general_account',
        'site',
        'manager_name',
        'description',
        'country_code',
        'bank_code',
        'branch_code',
        'rib_key',
        'overdraft_limit',
        'overdraft_currency',
        'transaction_ceiling',
        'operation_ceiling',
        'entry_generation',
        'include_in_forecast',
        'is_regularization',
        'opened_at',
        'closes_at',
        'statement_format',
        'statement_frequency',
        'last_statement_at',
        'forecast_horizon_days',
        'forecast_currency',
    ];

    protected $casts = [
        'opening_balance' => 'integer',
        'current_balance' => 'integer',
        'min_balance'     => 'integer',
        'is_default'      => 'boolean',
        'is_active'       => 'boolean',
        // [PARITÉ SAGE X3]
        'overdraft_limit'        => 'integer',
        'transaction_ceiling'    => 'integer',
        'operation_ceiling'      => 'integer',
        'include_in_forecast'    => 'boolean',
        'is_regularization'      => 'boolean',
        'opened_at'              => 'date',
        'closes_at'              => 'date',
        'last_statement_at'      => 'date',
        'forecast_horizon_days'  => 'integer',
    ];

    /** [TRESO] Solde sous le seuil d'alerte ? */
    public function isLowBalance(): bool
    {
        return (int) $this->min_balance > 0 && (int) $this->current_balance < (int) $this->min_balance;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function typeBadge(): string
    {
        return match ($this->type) {
            'caisse'       => 'Caisse',
            'banque'       => 'Banque',
            'mobile_money' => 'Mobile Money',
            default        => $this->type,
        };
    }
}
