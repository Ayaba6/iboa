<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [X3] CATÉGORIE d'article = modèle de gestion : détermine COMMENT l'article
 * fonctionne dans l'ERP (nature, flux, stratégie MTO/MTS, stock, production,
 * comptes) et fournit les valeurs par défaut héritées à la création d'article.
 *
 * Ne pas confondre avec ProductFamily (= classement commercial / statistique).
 * Les deux concepts restent dans des tables séparées.
 */
class ItemCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'is_active', 'sort_order', 'site_declinable',
        'nature',
        'is_purchasable', 'is_sellable', 'is_stockable', 'is_manufactured', 'is_subcontracted',
        'usable_in_bom', 'usable_as_finished',
        'strategy',
        'allow_negative_stock', 'lot_managed', 'serial_managed', 'coil_managed', 'expiry_managed',
        'qc_on_receipt', 'default_stock_min', 'default_stock_max', 'default_stock_securite', 'valuation_method',
        'default_sale_unit_id', 'default_pricing_unit_id', 'default_tax_rate_id', 'exempt_allowed',
        'floor_price_required', 'max_discount_percent', 'deposit_required', 'credit_check',
        'default_purchase_unit_id', 'receipt_tolerance_percent', 'lead_time_days', 'default_receipt_warehouse_id',
        'bom_required', 'routing_required', 'auto_of', 'qc_required',
        'default_mp_warehouse_id', 'default_pf_warehouse_id', 'default_production_line_id',
        'setup_loss', 'scrap_rate_percent', 'offcut_managed', 'cutting_optimized', 'mrp_planned',
        'stock_account_id', 'purchase_account_id', 'sale_account_id', 'variation_account_id',
        'consumption_account_id', 'scrap_account_id', 'finished_account_id', 'analytic_section_id', 'cost_method',
        'overridable_fields',
    ];

    protected $casts = [
        'is_active' => 'boolean', 'site_declinable' => 'boolean',
        'is_purchasable' => 'boolean', 'is_sellable' => 'boolean', 'is_stockable' => 'boolean',
        'is_manufactured' => 'boolean', 'is_subcontracted' => 'boolean',
        'usable_in_bom' => 'boolean', 'usable_as_finished' => 'boolean',
        'allow_negative_stock' => 'boolean', 'lot_managed' => 'boolean', 'serial_managed' => 'boolean',
        'coil_managed' => 'boolean', 'expiry_managed' => 'boolean', 'qc_on_receipt' => 'boolean',
        'exempt_allowed' => 'boolean', 'floor_price_required' => 'boolean', 'deposit_required' => 'boolean',
        'credit_check' => 'boolean', 'bom_required' => 'boolean', 'routing_required' => 'boolean',
        'auto_of' => 'boolean', 'qc_required' => 'boolean', 'offcut_managed' => 'boolean',
        'cutting_optimized' => 'boolean', 'mrp_planned' => 'boolean',
        'overridable_fields' => 'array',
        'default_stock_min' => 'decimal:2', 'default_stock_max' => 'decimal:2', 'default_stock_securite' => 'decimal:2',
        'max_discount_percent' => 'decimal:2', 'receipt_tolerance_percent' => 'decimal:2',
        'setup_loss' => 'decimal:3', 'scrap_rate_percent' => 'decimal:2',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'item_category_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(ItemCategorySite::class);
    }

    /** [X3 §10] Attributs dynamiques exigibles sur les articles de la catégorie. */
    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort_order');
    }

    public function defaultSaleUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'default_sale_unit_id'); }
    public function defaultPurchaseUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'default_purchase_unit_id'); }
    public function defaultPricingUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'default_pricing_unit_id'); }
    public function defaultTaxRate(): BelongsTo { return $this->belongsTo(TaxRate::class, 'default_tax_rate_id'); }

    /** Surcharge par site : valeur du site prioritaire sur la valeur globale. */
    public function forSite(?int $siteId): array
    {
        $global = [
            'mp_warehouse_id'      => $this->default_mp_warehouse_id,
            'pf_warehouse_id'      => $this->default_pf_warehouse_id,
            'receipt_warehouse_id' => $this->default_receipt_warehouse_id,
            'production_line_id'   => $this->default_production_line_id,
            'lead_time_days'       => $this->lead_time_days,
            'stock_min'            => $this->default_stock_min,
            'stock_max'            => $this->default_stock_max,
            'stock_securite'       => $this->default_stock_securite,
            'mrp_planned'          => $this->mrp_planned,
        ];
        if (! $siteId || ! $this->site_declinable) {
            return $global;
        }
        $site = $this->sites->firstWhere('site_id', $siteId);
        if (! $site) {
            return $global;
        }
        foreach ($global as $k => $v) {
            if ($site->$k !== null) {
                $global[$k] = $site->$k;
            }
        }

        return $global;
    }

    public function isUsed(): bool
    {
        return $this->products()->exists();
    }
}
