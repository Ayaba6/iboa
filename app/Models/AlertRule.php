<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * [PIL-04] Règle d'alerte par seuil.
 */
class AlertRule extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'name', 'metric', 'operator', 'threshold', 'target_roles',
        'is_active', 'description', 'last_value', 'last_triggered_at', 'created_by',
    ];

    protected $casts = [
        'target_roles'      => 'array',
        'is_active'         => 'boolean',
        'threshold'         => 'decimal:2',
        'last_value'        => 'decimal:2',
        'last_triggered_at' => 'datetime',
    ];

    public const OPERATORS = [
        'gt'  => '>',
        'gte' => '≥',
        'lt'  => '<',
        'lte' => '≤',
        'eq'  => '=',
    ];

    public function operatorSymbol(): string
    {
        return self::OPERATORS[$this->operator] ?? $this->operator;
    }
}
