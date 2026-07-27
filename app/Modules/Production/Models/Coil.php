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
     * [RÈGLE A] Bobine mère physiquement DIVISÉE (découpe/refendage) : elle ne
     * porte plus de disposition propre, ses enfants portent les leurs.
     */
    public const QUALITY_SPLIT           = 'split';

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
        'qty_released', 'qty_quarantine', 'qty_rejected', 'qty_return_pending', 'qty_returned', 'parent_coil_id',
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
        return $this->quality_status !== null
            && in_array($this->quality_status, self::QUALITY_BLOCKING, true);
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
