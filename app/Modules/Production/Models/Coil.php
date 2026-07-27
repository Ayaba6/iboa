<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Models\Warehouse;
use Database\Factories\CoilFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Bobine de tôle — lot + suivi poids, liée à un produit matière 1re. */
class Coil extends Model
{
    use HasCompanyScope, HasCreator, HasFactory, SoftDeletes;

    /**
     * [Qualité #11] Statuts QUALITÉ (distincts du statut logistique `status`).
     * NULL = inconnu (bobine historique) — jamais interprété comme « libéré ».
     */
    public const QUALITY_RECEIVED        = 'recu';
    public const QUALITY_QUARANTINED     = 'quarantaine';
    public const QUALITY_RELEASED        = 'libere';
    public const QUALITY_PARTIAL_RELEASE = 'libere_partiel';
    public const QUALITY_REJECTED        = 'refuse';
    public const QUALITY_RETURN_PENDING  = 'retour_attente';
    public const QUALITY_RETURNED        = 'retourne';
    public const QUALITY_CANCELLED       = 'annule';

    /**
     * [Qualité #3] Cycle de vie TRANSFORMATION — axe DISTINCT de la qualité et de
     * la logistique. Une bobine divisée est transformée, pas « dans une
     * disposition qualité » : après division, sa disposition qualité passe à NULL
     * (elle appartient désormais aux bobines filles).
     */
    public const TRANSFO_INTACT      = 'intacte';
    public const TRANSFO_SPLIT       = 'divisee';
    public const TRANSFO_TRANSFORMED = 'transformee';

    /** Statuts qualité INTERDISANT toute consommation en production. */
    public const QUALITY_BLOCKING = [
        self::QUALITY_QUARANTINED,
        self::QUALITY_RECEIVED,          // reçu mais pas encore contrôlé/libéré
        self::QUALITY_REJECTED,
        self::QUALITY_RETURN_PENDING,
        self::QUALITY_RETURNED,
        self::QUALITY_CANCELLED,
    ];

    protected $fillable = [
        'company_id', 'product_id', 'supplier_id', 'reception_id', 'reference', 'lot_number', 'color', 'thickness', 'width',
        'initial_weight', 'remaining_weight', 'estimated_length', 'purchase_price', 'cost_per_kg',
        'received_at', 'status', 'quality_status', 'quality_decision_id', 'notes', 'created_by', 'stock_lot_id', 'kg_per_linear_meter',
        'qty_released', 'qty_quarantine', 'qty_rejected', 'qty_return_pending', 'qty_returned', 'parent_coil_id', 'transformation_status',
        'quality_status_before_transformation', 'transferred_to_children_qty', 'transformed_at', 'transformed_by',
        // [Maquette Bobine] réception + caractéristiques + gestion
        'supplier_reference', 'warehouse_id', 'site', 'bl_number', 'origine', 'devise',
        'nuance', 'gross_weight', 'inner_diameter', 'outer_diameter', 'coating', 'surface_finish',
        'tolerance_thickness', 'barcode', 'brand', 'serial_number',
        'valuation_method', 'is_stock_managed', 'lot_tracking', 'allow_negative_stock',
        'valuation_status', 'valuation_reason', 'valuation_responsible_id',
    ];

    protected $casts = [
        'thickness' => 'decimal:2', 'width' => 'decimal:1', 'initial_weight' => 'decimal:2', 'remaining_weight' => 'decimal:2',
        'estimated_length' => 'decimal:2', 'purchase_price' => 'integer', 'cost_per_kg' => 'decimal:2', 'received_at' => 'date',
        'gross_weight' => 'decimal:3', 'inner_diameter' => 'decimal:2', 'outer_diameter' => 'decimal:2',
        'tolerance_thickness' => 'decimal:3',
        'is_stock_managed' => 'boolean', 'lot_tracking' => 'boolean', 'allow_negative_stock' => 'boolean',
    ];

    /**
     * [Qualité #11] La bobine est-elle bloquée par son statut qualité ?
     * Un statut NULL (historique, inconnu) n'est PAS bloquant — il est signalé
     * par `a3:audit-receptions` plutôt que d'arrêter la production existante ;
     * il n'est jamais présenté comme une libération.
     */
    public function isQualityBlocked(): bool
    {
        // [#3] Une bobine DIVISÉE/TRANSFORMÉE n'est plus consommable ni réservable :
        // sa matière appartient désormais à ses bobines filles.
        if (in_array($this->transformation_status, [self::TRANSFO_SPLIT, self::TRANSFO_TRANSFORMED], true)) {
            return true;
        }

        return $this->quality_status !== null
            && in_array($this->quality_status, self::QUALITY_BLOCKING, true);
    }

