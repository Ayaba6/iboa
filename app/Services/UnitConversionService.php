<?php

namespace App\Services;

use App\Models\Product;

/**
 * [MULTI-UNITÉS SAGE] Conversion entre unité d'achat / de vente et unité de stock.
 *
 * Un article peut être acheté dans une unité (ex. KG), stocké et vendu dans une
 * autre (ex. MTL — mètre linéaire). Les coefficients portés par l'article donnent
 * la valeur en unité de STOCK d'une unité d'achat / de vente :
 *
 *   - products.ua_to_us_coef : 1 unité d'achat  = ua_to_us_coef unités de stock
 *   - products.uv_to_us_coef : 1 unité de vente = uv_to_us_coef unités de stock
 *
 * Exemple bobine (achat KG, stock MTL, coef 0,571102) : 100 KG → 57,11 MTL.
 *
 * Le coût suit l'inverse : coût/unité_stock = coût/unité_source ÷ coef
 * (la valeur totale est conservée : qty_source × coût_source = qty_stock × coût_stock).
 *
 * Coef nul / non renseigné ⇒ traité comme 1 (aucune conversion) : les articles
 * mono-unité restent inchangés.
 */
class UnitConversionService
{
    /** Quantité (unité d'achat|vente) → quantité en unité de stock. */
    public function toStockQuantity(Product $product, float $quantity, string $mode = 'achat'): float
    {
        return round($quantity * $this->coef($product, $mode), 4);
    }

    /** Coût unitaire (par unité d'achat|vente) → coût par unité de stock. */
    public function toStockUnitCost(Product $product, float $unitCost, string $mode = 'achat'): float
    {
        $coef = $this->coef($product, $mode);

        return $coef > 0 ? round($unitCost / $coef, 2) : $unitCost;
    }

    /** Coefficient effectif (>0), 1 par défaut si non renseigné. */
    public function coef(Product $product, string $mode = 'achat'): float
    {
        $c = (float) ($mode === 'vente' ? $product->uv_to_us_coef : $product->ua_to_us_coef);

        return $c > 0 ? $c : 1.0;
    }
}
