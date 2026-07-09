<?php

namespace App\Modules\Production\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [MAINTENANCE] Opération planifiée d'une intervention (diagnostic, remplacement, test…). */
class MaintenanceOperation extends Model
{
    protected $fillable = [
        'machine_maintenance_id', 'number', 'code', 'name', 'technician_id',
        'planned_duration_min', 'start_time', 'end_time', 'status', 'is_critical', 'sort_order',
    ];
    protected $casts = [
        'number' => 'integer', 'planned_duration_min' => 'decimal:2',
        'is_critical' => 'boolean', 'sort_order' => 'integer',
    ];

    public function maintenance(): BelongsTo { return $this->belongsTo(MachineMaintenance::class, 'machine_maintenance_id'); }
    public function technician(): BelongsTo { return $this->belongsTo(Employee::class, 'technician_id'); }
}
