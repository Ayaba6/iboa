<?php

namespace App\Http\Requests\Sale\Concerns;

use App\Models\Product;
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
