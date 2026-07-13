<?php

namespace App\Modules\Production\Services;

use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Optimisation de découpe 1D (cutting stock).
 *
 * Algorithme First-Fit-Decreasing : place les pièces (de la plus longue à la
 * plus courte) dans des barres/bobines de longueur standard en tenant compte
 * du trait de scie (kerf). Minimise les chutes et calcule le rendement matière.
 */
class CuttingOptimizerService
{
    /**
     * @param float $stockLength longueur d'une barre/bobine (mm ou m, cohérent)
     * @param float $kerf        trait de scie entre 2 coupes
     * @param array<int, array{length: float, quantity: int}> $items
     * @param float $minReusableOffcut seuil au-delà duquel une chute est réutilisable
     *                                  (0 = pas de valorisation, tout est rebut)
     */
    public function optimize(float $stockLength, float $kerf, array $items, float $minReusableOffcut = 0): array
    {
        if ($stockLength <= 0) {
            throw ValidationException::withMessages(['stock_length' => 'Longueur de stock invalide.']);
        }

        // Déplie les pièces, exclut celles trop longues
        $pieces = [];
        foreach ($items as $it) {
            $len = (float) ($it['length'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($len <= 0 || $qty <= 0) {
                continue;
            }
            if ($len > $stockLength + 1e-6) {
                throw ValidationException::withMessages(['items' => "Pièce de {$len} > longueur de stock {$stockLength}."]);
            }
            for ($i = 0; $i < $qty; $i++) {
                $pieces[] = $len;
            }
        }

        if (empty($pieces)) {
            return ['bars' => [], 'bars_count' => 0, 'used' => 0, 'waste' => 0, 'yield' => 0,
                'reusable_offcut' => 0, 'scrap' => 0, 'stock_length' => $stockLength];
        }

        rsort($pieces); // décroissant

        $bars = []; // chaque barre = ['cuts'=>[], 'remaining'=>float]
        foreach ($pieces as $p) {
            $placed = false;
            foreach ($bars as &$bar) {
                $need = $p + (count($bar['cuts']) > 0 ? $kerf : 0);
                if ($bar['remaining'] + 1e-6 >= $need) {
                    $bar['cuts'][] = $p;
                    $bar['remaining'] -= $need;
                    $placed = true;
                    break;
                }
            }
            unset($bar);
            if (! $placed) {
                $bars[] = ['cuts' => [$p], 'remaining' => $stockLength - $p];
            }
        }

        $totalStock = count($bars) * $stockLength;
        $used       = array_sum($pieces);
        $waste      = $totalStock - $used;

        // Valorisation : chaque reste de barre est soit une chute réutilisable
        // (>= seuil), soit du rebut. Seuil 0 → tout est rebut.
        $reusable = 0.0;
        $scrap    = 0.0;
        $barRows  = [];
        foreach ($bars as $b) {
            $rem = $b['remaining'];
            $isReusable = $minReusableOffcut > 0 && $rem + 1e-6 >= $minReusableOffcut;
            if ($isReusable) {
                $reusable += $rem;
            } else {
                $scrap += $rem;
            }
            $barRows[] = [
                'cuts'        => $b['cuts'],
                'used'        => round($stockLength - $rem, 2),
                'waste'       => round($rem, 2),
                'offcut_type' => $rem <= 1e-6 ? 'aucune' : ($isReusable ? 'reutilisable' : 'rebut'),
            ];
        }

        return [
            'bars'            => $barRows,
            'bars_count'      => count($bars),
            'stock_length'    => $stockLength,
            'used'            => round($used, 2),
            'waste'           => round($waste, 2),
            'reusable_offcut' => round($reusable, 2),
            'scrap'           => round($scrap, 2),
            'yield'           => $totalStock > 0 ? round($used / $totalStock * 100, 1) : 0,
        ];
    }
}
