<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [VEN Crédit client] Décision de crédit tracée pour un client.
 * Clients globaux (pas de company scope) — filtrage via la relation client.
 */
class CreditDecision extends Model
{
    protected $fillable = [
        'company_id', 'client_id', 'type', 'previous_limit', 'new_limit', 'amount',
        'reason', 'decided_by',
    ];

    protected $casts = [
        'previous_limit' => 'decimal:2',
        'new_limit'      => 'decimal:2',
        'amount'         => 'decimal:2',
    ];

    public const TYPES = [
        'blocage'            => 'Blocage',
        'deblocage'          => 'Déblocage',
        'derogation'         => 'Dérogation ponctuelle',
        'relevement_plafond' => 'Relèvement du plafond',
        'reduction_plafond'  => 'Réduction du plafond',
        'autre'              => 'Autre',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
