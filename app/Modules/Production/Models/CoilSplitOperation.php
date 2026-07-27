<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [Division #1] Opération de division — document APPEND-ONLY.
 *
 * Une fois créée, une opération ne peut être ni modifiée, ni supprimée, ni
 * soft-deletée : toute correction passe par une CONTRE-OPÉRATION. La garde est
 * posée sur les événements du modèle, donc un appel Eloquent direct
 * (`$op->update(...)`, `$op->delete()`) est refusé au même titre qu'un appel de
 * service — c'est ce que prouvent les tests.
 */
class CoilSplitOperation extends Model
{
    protected $table = 'coil_split_operations';

    protected $guarded = [];

    protected $casts = [
        'mother_qty_before'          => 'decimal:3',
        'mother_initial_weight'      => 'decimal:3',
        'consumed_before_split'      => 'decimal:3',
        'returned_before_split'      => 'decimal:3',
        'released_before_split'      => 'decimal:3',
        'quarantine_before_split'    => 'decimal:3',
        'scrap_qty'                  => 'decimal:3',
        'loss_qty'                   => 'decimal:3',
        'weighing_tolerance'         => 'decimal:3',
        'requires_post_split_quality_control' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $op) {
            throw new \RuntimeException(sprintf(
                'Opération de division %s : document APPEND-ONLY — modification interdite. '
                . 'Utilisez une contre-opération pour corriger.',
                $op->number ?? $op->id
            ));
        });

        static::deleting(function (self $op) {
            throw new \RuntimeException(sprintf(
                'Opération de division %s : document APPEND-ONLY — suppression interdite.',
                $op->number ?? $op->id
            ));
        });
    }

    public function coil(): BelongsTo
    {
        return $this->belongsTo(Coil::class, 'coil_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CoilSplitOperationItem::class, 'split_operation_id');
    }
}
