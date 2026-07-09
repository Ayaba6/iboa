<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fiscal_years';

    protected $fillable = [
        'code',
        'label',
        'starts_at',
        'ends_at',
        'actual_close_date',
        'status',
        'is_current',
        // [Maquette Exercices fiscaux]
        'periodicity',
        'exercise_type',
        'responsible_id',
        'fiscal_regime',
        'base_currency',
        'previous_reference',
        'next_reference',
        'comment',
        'internal_notes',
        'allow_entries_after_provisional_close',
        'monthly_close_required',
        'auto_centralization',
        'analytics_active',
        'vat_lock_after_validation',
        'tolerated_days',
        'last_monthly_close',
    ];

    protected $casts = [
        'starts_at'          => 'date',
        'ends_at'            => 'date',
        'actual_close_date'  => 'date',
        'last_monthly_close' => 'date',
        'is_current'         => 'boolean',
        'allow_entries_after_provisional_close' => 'boolean',
        'monthly_close_required'    => 'boolean',
        'auto_centralization'       => 'boolean',
        'analytics_active'          => 'boolean',
        'vat_lock_after_validation' => 'boolean',
        'tolerated_days'            => 'integer',
    ];

    public function responsible(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only the current fiscal year.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: only open fiscal years.
     */
    public function scopeOuvert(Builder $query): Builder
    {
        return $query->where('status', 'ouvert');
    }
}
