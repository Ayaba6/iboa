<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [Ventes §4.3] Bon de préparation QUANTIFIÉ.
 *
 * Distinct du bon de chargement historique (`bon_preparations`) : celui-ci
 * porte des lignes, des quantités et des allocations lot/bobine/dépôt.
 * Le BL doit se construire depuis les quantités VALIDÉES ici.
 *
 * Machine d'états — transitions autorisées uniquement via SalesPickingService :
 *
 *   brouillon → a_preparer → en_preparation → partiellement_prepare | prepare
 *   prepare → controle → valide
 *   tout état non validé → annule (avec motif, libération des réservations)
 *
 * Un bon VALIDÉ ou ANNULÉ ne bouge plus. Jamais de suppression physique.
 */
class SalesPicking extends Model
{
    use HasCompanyScope;

    public const STATUS_BROUILLON = 'brouillon';

    public const STATUS_A_PREPARER = 'a_preparer';

    public const STATUS_EN_PREPARATION = 'en_preparation';

    public const STATUS_PARTIELLEMENT_PREPARE = 'partiellement_prepare';

    public const STATUS_PREPARE = 'prepare';

    public const STATUS_CONTROLE = 'controle';

    public const STATUS_VALIDE = 'valide';

    public const STATUS_ANNULE = 'annule';

    /** États depuis lesquels une annulation reste possible. */
    public const CANCELLABLE_STATUSES = [
        self::STATUS_BROUILLON,
        self::STATUS_A_PREPARER,
        self::STATUS_EN_PREPARATION,
        self::STATUS_PARTIELLEMENT_PREPARE,
        self::STATUS_PREPARE,
        self::STATUS_CONTROLE,
    ];

    protected $fillable = [
        'company_id', 'order_id', 'fiscal_year_id', 'number', 'status',
        'warehouse_id', 'priority', 'requested_date', 'notes',
        'created_by', 'started_by', 'started_at', 'prepared_by', 'prepared_at',
        'controlled_by', 'controlled_at', 'validated_by', 'validated_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason', 'idempotency_key',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'started_at' => 'datetime',
        'prepared_at' => 'datetime',
        'controlled_at' => 'datetime',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Un document validé ou annulé est FIGÉ : toute mutation ultérieure est
        // refusée au niveau modèle, pas seulement dans le service.
        static::updating(function (self $picking) {
            $frozen = [self::STATUS_VALIDE, self::STATUS_ANNULE];
            if (in_array($picking->getOriginal('status'), $frozen, true)) {
                throw new \RuntimeException(sprintf(
                    'Bon de préparation %s figé (statut %s) : aucune modification possible.',
                    $picking->number, $picking->getOriginal('status')
                ));
            }
        });

        static::deleting(function (self $picking) {
            throw new \RuntimeException(
                'Un bon de préparation ne se supprime jamais : utiliser l\'annulation motivée.'
            );
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesPickingItem::class);
    }

    public function controls(): HasMany
    {
        return $this->hasMany(SalesPickingControl::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }
}
