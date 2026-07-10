<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Opération d'une gamme (poste + temps). */
class RoutingOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'routing_id', 'work_center_id', 'sequence', 'name', 'setup_minutes', 'run_minutes_per_unit',
        // [SAGE parité]
        'operation_number', 'labor_minutes', 'quantite_base', 'uo', 'rendement',
        'controle_qualite', 'sous_traitance', 'statut',
        // [Maquette Gamme] code opération, type, attente, point de contrôle, critique
        'code', 'type_operation', 'waiting_minutes', 'point_controle', 'is_critical',
    ];
    protected $casts = [
        'setup_minutes' => 'decimal:2', 'run_minutes_per_unit' => 'decimal:2',
        'labor_minutes' => 'decimal:2', 'quantite_base' => 'decimal:3', 'rendement' => 'decimal:2',
        'controle_qualite' => 'boolean', 'sous_traitance' => 'boolean', 'operation_number' => 'integer',
        'waiting_minutes' => 'decimal:2', 'is_critical' => 'boolean',
    ];

    public function routing(): BelongsTo { return $this->belongsTo(Routing::class); }
    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class); }

    protected static function newFactory() { return \Database\Factories\RoutingOperationFactory::new(); }
}
