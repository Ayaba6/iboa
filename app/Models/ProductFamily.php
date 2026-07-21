<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductFamily extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'product_families';

    protected $fillable = [
        'parent_id',
        'site_id',
        'name',
        'designation_longue',
        'libelle_court',
        'code_prefix',
        'type_categorie',
        'famille_principale_id',
        'code',
        'description',
        'type_flux',
        'gestion_stock',
        'stock_negatif',
        'unite_stock_id',
        'unite_achat_id',
        'unite_vente_id',
        'unite_poids_id',
        'coef_ua_us',
        'coef_uv_us',
        'densite',
        'poids_brut',
        'poids_net',
        'epaisseur',
        'metrage',
        'site_stockage_id',
        'gestion_lot',
        'lot_obligatoire',
        'suivi_bobine',
        'utilisable_production',
        'actif_tous_sites',
        'gestion_numero_serie',
        'controle_qualite',
        'cq_entree',
        'cq_sortie',
        'numerotation_auto',
        'prix_plancher_obligatoire',
        'autoriser_surcharge',
        'sale_account_id',
        'purchase_account_id',
        'stock_account_id',
        'section_analytique_id',
        'cost_center_id',
        'tax_rate_achat_id',
        'tax_rate_vente_id',
        'image',
        'depth',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type_flux'           => 'array',
        'gestion_stock'       => 'boolean',
        'stock_negatif'       => 'boolean',
        'gestion_lot'         => 'boolean',
        'lot_obligatoire'     => 'boolean',
        'suivi_bobine'        => 'boolean',
        'utilisable_production' => 'boolean',
        'actif_tous_sites'    => 'boolean',
        'gestion_numero_serie'=> 'boolean',
        'controle_qualite'    => 'boolean',
        'cq_entree'           => 'boolean',
        'cq_sortie'           => 'boolean',
        'numerotation_auto'   => 'boolean',
        'prix_plancher_obligatoire' => 'boolean',
        'autoriser_surcharge' => 'boolean',
        'coef_ua_us'          => 'decimal:6',
        'coef_uv_us'          => 'decimal:6',
        'densite'             => 'decimal:3',
        'poids_brut'          => 'decimal:4',
        'poids_net'           => 'decimal:4',
        'epaisseur'           => 'decimal:2',
        'metrage'             => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    public function unitePoids(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unite_poids_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'site_id');
    }

    public function famillePrincipale(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'famille_principale_id');
    }

    public function sectionAnalytique(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CostCenter::class, 'section_analytique_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CostCenter::class, 'cost_center_id');
    }

    public function taxRateAchat(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TaxRate::class, 'tax_rate_achat_id');
    }

    public function taxRateVente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TaxRate::class, 'tax_rate_vente_id');
    }

    /** Dépôts autorisés pour cette catégorie (pivot avec 4 capacités). */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Warehouse::class, 'category_warehouse')
            ->withPivot(['can_production', 'can_sale', 'can_purchase', 'can_stock'])
            ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The parent family (self-referential).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'parent_id');
    }

    /**
     * Direct child families (self-referential).
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductFamily::class, 'parent_id')
            ->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Products belonging to this family.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }

    /** [X3 §5] Articles rattachés à cette SOUS-famille (axe sub_family_id). */
    public function subProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'sub_family_id');
    }

    /**
     * Compte comptable de vente (classe 7).
     */
    public function saleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sale_account_id');
    }

    /**
     * Compte comptable d'achat (classe 6).
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    /**
     * Compte comptable de stock (classe 3).
     */
    public function stockAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'stock_account_id');
    }

    public function uniteStock(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Unit::class, 'unite_stock_id');
    }

    public function uniteAchat(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Unit::class, 'unite_achat_id');
    }

    public function uniteVente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Unit::class, 'unite_vente_id');
    }

    public function siteStockage(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'site_stockage_id');
    }

    public static function typeFluxOptions(): array
    {
        return [
            'achete'      => 'Acheté',
            'vendu'       => 'Vendu',
            'fabrique'    => 'Fabriqué',
            'sous_traite' => 'Sous-traité',
            'service'     => 'Service',
            'livrable'    => 'Livrable',
        ];
    }
}
