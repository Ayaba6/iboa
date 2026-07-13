<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-13] Départ d'un salarié + solde de tout compte.
 */
class EmployeeDeparture extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'employee_id', 'type', 'notice_start', 'notice_days', 'effective_date',
        'status', 'reason', 'severance_amount', 'notice_amount', 'leave_balance_days',
        'leave_balance_amount', 'other_amount', 'total_stc',
        'equipment_returned', 'documents_issued', 'notes', 'finalized_at', 'created_by',
    ];

    protected $casts = [
        'notice_start'         => 'date',
        'effective_date'       => 'date',
        'finalized_at'         => 'date',
        'notice_days'          => 'integer',
        'severance_amount'     => 'decimal:2',
        'notice_amount'        => 'decimal:2',
        'leave_balance_days'   => 'decimal:2',
        'leave_balance_amount' => 'decimal:2',
        'other_amount'         => 'decimal:2',
        'total_stc'            => 'decimal:2',
        'equipment_returned'   => 'boolean',
        'documents_issued'     => 'boolean',
    ];

    public const TYPES = [
        'demission'    => 'Démission',
        'fin_contrat'  => 'Fin de contrat (CDD)',
        'licenciement' => 'Licenciement',
        'retraite'     => 'Départ à la retraite',
        'rupture'      => 'Rupture conventionnelle',
        'deces'        => 'Décès',
        'autre'        => 'Autre',
    ];

    public const STATUSES = [
        'declare'  => 'Déclaré',
        'en_cours' => 'En cours',
        'cloture'  => 'Clôturé',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
