<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

// [Paramétrage Vente] Paramètres généraux du module vente — singleton par société.
class SalesSetting extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'reserve_stock_on_quote', 'allow_direct_invoicing',
        'enforce_price_floor', 'discount_validation_threshold', 'default_margin_min', 'deposit_required_rate',
        'quote_validity_days', 'block_sales_on_overdue', 'require_order_for_delivery',
        'default_sales_warehouse_id', 'quote_footer_note', 'invoice_footer_note',
    ];

    protected $casts = [
        'reserve_stock_on_quote'        => 'boolean',
        'allow_direct_invoicing'        => 'boolean',
        'enforce_price_floor'           => 'boolean',
        'block_sales_on_overdue'        => 'boolean',
        'require_order_for_delivery'    => 'boolean',
        'discount_validation_threshold' => 'decimal:2',
        'default_margin_min'            => 'decimal:2',
        'deposit_required_rate'         => 'decimal:2',
        'quote_validity_days'           => 'integer',
    ];

    /** Instance société courante (créée avec les défauts si absente). */
    public static function current(): self
    {
        $setting = static::firstOrCreate(['company_id' => currentCompany()->id]);
        if ($setting->wasRecentlyCreated) {
            $setting->refresh(); // hydrate les défauts définis côté base
        }

        return $setting;
    }
}
