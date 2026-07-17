<?php

namespace App\Console\Commands;

use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Audit (et réconciliation optionnelle) des quatre référentiels matière :
 *
 *   A = Σ coils.remaining_weight        (bobines physiques, KG)
 *   B = Σ stock_lots.quantity           (lots, KG)
 *   C = product_stocks.quantity         (stock agrégé)
 *   D = solde stock_movements           (entrées − sorties, en unité stock)
 *
 * Par produit + dépôt. Dry-run par défaut : AUCUNE modification sans --fix.
 * --fix crée des mouvements d'ajustement « opening_reconciliation » traçables
 * pour aligner C sur A (les bobines physiques font foi) — jamais de
 * modification silencieuse des lignes historiques.
 */
class AuditCoilLots extends Command
{
    protected $signature = 'stock:audit-coil-lots
                            {--product= : Limiter à un product_id}
                            {--warehouse= : Limiter à un warehouse_id}
                            {--fix : Créer les mouvements de réconciliation (sinon dry-run)}
                            {--dry-run : Forcer la simulation (défaut)}';

    protected $description = 'Audit de cohérence bobines / lots / mouvements / product_stocks (KG)';

    public function handle(): int
    {
        $productFilter   = $this->option('product');
        $warehouseFilter = $this->option('warehouse');
        $fix             = (bool) $this->option('fix') && ! $this->option('dry-run');

        // Périmètre : tous les produits ayant des bobines.
        $coilGroups = DB::table('coils')
            ->selectRaw('product_id, warehouse_id, SUM(remaining_weight) as total_weight, COUNT(*) as nb')
            ->whereNull('deleted_at')
            ->whereNotNull('product_id')
            ->when($productFilter, fn ($q, $v) => $q->where('product_id', $v))
            ->when($warehouseFilter, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        if ($coilGroups->isEmpty()) {
            $this->info('Aucune bobine dans le périmètre.');
            return self::SUCCESS;
        }

        // Regroupement APRÈS résolution du dépôt : une bobine sans warehouse_id
        // est rattachée au dépôt majoritaire du produit — sans ça, le même
        // produit×dépôt apparaît deux fois avec le même stock C.
        $resolved = [];
        foreach ($coilGroups as $g) {
            $productId   = (int) $g->product_id;
            $warehouseId = (int) ($g->warehouse_id
                ?? ProductStock::where('product_id', $productId)->orderByDesc('quantity')->value('warehouse_id'));
            $key = $productId . ':' . $warehouseId;
            $resolved[$key] ??= (object) ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'total_weight' => 0.0, 'nb' => 0];
            $resolved[$key]->total_weight += (float) $g->total_weight;
            $resolved[$key]->nb           += (int) $g->nb;
        }

        $rows = [];
        $anomalies = 0;

        foreach ($resolved as $g) {
            $productId   = $g->product_id;
            $warehouseId = $g->warehouse_id;

            $A = (float) $g->total_weight;

            $B = (float) StockLot::where('product_id', $productId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->sum('quantity');

            $C = (float) ProductStock::where('product_id', $productId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->sum('quantity');

            // Solde des mouvements en unité stock (quantity_in_stock_uom si
            // présent, sinon quantity — mouvements historiques sans conversion).
            $movements = StockMovement::where('product_id', $productId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->get(['type', 'quantity', 'quantity_in_stock_uom', 'uom']);

            $D = 0.0;
            $mlCount = 0;
            $noUomCount = 0;
            foreach ($movements as $m) {
                $qty = (float) ($m->quantity_in_stock_uom ?? $m->quantity);
                if ($m->uom === 'ML' && $m->quantity_in_stock_uom === null) {
                    $mlCount++;
                }
                if ($m->uom === null) {
                    $noUomCount++;
                }
                $D += match ($m->type) {
                    'entree', 'retour_client'        => $qty,
                    'sortie', 'retour_fournisseur'   => -$qty,
                    'ajustement'                     => $qty, // signé à la saisie ? historique : positif=ajout
                    default                          => 0,
                };
            }

            $ecartAC = round($A - $C, 2);
            $ecartAB = round($A - $B, 2);
            $ecartDC = round($D - $C, 2);
            $isAnomaly = abs($ecartAC) > 0.01 || abs($ecartDC) > 0.01;
            if ($isAnomaly) {
                $anomalies++;
            }

            $rows[] = [
                'product'   => $productId,
                'wh'        => $warehouseId ?: '—',
                'bobines'   => $g->nb,
                'A bobines' => number_format($A, 2, ',', ' '),
                'B lots'    => number_format($B, 2, ',', ' '),
                'C stock'   => number_format($C, 2, ',', ' '),
                'D solde'   => number_format($D, 2, ',', ' '),
                'A−C'       => number_format($ecartAC, 2, ',', ' '),
                'D−C'       => number_format($ecartDC, 2, ',', ' '),
                'ML?'       => $mlCount,
                's/uom'     => $noUomCount,
                'état'      => $isAnomaly ? '⚠ ÉCART' : '✓',
            ];

            // ── Réconciliation : C aligné sur A (le physique fait foi) ──
            if ($fix && abs($ecartAC) > 0.01 && $warehouseId) {
                app(\App\Services\StockService::class)->recordMovement([
                    'product_id'            => $productId,
                    'warehouse_id'          => $warehouseId,
                    'type'                  => 'ajustement',
                    'quantity'              => $ecartAC, // signé : négatif = retirer
                    'uom'                   => 'KG',
                    'conversion_factor'     => 1,
                    'quantity_in_stock_uom' => $ecartAC,
                    'stock_uom'             => 'KG',
                    'notes'                 => 'opening_reconciliation — Synchronisation bobines, lots et product_stocks en KG (audit ' . now()->format('d/m/Y') . ')',
                    'idempotency_key'       => 'opening-reconciliation:' . $productId . ':' . $warehouseId . ':' . now()->format('Ymd'),
                    'allow_negative'        => true,
                ]);

                Log::warning('[AUDIT stock:audit-coil-lots] Réconciliation appliquée', [
                    'product_id' => $productId, 'warehouse_id' => $warehouseId,
                    'ecart_A_moins_C' => $ecartAC,
                ]);
            }
        }

        $this->table(array_keys($rows[0]), $rows);
        $this->line('');
        $this->info(sprintf(
            '%d groupe(s) produit×dépôt audités — %d écart(s). Mode : %s.',
            count($rows), $anomalies, $fix ? 'FIX (mouvements de réconciliation créés)' : 'dry-run (aucune modification)'
        ));
        if (! $fix && $anomalies > 0) {
            $this->warn('Relancer avec --fix pour créer les mouvements opening_reconciliation (alignement C → A).');
        }

        return self::SUCCESS;
    }
}
