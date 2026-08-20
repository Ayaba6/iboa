<?php

namespace App\Http\Requests\Sale\Concerns;

use App\Models\Product;
use App\Services\Sales\CommercialLinePriceRule;
use App\Services\SalesPriceGuardService;
use Illuminate\Validation\Validator;

/**
 * [CDC OA-12 — règle 4] Prix plancher : toute vente en dessous du seuil défini
 * sur l'article (products.min_sale_price) est bloquée à la validation.
 * S'applique aux devis, commandes et factures (Store + Update).
 */
trait ChecksFloorPrice
{
    public function checkFloorPrice(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('status') || $this->input('status') === 'brouillon') {
                return;
            }
            $items = (array) $this->input('items', []);
            $ids = collect($items)->pluck('product_id')->filter()->unique()->values();
            if ($ids->isEmpty()) {
                return;
            }

            $floors = Product::whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Product $product) => [
                    $product->id => app(SalesPriceGuardService::class)->effectiveFloor($product),
                ])
                ->filter(fn ($floor) => $floor > 0);

            // [BUG-A3-SALES-ZERO-PRICE-026] GARDE 1 — prix net nul, contrôlée
            // sur TOUTES les lignes, y compris celles dont l'article n'a aucun
            // plancher connu. C'est précisément ce cas qui laissait passer une
            // vente gratuite : `$floors` est filtré sur `floor > 0`, donc un
            // article sans coût en était absent et échappait à tout contrôle.
            //
            // Le calcul porte sur le prix NET : une remise de 100 % produit une
            // gratuité que `unit_price` seul ne révèle pas.
            foreach ($items as $i => $item) {
                if (! ($item['product_id'] ?? null)) {
                    continue;
                }

                $devise = $this->input('currency_code');

                $net = CommercialLinePriceRule::montantNetLigne(
                    (float) ($item['unit_price'] ?? 0),
                    (float) ($item['quantity'] ?? 1),
                    (float) ($item['discount_percent'] ?? 0),
                    0,
                    $devise,
                );

                if (CommercialLinePriceRule::estGratuit($net, $devise)) {
                    $v->errors()->add("items.$i.unit_price", sprintf(
                        'Ligne %d : %s', $i + 1,
                        CommercialLinePriceRule::messageGratuite(),
                    ));
                }
            }

            // GARDE 2 — sous le plancher, dérogeable.
            foreach ($items as $i => $item) {
                $pid = $item['product_id'] ?? null;
                if (! $pid || ! isset($floors[$pid])) {
                    continue;
                }
                $floor = (float) $floors[$pid];
                $price = (float) ($item['unit_price'] ?? 0);
                if ($price < $floor) {
                    $v->errors()->add("items.$i.unit_price", sprintf(
                        'Ligne %d : prix de vente (%s F) inférieur au prix plancher de l\'article (%s F) — vente bloquée.',
                        $i + 1,
                        number_format($price, 0, ',', ' '),
                        number_format($floor, 0, ',', ' ')
                    ));
                }
            }
        });
    }
}
