<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesRep extends Model
{
    use HasCompanyScope;

    protected $table = 'sales_reps';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'email',
        'phone',
        'commission_rate',
        'is_active',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function totalCommissions(string $period = null): float
    {
        $q = $this->commissions();
        if ($period) {
            $q->where('period', $period);
        }
        return (float) $q->sum('commission_amount');
    }

    public function paidCommissions(string $period = null): float
    {
        $q = $this->commissions()->where('status', 'payee');
        if ($period) {
            $q->where('period', $period);
        }
        return (float) $q->sum('commission_amount');
    }
}
