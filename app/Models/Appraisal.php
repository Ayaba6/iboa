<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [RH-11] Évaluation de performance d'un salarié sur une campagne.
 */
class Appraisal extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'campaign', 'period_year', 'evaluator_name', 'status',
        'self_score', 'manager_score', 'overall_score', 'rating',
        'objectives', 'action_plan', 'bonus_amount', 'comments', 'finalized_at', 'created_by',
    ];

    protected $casts = [
        'period_year'   => 'integer',
        'self_score'    => 'decimal:2',
        'manager_score' => 'decimal:2',
        'overall_score' => 'decimal:2',
        'bonus_amount'  => 'decimal:2',
        'finalized_at'  => 'date',
    ];

    public const STATUSES = [
        'a_faire'            => 'À faire',
        'auto_evaluation'    => 'Auto-évaluation',
        'evaluation_manager' => 'Évaluation manager',
        'finalisee'          => 'Finalisée',
    ];

    public const RATINGS = [
        'insuffisant'  => 'Insuffisant',
        'a_ameliorer'  => 'À améliorer',
        'satisfaisant' => 'Satisfaisant',
        'bon'          => 'Bon',
        'excellent'    => 'Excellent',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AppraisalCriterion::class)->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function ratingLabel(): ?string
    {
        return $this->rating ? (self::RATINGS[$this->rating] ?? $this->rating) : null;
    }
}
