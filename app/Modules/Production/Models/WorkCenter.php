<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [PRODUCTION] Centre de travail (work center) — unité de capacité/coût.
 * Regroupe une machine + un coût horaire + une capacité journalière.
 * Socle des gammes opératoires (routings) et de la planification.
 */
class WorkCenter extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id', 'machine_id', 'code', 'name', 'type', 'atelier', 'site',
        'capacity_hours_per_day', 'cost_per_hour', 'efficiency_rate',
        'is_active', 'notes', 'created_by',
        // [Maquette Poste de charge] général
        'category', 'location', 'production_line_id', 'poste_group', 'responsible_id',
        'depot_production_id', 'priorite', 'work_calendar', 'similar_work_center_id',
        // capacité + temps
        'nominal_capacity', 'capacity_unit', 'theoretical_capacity', 'theoretical_capacity_unit',
        'utilization_rate', 'trs_standard',
        'cycle_time', 'cycle_time_unit', 'setup_time_min', 'adjustment_time_min', 'transfer_time_min',
        // organisation + contrôle + identification
        'operators_count', 'default_team', 'operating_mode', 'parallel_work',
        'quality_control_point', 'control_frequency', 'documentation_ref', 'criticality',
        'barcode', 'serial_number',
        // options
        'include_in_capacity', 'allow_overload', 'scrap_management',
        'require_time_entry', 'auto_cost_allocation', 'required_on_of',
    ];

    protected $casts = [
        'capacity_hours_per_day' => 'decimal:2',
        'cost_per_hour'          => 'decimal:2',
        'efficiency_rate'        => 'decimal:2',
        'is_active'              => 'boolean',
        'nominal_capacity'       => 'decimal:2',
        'theoretical_capacity'   => 'decimal:2',
        'utilization_rate'       => 'decimal:2',
        'trs_standard'           => 'decimal:2',
        'cycle_time'             => 'decimal:3',
        'setup_time_min'         => 'decimal:2',
        'adjustment_time_min'    => 'decimal:2',
        'transfer_time_min'      => 'decimal:2',
        'operators_count'        => 'integer',
        'parallel_work'          => 'boolean',
        'include_in_capacity'    => 'boolean',
        'allow_overload'         => 'boolean',
        'scrap_management'       => 'boolean',
        'require_time_entry'     => 'boolean',
        'auto_cost_allocation'   => 'boolean',
        'required_on_of'         => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class, 'production_line_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'responsible_id'); }
    public function depotProduction(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_production_id'); }
    public function similarWorkCenter(): BelongsTo { return $this->belongsTo(self::class, 'similar_work_center_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\WorkCenterFactory::new();
    }
}
