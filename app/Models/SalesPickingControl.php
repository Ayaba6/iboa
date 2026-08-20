<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [Ventes §14] Contrôle de préparation — enregistrement APPEND-ONLY.
 *
 * Un contrôle ne se corrige pas et ne se supprime pas : une modification du bon
 * après contrôle l'INVALIDE (motif + horodatage) et un nouveau contrôle est
 * exigé. L'historique des contrôles invalidés reste lisible.
 */
class SalesPickingControl extends Model
{
    public const RESULT_CONFORME = 'conforme';

    public const RESULT_ECART = 'ecart';

    protected $fillable = [
        'sales_picking_id', 'sales_picking_item_id', 'controlled_by',
        'result', 'checkpoints', 'notes', 'invalidated_at', 'invalidated_reason',
    ];

    protected $casts = [
        'checkpoints' => 'array',
        'invalidated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $control) {
            // Seule mutation permise : l'invalidation (une fois, avec motif).
            $dirty = array_keys($control->getDirty());
            $allowed = ['invalidated_at', 'invalidated_reason', 'updated_at'];
            if (array_diff($dirty, $allowed) !== []) {
                throw new \RuntimeException(
                    'Un contrôle de préparation est append-only : seule son invalidation motivée est permise.'
                );
            }
            if ($control->getOriginal('invalidated_at') !== null) {
                throw new \RuntimeException('Ce contrôle est déjà invalidé.');
            }
        });

        static::deleting(function () {
            throw new \RuntimeException('Un contrôle de préparation ne se supprime jamais.');
        });
    }

    public function picking(): BelongsTo
    {
        return $this->belongsTo(SalesPicking::class, 'sales_picking_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesPickingItem::class, 'sales_picking_item_id');
    }
}
