<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * [Division #3] Proposition de division — porte la machine d'états du circuit
 * d'approbation et fige les seuils applicables au moment de la soumission.
 */
class CoilSplitProposal extends Model
{
    public const STATUS_DRAFT       = 'brouillon';
    public const STATUS_SUBMITTED   = 'soumise';
    public const STATUS_APPROVED    = 'approuvee';
    public const STATUS_REFUSED     = 'refusee';
    public const STATUS_EXECUTED    = 'executee';
    public const STATUS_INVALIDATED = 'invalidee';
    public const STATUS_EXPIRED     = 'expiree';

    protected $table = 'coil_split_proposals';

    protected $guarded = [];

    protected $casts = [
        'payload'                => 'array',
        'divisible_qty'          => 'decimal:3',
        'scrap_qty'              => 'decimal:3',
        'loss_qty'               => 'decimal:3',
        'threshold_loss_qty'     => 'decimal:3',
        'requires_loss_approval' => 'boolean',
        'submitted_at'           => 'datetime',
        'approved_at'            => 'datetime',
        'executed_at'            => 'datetime',
    ];
}
