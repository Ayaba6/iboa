<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Collection;

/**
 * [R2 §3 — écart de consommation matière] Écart QUANTITÉ entre la consommation
 * RÉELLE et la consommation THÉORIQUE de la nomenclature, valorisé.
 *
 * Distinct de l'écart de coût global (ProductionCost::variance = coût total réel
 * − coût standard) : ici on mesure, matière par matière, combien de kg ont été
 * réellement consommés face à ce que la nomenclature prévoit pour la quantité
 * produite. Surconsommation (réel > théorique) et sous-consommation (réel <
 * théorique) sont toutes deux restituées, valorisées au coût réel pondéré des
 * bobines effectivement consommées (multi-bobine à coûts différents géré).
 *
 * Théorique = quantity_per_meter × mètres produits × (1 + waste_rate/100).
 * Le taux de rebut de la nomenclature est une TOLÉRANCE : l'écart se mesure
 * au-delà de la matière normalement perdue.
 */
class ConsumptionVarianceService
{
    /**
     * @return array{lines: Collection, total_ecart_qty: float, total_ecart_value: int}
     */
    public function forOrder(ProductionOrder $order): array
    {
        $order->loadMissing(['consumptions' => fn ($q) => $q->active()->with('coil'), 'outputs', 'billOfMaterial']);

        // Mètres réellement produits (base du besoin théorique).
        $meters = (float) $order->outputs->sum('total_meters');
        if ($meters <= 0) {
            $meters = (float) $order->totalMeters();
        }

        // Consommation RÉELLE agrégée par produit-matière : poids + coût (le coût
        // reflète déjà le prix propre de chaque bobine → pondération correcte).
        $realByProduct = [];
        foreach ($order->consumptions as $c) {
            $pid = $c->coil?->product_id;
            if (! $pid) {
                continue;
            }
            $realByProduct[$pid] ??= ['weight' => 0.0, 'cost' => 0];
            $realByProduct[$pid]['weight'] += (float) $c->weight_consumed;
            $realByProduct[$pid]['cost']   += (int) $c->cost;
        }

        $lines = collect();
        $totalEcartQty   = 0.0;
        $totalEcartValue = 0;

        $bomLines = $order->bom_snapshot
            ? collect($order->bom_snapshot['lines'] ?? [])
            : ($order->bill_of_material_id
                ? $order->billOfMaterial?->lines()->get() ?? collect()
                : collect());

        // Produits couverts : lignes de nomenclature + toute matière consommée hors
        // nomenclature (substitution / matière non prévue → surconsommation nette).
        $productIds = $bomLines->map(fn ($line) => data_get($line, 'product_id'))->filter()
            ->merge(array_keys($realByProduct))->unique();

        foreach ($productIds as $pid) {
            $line = $bomLines->first(fn ($candidate) => (int) data_get($candidate, 'product_id') === (int) $pid);

            $theoretical = 0.0;
            if ($line) {
                $theoretical = (float) data_get($line, 'quantity_per_meter') * $meters
                    * (1 + (float) data_get($line, 'waste_rate') / 100);
            }

            $real     = (float) ($realByProduct[$pid]['weight'] ?? 0);
            $realCost = (int) ($realByProduct[$pid]['cost'] ?? 0);
            $ecart    = round($real - $theoretical, 4);

            // Coût unitaire réel pondéré (Σcoût / Σpoids) — sinon pas de valorisation.
            $unitCost   = $real > 0 ? $realCost / $real : 0.0;
            $ecartValue = (int) round($ecart * $unitCost);

            $lines->push([
                'product_id'      => $pid,
                'theoretical_qty' => round($theoretical, 4),
                'real_qty'        => round($real, 4),
                'ecart_qty'       => $ecart,
                'sens'            => $ecart > 0 ? 'surconsommation' : ($ecart < 0 ? 'sous-consommation' : 'conforme'),
                'unit_cost'       => round($unitCost, 4),
                'ecart_value'     => $ecartValue, // >0 défavorable (matière perdue en trop)
            ]);

            $totalEcartQty   += $ecart;
            $totalEcartValue += $ecartValue;
        }

        return [
            'lines'             => $lines,
            'total_ecart_qty'   => round($totalEcartQty, 4),
            'total_ecart_value' => $totalEcartValue,
        ];
    }
}
