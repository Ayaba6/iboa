<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** [PRODUCTION] Intervention de maintenance machine (préventive / corrective). */
class MachineMaintenance extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'machine_id', 'maintenance_plan_id', 'type', 'title', 'status', 'planned_at',
        'started_at', 'ended_at', 'downtime_minutes', 'cost', 'operator_id', 'notes', 'created_by',
        // [Maquette Intervention] général
        'code', 'site', 'requester_id', 'priorite', 'production_line_id',
        'planned_start_time', 'planned_end_time', 'atelier', 'depot_pieces_id',
        'request_source', 'urgency_level', 'ot_reference',
        // paramètres et sécurité
        'machine_stop_required', 'electrical_lockout', 'allow_subcontracting',
        'maintenance_validation_required', 'quality_check_after',
        // résumé technique
        'symptom', 'probable_cause', 'critical_part', 'production_impact',
    ];
    protected $casts = [
        'planned_at' => 'date', 'started_at' => 'datetime', 'ended_at' => 'datetime',
        'downtime_minutes' => 'decimal:2', 'cost' => 'integer',
        'machine_stop_required' => 'boolean', 'electrical_lockout' => 'boolean',
        'allow_subcontracting' => 'boolean', 'maintenance_validation_required' => 'boolean',
        'quality_check_after' => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(Employee::class, 'operator_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id'); }
    public function parts(): HasMany { return $this->hasMany(MaintenancePart::class); }
    public function operations(): HasMany { return $this->hasMany(MaintenanceOperation::class)->orderBy('sort_order'); }
    public function requester(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'requester_id'); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class, 'production_line_id'); }
    public function depotPieces(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_pieces_id'); }

    public function typeLabel(): string { return $this->type === 'corrective' ? 'Corrective' : 'Préventive'; }
    public function statusLabel(): string { return match($this->status){ 'planifie'=>'Planifiée','en_cours'=>'En cours',default=>'Terminée' }; }

    /** Coût total = saisie manuelle (main d'œuvre/forfait) + pièces réellement consommées du stock. */
    public function totalCost(): int
    {
        return (int) $this->cost + (int) $this->parts->sum(fn (MaintenancePart $p) => $p->quantity * $p->unit_cost);
    }

    protected static function newFactory() { return \Database\Factories\MachineMaintenanceFactory::new(); }
}
