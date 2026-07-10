<?php
namespace App\Modules\Production\Models;
use App\Models\Company;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionMachine extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id','code','name','type',
        'manufacturer','model','serial_number','site','atelier','commissioned_at','power_kw',
        'hourly_cost','energy_cost_per_hour','maintenance_cost_per_hour',
        'status','maintenance_frequency_days','is_active','notes','created_by',
        // [Maquette Machine] général
        'category','production_line_id','location','country_origin','brand',
        'nominal_capacity','capacity_unit','unit_id','responsible_id','power_supply',
        'acquisition_cost','weight_kg',
        // caractéristiques techniques
        'length_mm','width_mm','height_mm','footprint_m3',
        'max_speed','nominal_speed','useful_width_mm','thickness_min','thickness_max',
        'waves_count','shaft_diameter_mm',
        'motor_type','reducer','cutting_system','integrated_decoiler',
        'power_kva','air_pressure_bar','hydraulic_pressure_bar',
        'temp_min','temp_max','humidity_max','environment',
        // disponibilités et affectation
        'assigned_to_atelier','assigned_to_line','work_calendar','work_center_id','default_team','priorite',
    ];

    public function maintenances(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(MachineMaintenance::class, 'machine_id'); }
    protected $casts = [
        'hourly_cost'=>'integer','energy_cost_per_hour'=>'integer','maintenance_cost_per_hour'=>'integer',
        'is_active'=>'boolean','commissioned_at'=>'date','power_kw'=>'decimal:2',
        'nominal_capacity'=>'decimal:2','acquisition_cost'=>'integer','weight_kg'=>'decimal:2',
        'length_mm'=>'decimal:2','width_mm'=>'decimal:2','height_mm'=>'decimal:2','footprint_m3'=>'decimal:2',
        'max_speed'=>'decimal:2','nominal_speed'=>'decimal:2','useful_width_mm'=>'decimal:2',
        'thickness_min'=>'decimal:3','thickness_max'=>'decimal:3','waves_count'=>'integer','shaft_diameter_mm'=>'decimal:2',
        'integrated_decoiler'=>'boolean','power_kva'=>'decimal:2','air_pressure_bar'=>'decimal:2','hydraulic_pressure_bar'=>'decimal:2',
        'temp_min'=>'decimal:1','temp_max'=>'decimal:1','humidity_max'=>'decimal:1',
        'assigned_to_atelier'=>'boolean','assigned_to_line'=>'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function lines(): HasMany { return $this->hasMany(ProductionLine::class, 'machine_id'); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class, 'production_line_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(\App\Models\Unit::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'responsible_id'); }
    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class, 'work_center_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionMachineFactory::new();
    }
}
