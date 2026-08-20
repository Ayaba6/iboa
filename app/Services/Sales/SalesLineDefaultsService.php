<?php

namespace App\Services\Sales;

use App\Models\Product;

/**
 * [Ventes] Source UNIQUE des valeurs dérivées d'une ligne de vente : unité de
 * mesure et coût unitaire.
 *
 * Deux défauts constatés motivent ce service.
 *
 * 1. AUCUNE ligne ne portait d'unité. `quote_items.unit_id` et
 *    `order_items.unit_id` existent, les services les lisent
 *    (`$item['unit_id'] ?? null`), mais les formulaires ne les postaient jamais :
 *    4 lignes de devis sur 4 et 5 lignes de commande sur 5 avaient `unit_id`
 *    NULL. Sur un ERP métallurgie c'est structurel — une tôle bac se vend à la
 *    pièce ou au mètre linéaire, un fer à béton au kilo ou à la tonne. « Quantité
 *    50 » sans unité ne veut rien dire, et l'ambiguïté se propageait au bon de
 *    préparation, au bon de livraison puis à la facture.
 *
 * 2. Le COÛT n'existait que sur `invoice_items`, donc après la décision
 *    commerciale. La marge était incalculable au devis.
 *
 * Le choix de RÉSOUDRE côté serveur plutôt que d'exiger le champ au formulaire
 * est délibéré : 28 fichiers de test postent des lignes sans unité, et les
 * intégrations tierces existantes aussi. Exiger le champ aurait déplacé le
 * travail vers la réparation d'appelants au lieu de corriger la donnée.
 */
class SalesLineDefaultsService
{
    /**
     * Unité à retenir pour une ligne.
     *
     * Ordre : ce que l'utilisateur a choisi, puis l'unité de VENTE de l'article,
     * puis son unité de gestion. L'unité de vente primer sur l'unité de gestion
     * est le point important : un article géré au kilo peut se vendre à la barre.
     */
    public function resolveUnitId(?int $submittedUnitId, ?Product $product): ?int
    {
        if ($submittedUnitId) {
            return $submittedUnitId;
        }

        return $product?->sale_unit_id ?? $product?->unit_id;
    }

    /**
     * Coût unitaire à FIGER sur la ligne.
     *
     * Ordre : coût moyen pondéré (le coût réel du stock détenu), puis coût
     * standard (paramétré), puis dernier prix d'achat connu, puis prix d'achat de
     * référence. Le premier renseigné et strictement positif gagne.
     *
     * Un zéro n'est jamais retenu comme coût : il produirait une marge de 100 %
     * et masquerait précisément le cas à surveiller — un article dont le coût
     * n'est pas renseigné. On rend `null`, et l'écran affiche « — » au lieu d'une
     * marge fausse.
     */
    public function resolveUnitCost(?Product $product): ?float
    {
        if (! $product) {
            return null;
        }

        foreach (['weighted_avg_cost', 'cout_standard', 'last_purchase_price', 'purchase_price'] as $attribute) {
            $value = (float) ($product->{$attribute} ?? 0);
            if ($value > 0) {
                return round($value, 2);
            }
        }

        return null;
    }

    /**
     * Complète un tableau de ligne soumis. Ne remplace jamais une valeur fournie.
     *
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    public function apply(array $item): array
    {
        $product = ! empty($item['product_id']) ? Product::find($item['product_id']) : null;

        $item['unit_id'] = $this->resolveUnitId(
            isset($item['unit_id']) ? (int) $item['unit_id'] ?: null : null,
            $product
        );

        // `array_key_exists` et non `isset` : un coût explicitement nul doit être
        // respecté, alors qu'`isset` le confondrait avec une absence.
        if (! array_key_exists('unit_cost', $item) || $item['unit_cost'] === null) {
            $item['unit_cost'] = $this->resolveUnitCost($product);
        }

        return $item;
    }
}
