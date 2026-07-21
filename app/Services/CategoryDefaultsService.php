<?php

namespace App\Services;

use App\Models\ItemCategory;

/**
 * [X3 §7 — Héritage catégorie → article] À la création d'un article, la
 * catégorie sélectionnée fournit les valeurs par défaut ; la résolution est
 * global → site → saisie utilisateur. La saisie ne PREND le dessus que si le
 * champ figure dans la liste blanche overridable_fields de la catégorie
 * (vide/null = tout surchargeable — compatibilité).
 *
 * Ne s'applique JAMAIS rétroactivement aux articles existants : la
 * propagation est une action distincte et contrôlée (§8).
 */
class CategoryDefaultsService
{
    /** Correspondance champ article => valeur issue de la catégorie. */
    public function defaultsFor(ItemCategory $cat, ?int $siteId = null): array
    {
        $site = $cat->forSite($siteId);

        // COMPORTEMENT : imposé même à null (ex. MARCHANDISE ⇒ production_mode
        // null — un article marchandise ne peut pas se déclarer MTO).
        $behaviour = [
            'type_article'         => $this->natureToTypeArticle($cat->nature),
            'type'                 => $cat->nature === 'service' ? 'service' : 'simple',
            'is_purchasable'       => $cat->is_purchasable,
            'is_sellable'          => $cat->is_sellable,
            'is_stockable'         => $cat->is_stockable,
            'is_manufacturable'    => $cat->is_manufactured,
            'production_mode'      => in_array($cat->strategy, ['mto', 'mts'], true) ? $cat->strategy : null,
            'allow_negative_stock' => $cat->allow_negative_stock,
            'has_lot_number'       => $cat->lot_managed,
            'has_serial_number'    => $cat->serial_managed,
            'has_expiry_date'      => $cat->expiry_managed,
            'controle_qualite'     => $cat->qc_required || $cat->qc_on_receipt,
        ];

        // DÉFAUTS OPTIONNELS : appliqués seulement si définis sur la catégorie
        // (colonnes produits parfois NOT NULL — un null n'est pas injecté).
        $optional = array_filter([
            'valuation_method'     => $cat->valuation_method,
            'stock_min'            => $site['stock_min'],
            'stock_max'            => $site['stock_max'],
            'stock_securite'       => $site['stock_securite'],
            'sale_unit_id'         => $cat->default_sale_unit_id,
            'purchase_unit_id'     => $cat->default_purchase_unit_id,
            'tax_rate_id'          => $cat->default_tax_rate_id,
            'delivery_delay_days'  => $site['lead_time_days'],
            'production_warehouse_id' => $site['mp_warehouse_id'],
            'sale_warehouse_id'    => $site['pf_warehouse_id'],
            'stock_account_id'     => $cat->stock_account_id,
            'purchase_account_id'  => $cat->purchase_account_id,
            'sale_account_id'      => $cat->sale_account_id,
            'variation_stock_account_id' => $cat->variation_account_id,
        ], fn ($v) => $v !== null);

        return $behaviour + $optional;
    }

    /**
     * Fusionne défauts + saisie : la saisie gagne uniquement sur les champs
     * autorisés (overridable_fields) ; ailleurs, la catégorie impose sa valeur.
     */
    public function apply(array $input, ItemCategory $cat, ?int $siteId = null): array
    {
        $defaults    = $this->defaultsFor($cat, $siteId);
        $overridable = $cat->overridable_fields; // null/[] = tout surchargeable

        foreach ($defaults as $field => $value) {
            $userProvided = array_key_exists($field, $input) && $input[$field] !== null && $input[$field] !== '';
            $mayOverride  = empty($overridable) || in_array($field, $overridable, true);

            if (! $userProvided || ! $mayOverride) {
                $input[$field] = $value;
            }
        }

        return $input;
    }

    private function natureToTypeArticle(string $nature): string
    {
        return match ($nature) {
            'matiere_premiere'          => 'matiere_premiere',
            'semi_fini', 'produit_fini' => 'produit_fini',
            'marchandise'               => 'marchandise',
            'consommable'               => 'consommable',
            'service'                   => 'service',
            'sous_produit', 'chute'     => 'produit_fini',
            'rebut'                     => 'produit_fini',
        };
    }
}
