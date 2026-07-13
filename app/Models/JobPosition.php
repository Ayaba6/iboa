<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [RH-01] Poste / Grade / Emploi — référentiel des postes de travail.
 */
class JobPosition extends Model
{
    use HasFactory;
    use HasCompanyScope;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'department_id', 'grade', 'category',
        'cost_center', 'headcount_target', 'salary_min', 'salary_max',
        'description', 'missions', 'is_active',
    ];

    protected $casts = [
        'salary_min'       => 'decimal:2',
        'salary_max'       => 'decimal:2',
        'headcount_target' => 'integer',
        'is_active'        => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** Effectif pourvu sur le poste. */
    public function getHeadcountAttribute(): int
    {
        return $this->employees()->count();
    }
}
