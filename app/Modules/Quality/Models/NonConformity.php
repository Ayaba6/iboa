<?php

namespace App\Modules\Quality\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [QUALITÉ] Non-conformité + action corrective (CAPA). */
class NonConformity extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'quality_inspection_id', 'reference', 'title', 'description', 'severity',
        'status', 'corrective_action', 'responsible_id', 'due_date', 'closed_at', 'created_by',
        // [Maquette NC] général
        'nc_type', 'origin', 'category', 'atelier', 'production_line_id', 'work_center_id',
        'machine_id', 'product_id', 'lot_number', 'norm_reference', 'requirement',
        'measured_value', 'deviation', 'deviation_unit', 'nc_quantity', 'nc_quantity_unit',
        'detected_at', 'detected_by_id', 'comments',
        // évaluation + classification
        'impact_quality', 'impact_cost', 'impact_delay', 'safety_risk',
        'classification', 'client_claim', 'production_stopped', 'isolation_needed', 'product_isolated',
        // disposition immédiate
        'immediate_action', 'isolated_quantity', 'isolated_quantity_unit',
        'isolation_location', 'disposition_responsible_id', 'isolated_at', 'disposition_comments',
    ];
    protected $casts = [
        'due_date' => 'date', 'closed_at' => 'date', 'detected_at' => 'date', 'isolated_at' => 'date',
        'nc_quantity' => 'decimal:2', 'isolated_quantity' => 'decimal:2',
        'client_claim' => 'boolean', 'production_stopped' => 'boolean',
        'isolation_needed' => 'boolean', 'product_isolated' => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(QualityInspection::class, 'quality_inspection_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(Employee::class, 'responsible_id'); }
    public function productionLine(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\ProductionLine::class, 'production_line_id'); }
    public function workCenter(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\WorkCenter::class, 'work_center_id'); }
    public function machine(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\ProductionMachine::class, 'machine_id'); }
    public function product(): BelongsTo { return $this->belongsTo(\App\Models\Product::class); }
    public function detectedBy(): BelongsTo { return $this->belongsTo(Employee::class, 'detected_by_id'); }
    public function dispositionResponsible(): BelongsTo { return $this->belongsTo(Employee::class, 'disposition_responsible_id'); }
    public function characteristics(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(NonConformityCharacteristic::class)->orderBy('sort_order'); }

    public function severityLabel(): string { return match($this->severity){ 'mineure'=>'Mineure','majeure'=>'Majeure','critique'=>'Critique',default=>$this->severity }; }
    public function statusLabel(): string { return match($this->status){ 'ouverte'=>'Ouverte','en_cours'=>'En cours','cloturee'=>'Clôturée',default=>$this->status }; }

    protected static function newFactory() { return \Database\Factories\NonConformityFactory::new(); }
}