    /** La bobine a-t-elle été physiquement divisée ? */
    public function isSplit(): bool
    {
        return $this->transformation_status === self::TRANSFO_SPLIT;
    }

    // -------------------------------------------------------------------------
    // [#1] État OPÉRATIONNEL centralisé — ne jamais tester `quality_status` ou
    // `status` directement pour décider d'un usage matière. Une mère divisée peut
    // rester historiquement « libérée » tout en étant inutilisable.
    // -------------------------------------------------------------------------

    /** Statut QUALITÉ (historique) : la bobine a-t-elle été libérée ? */
    public function isQualityReleased(): bool
    {
        return $this->quality_status === self::QUALITY_RELEASED;
    }

    /**
     * La bobine est-elle OPÉRATIONNELLEMENT active (utilisable comme matière) ?
     * Faux si divisée/transformée, bloquée qualité, épuisée ou sans solde.
     */
    public function isOperationallyActive(): bool
    {
        return ! $this->isQualityBlocked()
            && $this->status !== 'epuisee'
            && (float) $this->remaining_weight > 0;
    }

    public function isConsumable(): bool
    {
        return $this->isOperationallyActive();
    }

    public function isReservable(): bool
    {
        return $this->isOperationallyActive();
    }

    public function isTransferable(): bool
    {
        return $this->isOperationallyActive();
    }

    /** Quantité réellement disponible (0 si non opérationnelle). */
    public function availableQuantity(): float
    {
        if (! $this->isOperationallyActive()) {
            return 0.0;
        }

        return $this->hasQualityBalances()
            ? $this->availableReleasedQuantity()
            : (float) $this->remaining_weight;
    }

    /**
     * [#3] Valeur ACTIVE en stock — distincte du coût HISTORIQUE (purchase_price,
     * conservé même après division). Une mère divisée vaut 0 en stock actif : sa
     * valeur a été transférée aux filles. Les états de stock/valorisation doivent
     * sommer CECI, jamais purchase_price.
     */
    public function activeInventoryValue(): float
    {
        if (! $this->isOperationallyActive()) {
            return 0.0;
        }

        return (float) $this->remaining_weight * (float) $this->cost_per_kg;
    }

    /** Coût historique d'acquisition — jamais modifié par une transformation. */
    public function historicalTotalCost(): int
    {
        return (int) $this->purchase_price;
    }

    /**
     * Scope des bobines utilisables comme matière (sélecteurs OF, réservation,
     * découpe, MRP, tableaux de bord…). Exclut les divisées/transformées, les
     * bloquées qualité et les épuisées.
     */
    public function scopeUsableAsMaterial($query)
    {
        return $query->where('status', '!=', 'epuisee')
            ->where('remaining_weight', '>', 0)
            // non divisée / non transformée (NULL = historique intacte)
            ->where(fn ($q) => $q->whereNull('transformation_status')
                ->orWhere('transformation_status', self::TRANSFO_INTACT))
            // non bloquée qualité (NULL = historique, non bloquant)
            ->where(fn ($q) => $q->whereNull('quality_status')
                ->orWhereNotIn('quality_status', self::QUALITY_BLOCKING));
    }

    /**
     * [Qualité #1] La bobine porte-t-elle des soldes quantitatifs par disposition ?
     * (false = bobine historique/non qualifiée : garde quantitative inapplicable,
     * seule la garde de poids restant s'applique — signalé par l'audit.)
     */
    public function hasQualityBalances(): bool
    {
        return $this->qty_released !== null || $this->qty_quarantine !== null;
    }

    /**
     * [Qualité #1/#2] Quantité RÉELLEMENT consommable :
     *   dispo = libéré − consommé − retourné depuis le libéré
     * où consommé = poids initial − poids restant. Un statut « libéré
     * partiellement » n'autorise JAMAIS à lui seul la consommation : c'est ce
     * solde qui fait foi.
     */
    public function availableReleasedQuantity(): float
    {
        if (! $this->hasQualityBalances()) {
            return (float) $this->remaining_weight; // héritage : pas de solde qualité
        }
        $released = (float) ($this->qty_released ?? 0);
        $consumed = max(0.0, (float) $this->initial_weight - (float) $this->remaining_weight);
        $returned = (float) ($this->qty_returned ?? 0);

        return max(0.0, $released - $consumed - $returned);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'disponible' && $this->valuation_status === 'valorisation_definitive' && (float) $this->cost_per_kg > 0 && (float) $this->remaining_weight > 0;
    }

    public function consumptionRate(): float
    {
        return $this->initial_weight > 0 ? (float) $this->remaining_weight / (float) $this->initial_weight : 0;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'disponible' => 'Disponible','en_production' => 'En production','epuisee' => 'Épuisée',default => $this->status
        };
    }

    protected static function newFactory()
    {
        return CoilFactory::new();
    }
}
