<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-05] Évènement de carrière / mouvement d'un salarié.
 */
class CareerEvent extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'type', 'effective_date',
        'from_job_position_id', 'to_job_position_id', 'from_department_id', 'to_department_id',
        'from_category', 'to_category', 'from_fonction', 'to_fonction',
        'grade', 'manager_name', 'site', 'cost_center', 'salary',
        'reason', 'notes', 'applied', 'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'salary'         => 'decimal:2',
        'applied'        => 'boolean',
    ];

    public const TYPES = [
        'affectation'       => 'Affectation',
        'mutation'          => 'Mutation',
        'promotion'         => 'Promotion',
        'changement_poste'  => 'Changement de poste',
        'changement_grade'  => 'Changement de grade',
        'revalorisation'    => 'Revalorisation salariale',
        'reintegration'     => 'Réintégration',
        'autre'             => 'Autre',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function toJobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'to_job_position_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
