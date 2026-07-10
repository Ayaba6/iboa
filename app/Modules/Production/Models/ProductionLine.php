<?php
namespace App\Modules\Production\Models;
use App\Models\Company;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionLine extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id','machine_id','code','name','is_active','created_by',
        // [Maquette Ligne] général
        'type_ligne','product_id','depot_production_id','atelier','location','line_group','site',
        'nominal_capacity','capacity_unit','work_calendar','commissioned_at','responsible_id','status','notes',
        // capacité et performances
        'theoretical_capacity','theoretical_capacity_unit','practical_capacity','practical_capacity_unit',
        'trs_target','cycle_time','cycle_time_unit',
        // organisation + plages
        'teams_count','default_team','operators_per_team','continuous_work',
        'start_time','end_time','break1_start','break1_end','break2_start','break2_end',
        // contrôle + identification
        'quality_control_point','control_frequency','require_production_entry','barcode','serial_number',
        // options
        'allow_of_start','allow_interline','scrap_management','auto_cost_allocation',
        'block_if_unavailable','track_stoppages','priorite',
    ];
    protected $casts = [
        'is_active'=>'boolean','commissioned_at'=>'date',
        'nominal_capacity'=>'decimal:2','theoretical_capacity'=>'decimal:2','practical_capacity'=>'decimal:2',
        'trs_target'=>'decimal:2','cycle_time'=>'decimal:3',
        'teams_count'=>'integer','operators_per_team'=>'integer',
        'continuous_work'=>'boolean','require_production_entry'=>'boolean',
        'allow_of_start'=>'boolean','allow_interline'=>'boolean','scrap_management'=>'boolean',
        'auto_cost_allocation'=>'boolean','block_if_unavailable'=>'boolean','track_stoppages'=>'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function product(): BelongsTo { return $this->belongsTo(\App\Models\Product::class); }
    public function depotProduction(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_production_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'responsible_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionLineFactory::new();
    }
}
