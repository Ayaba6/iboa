<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope, HasAttachments;

    protected $table = 'warehouses';

    protected $fillable = [
        'company_id',
        'name',
        'long_name',
        'code',
        'type',
        'parent_id',
        'site',
        'can_production',
        'can_sale',
        'can_delivery',
        'can_purchase',
        'can_stock',
        'can_transfer',
        'allow_negative_stock',
        'requires_quality_control',
        'address',
        'address_complement',
        'city',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'default_location',
        'quality_warehouse_id',
        'scrap_warehouse_id',
        'max_capacity',
        'capacity_unit',
        'overload_alert_percent',
        'issue_method',
        'issue_priority',
        'stock_account',
        'stock_journal',
        'cost_center',
        'analytic_section',
        'flow_settings',
        'manager_name',
        'phone',
        'email',
        'is_default',
        'is_active',
    ];

    /** Types de dépôt (Phase D). */
    public const TYPES = [
        'achat'             => 'Dépôt d\'achat',
        'matiere_premiere'  => 'Matières premières',
        'production'        => 'Production',
        'produit_fini'      => 'Produits finis',
        'vente'             => 'Site de vente',
        'logistique'        => 'Logistique',
        'sous_traitance'    => 'Sous-traitance',
        'qualite'           => 'Qualité',
        'rebut'             => 'Rebut',
    ];

    /** Méthodes de valorisation / sortie de stock. */
    public const ISSUE_METHODS = [
        'fifo' => 'FIFO',
        'lifo' => 'LIFO',
        'cmp'  => 'CMP (coût moyen pondéré)',
    ];

    /** Priorité de sortie des lots. */
    public const ISSUE_PRIORITIES = [
        'oldest'      => 'Date la plus ancienne',
        'nearest_exp' => 'Péremption la plus proche',
        'location'    => 'Ordre d\'emplacement',
    ];

    /** Flux gérés par la table « Autorisations et flux ». */
    public const FLOWS = [
        'achat'            => ['Achat', 'Réceptions marchandises', 'can_purchase'],
        'vente'            => ['Vente', 'Préparation commandes', 'can_sale'],
        'production'       => ['Production', 'Alimentation ateliers', 'can_production'],
        'livraison'        => ['Livraison', 'Expéditions clients', 'can_delivery'],
        'reception'        => ['Réception', 'Réceptions fournisseurs', 'can_purchase'],
        'transfert_sortie' => ['Transfert sortant', 'Sorties vers autres dépôts', 'can_transfer'],
        'transfert_entree' => ['Transfert entrant', 'Entrées depuis autres dépôts', 'can_transfer'],
        'inventaire'       => ['Inventaire', 'Inventaires périodiques', 'can_stock'],
    ];

    protected $casts = [
        'is_default'               => 'boolean',
        'is_active'                => 'boolean',
        'can_production'           => 'boolean',
        'can_sale'                 => 'boolean',
        'can_delivery'             => 'boolean',
        'can_purchase'             => 'boolean',
        'can_stock'                => 'boolean',
        'can_transfer'             => 'boolean',
        'allow_negative_stock'     => 'boolean',
        'requires_quality_control' => 'boolean',
        'max_capacity'             => 'decimal:2',
        'overload_alert_percent'   => 'decimal:2',
        'latitude'                 => 'decimal:7',
        'longitude'                => 'decimal:7',
        'flow_settings'            => 'array',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only the default warehouse.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: only active warehouses.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The company that owns this warehouse.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Current stock levels stored in this warehouse.
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Stock movements that occurred in this warehouse.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Locations (emplacements) within this warehouse.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}
