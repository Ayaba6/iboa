<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'products';

    protected $fillable = [
        'site_id',
        'reference',
        'code_article',
        'statut',
        'barcode',
        'name',
        'designation_2',
        'description',
        'client_type_canal',
        'production_warehouse_id',
        'sale_warehouse_id',
        'quality_warehouse_id',
        'seuil_alerte',
        'tax_rate_achat_id',
        'section_analytique_id',
        'cost_center_id',
        'nomenclature_ref',
        'profil',
        'couleur',
        'largeur_utile',
        'longueur_standard',
        'longueur_min',
        'longueur_max',
        'machine_defaut_id',
        'rendement_standard',
        'taux_perte',
        'article_avarie_id',
        'article_chute_id',
        'image',
        'family_id',
        'sub_family_id',
        'famille1_id',
        'famille2_id',
        'famille3_id',
        'famille4_id',
        'famille5_id',
        'item_category_id',
        'brand_id',
        'unit_id',
        'purchase_unit_id',
        'sale_unit_id',
        'weight_unit_id',
        'ua_to_us_coef',
        'uv_to_us_coef',
        'gross_weight_per_us',
        'net_weight_per_us',
        'thickness',
        'linear_meters',
        'density',
        'allow_negative_stock',
        'stock_securite',
        'main_warehouse_id',
        'tax_rate_id',
        'sale_account_id',
        'purchase_account_id',
        'stock_account_id',
        'default_supplier_id',
        'supplier_reference',
        'delivery_delay_days',
        'type',
        'is_stockable',
        'is_semi_finished',
        'production_mode',
        'is_purchasable',
        'is_sellable',
        'is_manufacturable',
        'purchase_price',
        'last_purchase_price',
        'weighted_avg_cost',
        'sale_price',
        'bande',
        'metrage',
        'min_sale_price',
        'max_sale_price',
        'margin_rate_target',
        'stock_min',
        'stock_max',
        'reorder_point',
        'valuation_method',
        'weight',
        'weight_unit',
        'has_serial_number',
        'has_lot_number',
        'has_expiry_date',
        'controle_qualite',
        'is_active',
        // Champs étendus article
        'designation_courte',
        'type_article',
        'cout_standard',
        'variation_stock_account_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_stockable'         => 'boolean',
        'is_purchasable'       => 'boolean',
        'is_sellable'          => 'boolean',
        'is_manufacturable'    => 'boolean',
        'has_serial_number'    => 'boolean',
        'has_lot_number'       => 'boolean',
        'has_expiry_date'      => 'boolean',
        'is_active'            => 'boolean',
        'allow_negative_stock' => 'boolean',
        'ua_to_us_coef'        => 'decimal:6',
        'uv_to_us_coef'        => 'decimal:6',
        'gross_weight_per_us'  => 'decimal:4',
        'net_weight_per_us'    => 'decimal:4',
        'thickness'            => 'decimal:2',
        'bande'                => 'decimal:2',
        'metrage'              => 'decimal:2',
        'linear_meters'        => 'decimal:2',
        'density'              => 'decimal:3',
        'largeur_utile'        => 'decimal:2',
        'longueur_standard'    => 'decimal:2',
        'longueur_min'         => 'decimal:3',
        'longueur_max'         => 'decimal:3',
        'rendement_standard'   => 'decimal:4',
        'taux_perte'           => 'decimal:4',
        'seuil_alerte'         => 'decimal:3',
        'stock_securite'       => 'decimal:3',
        'cout_standard'        => 'decimal:4',
        'controle_qualite'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            $product->created_by = auth()->id();
            $product->updated_by = auth()->id();
        });
        static::updating(function (self $product) {
            $product->updated_by = auth()->id();
        });
    }

    public static function typeArticleOptions(): array
    {
        return [
            'marchandise'      => 'Marchandise',
            'matiere_premiere' => 'Matière première',
            'produit_fini'     => 'Produit fini',
            'service'          => 'Service',
            'consommable'      => 'Consommable',
        ];
    }

    /** Statut actif (cahier des charges : actif / en sommeil). */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** [X3 §5] Sous-famille (doit appartenir à la famille — garde serveur). */
    public function subFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'sub_family_id');
    }

    public function famille1(): BelongsTo { return $this->belongsTo(ProductFamily::class, 'famille1_id'); }
    public function famille2(): BelongsTo { return $this->belongsTo(ProductFamily::class, 'famille2_id'); }
    public function famille3(): BelongsTo { return $this->belongsTo(ProductFamily::class, 'famille3_id'); }
    public function famille4(): BelongsTo { return $this->belongsTo(ProductFamily::class, 'famille4_id'); }
    public function famille5(): BelongsTo { return $this->belongsTo(ProductFamily::class, 'famille5_id'); }

    /** [X3] Catégorie de gestion (modèle de fonctionnement — distincte de la famille). */
    public function itemCategory(): BelongsTo { return $this->belongsTo(ItemCategory::class, 'item_category_id'); }

    /** [X3 §10] Déclinaisons article-site. */
    public function productSites(): HasMany { return $this->hasMany(ProductSite::class); }

    /** [X3 §10] Valeurs des attributs dynamiques (définis par la catégorie). */
    public function attributeValues(): HasMany { return $this->hasMany(ProductAttributeValue::class); }

    /**
     * [X3 §10] Paramètres résolus pour un site :
     * article-site > catégorie-site > catégorie globale > valeurs de l'article.
     */
    public function paramsForSite(?int $siteId): array
    {
        $base = [
            'mp_warehouse_id'      => $this->production_warehouse_id,
            'pf_warehouse_id'      => $this->sale_warehouse_id,
            'receipt_warehouse_id' => null,
            'production_line_id'   => null,
            'lead_time_days'       => $this->delivery_delay_days,
            'stock_min'            => $this->stock_min,
            'stock_max'            => $this->stock_max,
            'stock_securite'       => $this->stock_securite,
        ];

        // Catégorie (globale puis surcharge site)
        if ($this->itemCategory) {
            $catSite = $this->itemCategory->forSite($siteId);
            foreach (['mp_warehouse_id', 'pf_warehouse_id', 'receipt_warehouse_id', 'production_line_id', 'lead_time_days', 'stock_min', 'stock_max', 'stock_securite'] as $k) {
                if (($catSite[$k] ?? null) !== null) {
                    $base[$k] = $catSite[$k];
                }
            }
        }

        // Article-site : priorité maximale
        if ($siteId) {
            $ps = $this->productSites->firstWhere('site_id', $siteId);
            if ($ps) {
                foreach (array_keys($base) as $k) {
                    if ($ps->$k !== null) {
                        $base[$k] = $ps->$k;
                    }
                }
            }
        }

        return $base;
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'purchase_unit_id'); }
    public function saleUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'sale_unit_id'); }
    public function weightUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'weight_unit_id'); }
    public function mainWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'main_warehouse_id'); }
    public function site(): BelongsTo { return $this->belongsTo(Warehouse::class, 'site_id'); }
    public function productionWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'production_warehouse_id'); }
    public function saleWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'sale_warehouse_id'); }
    public function qualityWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'quality_warehouse_id'); }
    public function taxRateAchat(): BelongsTo { return $this->belongsTo(TaxRate::class, 'tax_rate_achat_id'); }
    public function sectionAnalytique(): BelongsTo { return $this->belongsTo(\App\Models\CostCenter::class, 'section_analytique_id'); }
    public function costCenter(): BelongsTo { return $this->belongsTo(\App\Models\CostCenter::class, 'cost_center_id'); }
    public function machineDefaut(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\ProductionMachine::class, 'machine_defaut_id'); }
    public function articleAvarie(): BelongsTo { return $this->belongsTo(Product::class, 'article_avarie_id'); }
    public function articleChute(): BelongsTo { return $this->belongsTo(Product::class, 'article_chute_id'); }

    /** Dépôts autorisés pour cet article (pivot avec 4 capacités). */
    public function warehouses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'product_warehouse')
            ->withPivot(['can_production', 'can_sale', 'can_purchase', 'can_stock'])
            ->withTimestamps();
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function saleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sale_account_id');
    }

    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    public function stockAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'stock_account_id');
    }

    public function variationStockAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'variation_stock_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    public function usedInComponents(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'component_product_id');
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function productPriceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /** Bobines physiques rattachées à cet article matière (1 bobine = 1 lot physique). */
    public function coils(): HasMany
    {
        return $this->hasMany(\App\Modules\Production\Models\Coil::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true);
    }

    public function scopeStockable(Builder $query): Builder
    {
        return $query->where('is_stockable', true);
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function currentStock(?int $warehouseId = null): float
    {
        $query = $this->productStocks();

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) $query->sum('quantity');
    }

    /**
     * Stock à terme = stock actuel + réceptions PO en attente − livraisons SO confirmées en attente.
     * Formule : actuel + (PO approuvés non reçus) − (commandes confirmées non livrées).
     */
    public function stockATerme(): float
    {
        $current = $this->productStocks->sum('quantity');

        $pendingIn = \Illuminate\Support\Facades\DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('poi.product_id', $this->id)
            ->whereIn('po.status', ['confirme', 'partiellement_recu'])
            ->selectRaw('COALESCE(SUM(poi.quantity - poi.received_quantity), 0) as pending')
            ->value('pending');

        $pendingOut = \Illuminate\Support\Facades\DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.product_id', $this->id)
            ->whereIn('o.status', ['confirme', 'partiellement_livre'])
            ->selectRaw('COALESCE(SUM(oi.quantity - oi.delivered_quantity), 0) as pending')
            ->value('pending');

        return (float) $current + (float) $pendingIn - (float) $pendingOut;
    }
}
