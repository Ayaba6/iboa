<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [Division #1] Ligne d'allocation d'une division — APPEND-ONLY, comme
 * l'opération qui la porte : ni modification, ni suppression, ni ajout tardif
 * à une opération déjà validée. Correction par contre-opération uniquement.
 */
class CoilSplitOperationItem extends Model
{
    protected $table = 'coil_split_operation_items';

    protected $guarded = [];

    protected $casts = [
        'weight' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // Ajout tardif interdit : on ne peut rattacher une ligne qu'à une
            // opération créée dans la même transaction (statut « appliquee » et
            // aucune ligne postérieure à sa clôture n'est acceptée hors service).
            $op = CoilSplitOperation::find($item->split_operation_id);
            if ($op && $op->status === 'cloturee') {
                throw new \RuntimeException(
                    'Opération de division clôturée : ajout d\'une ligne interdit (append-only).'
                );
            }
        });

        static::updating(function (self $item) {
            throw new \RuntimeException(
                'Ligne d\'opération de division : document APPEND-ONLY — modification interdite.'
            );
        });

        static::deleting(function (self $item) {
            throw new \RuntimeException(
                'Ligne d\'opération de division : document APPEND-ONLY — suppression interdite.'
            );
        });
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(CoilSplitOperation::class, 'split_operation_id');
    }

    public function childCoil(): BelongsTo
    {
        return $this->belongsTo(Coil::class, 'child_coil_id');
    }
}
