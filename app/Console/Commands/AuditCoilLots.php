<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Audit (et reconciliation optionnelle) des quatre referentiels matiere :
 *
 *   A = sum coils.remaining_weight        (bobines physiques, KG)
 *   B = sum stock_lots.quantity           (lots, KG)
 *   C = product_stocks.quantity           (stock agrege)
 *   D = solde stock_movements             (entrees - sorties, unite stock)
 *
 * Dry-run par defaut : AUCUNE modification sans --fix. Les reconciliations
 * restent tracees par mouvements d'ajustement explicites, avec run-id,
 * rapport JSON et possibilite de rejeu idempotent d'un meme run.
 */
class AuditCoilLots extends Command
{
    private const ANOMALY_TOLERANCE_QTY = 0.01;

    private const ANOMALY_TOLERANCE_UNIT = 'KG';

    protected $signature = 'stock:audit-coil-lots
                            {--product= : Limiter a un product_id}
                            {--warehouse= : Limiter a un warehouse_id}
                            {--fix : Creer les mouvements de reconciliation (sinon dry-run)}
                            {--run-id= : Identifiant stable du lot de correction/rejeu}
                            {--revert-run= : Extourner un lot de reconciliation deja applique}
                            {--report= : Chemin du rapport JSON avant/apres}
                            {--backup-id= : Identifiant de sauvegarde prealable obligatoire hors base de test dediee}
                            {--backup-file= : Fichier de sauvegarde prealable obligatoire hors base de test dediee}
                            {--confirm-db= : Nom exact de la base a confirmer hors base de test dediee}
                            {--allow-production : Autoriser exceptionnellement --fix en production}
                            {--force : Ne pas demander de confirmation interactive hors tests}
                            {--dry-run : Forcer la simulation (defaut)}';

    protected $description = 'Audit de coherence bobines / lots / mouvements / product_stocks (KG)';

    /** @var array<int, int|null> */
    private array $warehouseResolutionCache = [];

    private ?string $lastReportSha256 = null;

    private ?string $lastReportSha256File = null;

    public function handle(): int
    {
        try {
            $revertRun = $this->option('revert-run');
            if (is_string($revertRun) && $revertRun !== '') {
                return $this->revertRun($revertRun);
            }

            return $this->auditAndMaybeFix();
        } catch (\Throwable $e) {
            Log::error('[AUDIT stock:audit-coil-lots] echec', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function auditAndMaybeFix(): int
    {
        $productFilter = $this->option('product');
        $warehouseFilter = $this->option('warehouse');
        $fix = (bool) $this->option('fix') && ! $this->option('dry-run');
        $runIdOption = $this->option('run-id');
        $runId = (string) ($runIdOption ?: 'coil-audit-'.now()->format('Ymd-His'));
        $safety = $this->buildSafetyContext($runId, $runIdOption !== null && $runIdOption !== '');

        if ($fix) {
            $this->assertFixSafetyPreconditions($safety);
        }

        $before = $this->collectScope($productFilter, $warehouseFilter);
        if ($before['summary']['group_count'] === 0) {
            $reportPath = $this->writeReport([
                'meta' => $this->reportMeta('dry-run', $runId, $productFilter, $warehouseFilter, $safety),
                'before' => $before,
                'after' => null,
                'applied' => [],
                'warnings' => $this->reportWarnings(),
            ]);
            $this->info('Aucun article gere en bobines dans le perimetre.');
            $this->line($this->reportDisplay($reportPath));

            return self::SUCCESS;
        }

        $this->renderAudit('Avant correction', $before);

        $report = [
            'meta' => $this->reportMeta($fix ? 'fix' : 'dry-run', $runId, $productFilter, $warehouseFilter, $safety),
            'before' => $before,
            'after' => null,
            'applied' => [],
            'warnings' => $this->reportWarnings(),
        ];

        if (! $fix) {
            $reportPath = $this->writeReport($report);
            if ($before['summary']['anomalies'] > 0) {
                $this->warn('Aucune modification appliquee. Relancer avec --fix pour creer des mouvements opening_reconciliation traces.');
                $this->warn('Cette commande ne poste AUCUNE ecriture comptable automatique : une regularisation GL doit etre decidee separement si necessaire.');
            }
            $this->line($this->reportDisplay($reportPath));

            return $before['summary']['anomalies'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        if (! $this->confirmDangerousAction(sprintf(
            'Base %s - appliquer %d correction(s) de stock sous le run-id %s ?',
            $safety['db_database'] ?: '(inconnue)',
            $before['summary']['fix_candidates'],
            $runId,
        ))) {
            $this->warn('Correction annulee.');
            $reportPath = $this->writeReport($report);
            $this->line($this->reportDisplay($reportPath));

            return self::FAILURE;
        }

        $applied = [];
        foreach ($before['rows'] as $row) {
            if (! $row['needs_fix']) {
                continue;
            }

            $productId = (int) $row['product_id'];
            $warehouseId = (int) $row['warehouse_id'];
            if (! $warehouseId) {
                continue;
            }

            $delta = round((float) $row['delta_a_c'], 4);
            if (abs($delta) <= self::ANOMALY_TOLERANCE_QTY) {
                continue;
            }

            $unitCost = (float) ($row['avg_cost'] ?: $row['lot_unit_cost'] ?: $row['coil_unit_cost'] ?: 0);
            $movement = app(StockService::class)->recordMovement([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'ajustement',
                'quantity' => $delta,
                'uom' => self::ANOMALY_TOLERANCE_UNIT,
                'conversion_factor' => 1,
                'quantity_in_stock_uom' => $delta,
                'stock_uom' => self::ANOMALY_TOLERANCE_UNIT,
                'unit_cost' => $unitCost,
                'reference_type' => self::class,
                'notes' => sprintf(
                    'opening_reconciliation run=%s product=%d warehouse=%d A=%.4f B=%.4f C=%.4f D=%.4f',
                    $runId, $productId, $warehouseId, (float) $row['a_qty'], (float) $row['b_qty'], (float) $row['c_qty'], (float) $row['d_qty'],
                ),
                'idempotency_key' => sprintf('opening-reconciliation:%s:%d:%d', $runId, $productId, $warehouseId),
                'allow_negative' => true,
            ]);

            $applied[] = [
                'movement_id' => $movement->id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'delta_applied' => $delta,
                'unit_cost' => $unitCost,
                'estimated_value_gap' => round(abs($delta) * $unitCost, 4),
                'idempotency_key' => $movement->idempotency_key,
                'created_by' => $movement->created_by,
                'occurred_at' => optional($movement->occurred_at)->toIso8601String(),
            ];

            Log::warning('[AUDIT stock:audit-coil-lots] Reconciliation appliquee', [
                'run_id' => $runId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'delta_a_minus_c' => $delta,
                'movement_id' => $movement->id,
            ]);
        }

        $after = $this->collectScope($productFilter, $warehouseFilter);
        $this->renderAudit('Apres correction', $after);

        $report['applied'] = $applied;
        $report['after'] = $after;
        $reportPath = $this->writeReport($report);

        $this->info(sprintf('Corrections appliquees : %d. Anomalies restantes : %d.', count($applied), $after['summary']['anomalies']));
        $this->warn('Aucune ecriture comptable automatique n a ete postee par cette commande : verifier la regularisation GL si la quantite corrigee porte une valeur comptable.');
        $this->line($this->reportDisplay($reportPath));

        return $after['summary']['anomalies'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function revertRun(string $runId): int
    {
        $movements = StockMovement::query()
            ->where('type', 'ajustement')
            ->where('idempotency_key', 'like', 'opening-reconciliation:'.$runId.':%')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            $this->warn('Aucun mouvement opening_reconciliation trouve pour ce run-id.');

            return self::FAILURE;
        }

        if (! $this->confirmDangerousAction(sprintf('Extourner %d mouvement(s) du run-id %s ?', $movements->count(), $runId))) {
            $this->warn('Extourne annulee.');

            return self::FAILURE;
        }

        $applied = [];
        foreach ($movements as $movement) {
            $conflicts = $this->revertConflicts($movement);
            if ($conflicts !== []) {
                throw new \RuntimeException(sprintf(
                    'Extourne refusee pour le run-id %s : des mouvements ulterieurs existent deja sur produit %d depot %d (%s).',
                    $runId, (int) $movement->product_id, (int) $movement->warehouse_id, implode(', ', $conflicts),
                ));
            }

            $delta = -round((float) ($movement->quantity_in_stock_uom ?? $movement->quantity), 4);
            $reverse = app(StockService::class)->recordMovement([
                'product_id' => $movement->product_id,
                'warehouse_id' => $movement->warehouse_id,
                'type' => 'ajustement',
                'quantity' => $delta,
                'uom' => $movement->stock_uom ?: $movement->uom ?: self::ANOMALY_TOLERANCE_UNIT,
                'conversion_factor' => $movement->conversion_factor ?: 1,
                'quantity_in_stock_uom' => $delta,
                'stock_uom' => $movement->stock_uom ?: self::ANOMALY_TOLERANCE_UNIT,
                'unit_cost' => (float) ($movement->unit_cost ?? 0),
                'reference_type' => self::class,
                'reversal_of_movement_id' => $movement->id,
                'notes' => sprintf('opening_reconciliation_reversal run=%s movement=%d', $runId, $movement->id),
                'idempotency_key' => sprintf('opening-reconciliation-reversal:%s:%d', $runId, $movement->id),
                'allow_negative' => true,
            ]);

            $applied[] = [
                'movement_id' => $reverse->id,
                'reversal_of_movement_id' => $movement->id,
                'product_id' => $movement->product_id,
                'warehouse_id' => $movement->warehouse_id,
                'delta_applied' => $delta,
                'occurred_at' => optional($reverse->occurred_at)->toIso8601String(),
            ];
        }

        $reportPath = $this->writeReport([
            'meta' => $this->reportMeta('revert', $runId, $this->option('product'), $this->option('warehouse'), $this->buildSafetyContext($runId, true)),
            'before' => null,
            'after' => null,
            'applied' => $applied,
            'warnings' => $this->reportWarnings(),
        ]);

        $this->info(sprintf('Extourne terminee : %d mouvement(s) crees.', count($applied)));
        $this->line($this->reportDisplay($reportPath));

        return self::SUCCESS;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function collectScope($productFilter, $warehouseFilter): array
    {
        $this->warehouseResolutionCache = [];

        $productIds = Product::query()
            ->leftJoin('item_categories', 'item_categories.id', '=', 'products.item_category_id')
            ->when($productFilter, fn ($q, $v) => $q->where('products.id', $v))
            ->where(function ($q) {
                $q->where('item_categories.coil_managed', true)
                    ->orWhereExists(function ($sq) {
                        $sq->selectRaw(1)
                            ->from('coils')
                            ->whereNull('coils.deleted_at')
                            ->whereColumn('coils.product_id', 'products.id');
                    });
            })
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return $this->emptyAuditSummary();
        }

        $coilMap = $this->resolveGroupedMap(
            DB::table('coils')
                ->selectRaw('product_id, warehouse_id, COUNT(*) as coil_count, SUM(remaining_weight) as qty, SUM(remaining_weight * COALESCE(cost_per_kg, 0)) as value_total, AVG(COALESCE(cost_per_kg, 0)) as unit_cost')
                ->whereNull('deleted_at')
                ->whereIn('product_id', $productIds)
                ->when($productFilter, fn ($q, $v) => $q->where('product_id', $v))
                ->when($warehouseFilter, fn ($q, $v) => $q->where('warehouse_id', $v))
                ->groupBy('product_id', 'warehouse_id')
                ->get(),
            ['coil_count', 'qty', 'value_total', 'unit_cost'],
            $warehouseFilter
        );

        $lotMap = $this->resolveGroupedMap(
            StockLot::query()
                ->selectRaw('product_id, warehouse_id, COUNT(*) as lot_count, SUM(quantity) as qty, SUM(quantity * COALESCE(unit_cost, 0)) as value_total, AVG(COALESCE(unit_cost, 0)) as unit_cost')
                ->whereIn('product_id', $productIds)
                ->when($productFilter, fn ($q, $v) => $q->where('product_id', $v))
                ->when($warehouseFilter, fn ($q, $v) => $q->where('warehouse_id', $v))
                ->groupBy('product_id', 'warehouse_id')
                ->get(),
            ['lot_count', 'qty', 'value_total', 'unit_cost'],
            $warehouseFilter
        );

        $stockMap = $this->resolveGroupedMap(
            ProductStock::query()
                ->selectRaw('product_id, warehouse_id, COUNT(*) as stock_rows, SUM(quantity) as qty, SUM(quantity * COALESCE(avg_cost, 0)) as value_total, AVG(COALESCE(avg_cost, 0)) as avg_cost')
                ->whereIn('product_id', $productIds)
                ->when($productFilter, fn ($q, $v) => $q->where('product_id', $v))
                ->when($warehouseFilter, fn ($q, $v) => $q->where('warehouse_id', $v))
                ->groupBy('product_id', 'warehouse_id')
                ->get(),
            ['stock_rows', 'qty', 'value_total', 'avg_cost'],
            $warehouseFilter
        );

        $movementMap = $this->resolveGroupedMap(
            StockMovement::query()
                ->selectRaw("product_id, warehouse_id, COUNT(*) as movement_count,
                    SUM(CASE
                        WHEN type IN ('entree','retour_client') THEN COALESCE(quantity_in_stock_uom, quantity)
                        WHEN type IN ('sortie','retour_fournisseur') THEN -COALESCE(quantity_in_stock_uom, quantity)
                        WHEN type = 'ajustement' THEN COALESCE(quantity_in_stock_uom, quantity)
                        ELSE 0
                    END) as qty,
                    SUM(CASE WHEN uom = 'ML' AND quantity_in_stock_uom IS NULL THEN 1 ELSE 0 END) as ml_without_stock_uom,
                    SUM(CASE WHEN uom IS NULL THEN 1 ELSE 0 END) as null_uom_count")
                ->whereIn('product_id', $productIds)
                ->when($productFilter, fn ($q, $v) => $q->where('product_id', $v))
                ->when($warehouseFilter, fn ($q, $v) => $q->where('warehouse_id', $v))
                ->groupBy('product_id', 'warehouse_id')
                ->get(),
            ['movement_count', 'qty', 'ml_without_stock_uom', 'null_uom_count'],
            $warehouseFilter
        );

        $keys = collect(array_keys($coilMap))
            ->merge(array_keys($lotMap))
            ->merge(array_keys($stockMap))
            ->merge(array_keys($movementMap))
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return $this->emptyAuditSummary();
        }

        $productNames = Product::whereIn('id', $keys->map(fn ($key) => (int) explode(':', $key)[0])->unique()->values())
            ->pluck('name', 'id');
        $warehouseNames = DB::table('warehouses')
            ->whereIn('id', $keys->map(fn ($key) => (int) explode(':', $key)[1])->filter()->unique()->values())
            ->pluck('name', 'id');

        $rows = [];
        foreach ($keys as $key) {
            [$productId, $warehouseId] = array_map('intval', explode(':', $key));
            $coil = $coilMap[$key] ?? [];
            $lot = $lotMap[$key] ?? [];
            $stock = $stockMap[$key] ?? [];
            $movement = $movementMap[$key] ?? [];

            $a = round((float) ($coil['qty'] ?? 0), 4);
            $b = round((float) ($lot['qty'] ?? 0), 4);
            $c = round((float) ($stock['qty'] ?? 0), 4);
            $d = round((float) ($movement['qty'] ?? 0), 4);
            $deltaAC = round($a - $c, 4);
            $deltaAB = round($a - $b, 4);
            $deltaDC = round($d - $c, 4);
            $referenceUnitCost = round((float) ($stock['avg_cost'] ?? $lot['unit_cost'] ?? $coil['unit_cost'] ?? 0), 4);
            $estimatedValueGapAC = round(abs($deltaAC) * $referenceUnitCost, 4);
            $isAnomaly = abs($deltaAC) > self::ANOMALY_TOLERANCE_QTY
                || abs($deltaAB) > self::ANOMALY_TOLERANCE_QTY
                || abs($deltaDC) > self::ANOMALY_TOLERANCE_QTY;

            $rows[] = [
                'product_id' => $productId,
                'product_name' => (string) ($productNames[$productId] ?? ('ID#'.$productId)),
                'warehouse_id' => $warehouseId,
                'warehouse_name' => (string) ($warehouseNames[$warehouseId] ?? ('W#'.$warehouseId)),
                'coil_count' => (int) ($coil['coil_count'] ?? 0),
                'lot_count' => (int) ($lot['lot_count'] ?? 0),
                'movement_count' => (int) ($movement['movement_count'] ?? 0),
                'a_qty' => $a,
                'b_qty' => $b,
                'c_qty' => $c,
                'd_qty' => $d,
                'delta_a_c' => $deltaAC,
                'delta_a_b' => $deltaAB,
                'delta_d_c' => $deltaDC,
                'value_a' => round((float) ($coil['value_total'] ?? 0), 2),
                'value_b' => round((float) ($lot['value_total'] ?? 0), 2),
                'value_c' => round((float) ($stock['value_total'] ?? 0), 2),
                'estimated_value_gap_a_c' => $estimatedValueGapAC,
                'avg_cost' => round((float) ($stock['avg_cost'] ?? 0), 4),
                'lot_unit_cost' => round((float) ($lot['unit_cost'] ?? 0), 4),
                'coil_unit_cost' => round((float) ($coil['unit_cost'] ?? 0), 4),
                'ml_without_stock_uom' => (int) ($movement['ml_without_stock_uom'] ?? 0),
                'null_uom_count' => (int) ($movement['null_uom_count'] ?? 0),
                'is_anomaly' => $isAnomaly,
                'needs_fix' => $warehouseId > 0 && abs($deltaAC) > self::ANOMALY_TOLERANCE_QTY,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['is_anomaly'] !== $b['is_anomaly']) {
                return $a['is_anomaly'] ? -1 : 1;
            }

            return [$a['product_id'], $a['warehouse_id']] <=> [$b['product_id'], $b['warehouse_id']];
        });

        return [
            'rows' => $rows,
            'summary' => [
                'group_count' => count($rows),
                'product_count' => collect($rows)->pluck('product_id')->unique()->count(),
                'warehouse_count' => collect($rows)->pluck('warehouse_id')->unique()->count(),
                'coil_count' => collect($rows)->sum('coil_count'),
                'lot_count' => collect($rows)->sum('lot_count'),
                'movement_count' => collect($rows)->sum('movement_count'),
                'anomalies' => collect($rows)->where('is_anomaly', true)->count(),
                'fix_candidates' => collect($rows)->where('needs_fix', true)->count(),
                'value_a' => round((float) collect($rows)->sum('value_a'), 2),
                'value_b' => round((float) collect($rows)->sum('value_b'), 2),
                'value_c' => round((float) collect($rows)->sum('value_c'), 2),
                'ml_without_stock_uom' => collect($rows)->sum('ml_without_stock_uom'),
                'null_uom_count' => collect($rows)->sum('null_uom_count'),
                'cumulative_abs_delta_a_c' => round((float) collect($rows)->sum(fn (array $row) => abs((float) $row['delta_a_c'])), 4),
                'cumulative_abs_delta_a_b' => round((float) collect($rows)->sum(fn (array $row) => abs((float) $row['delta_a_b'])), 4),
                'cumulative_abs_delta_d_c' => round((float) collect($rows)->sum(fn (array $row) => abs((float) $row['delta_d_c'])), 4),
                'estimated_value_gap_a_c' => round((float) collect($rows)->sum('estimated_value_gap_a_c'), 4),
            ],
        ];
    }

    /**
     * @param  iterable<int, object>  $groupedRows
     * @param  array<int, string>  $fields
     * @return array<string, array<string, float|int>>
     */
    private function resolveGroupedMap(iterable $groupedRows, array $fields, $warehouseFilter): array
    {
        $map = [];

        foreach ($groupedRows as $row) {
            $productId = (int) $row->product_id;
            $warehouseId = $this->resolveWarehouseId($productId, isset($row->warehouse_id) ? (int) $row->warehouse_id : null);
            if (! $warehouseId) {
                continue;
            }
            if ($warehouseFilter && (int) $warehouseFilter !== $warehouseId) {
                continue;
            }

            $key = $productId.':'.$warehouseId;
            if (! isset($map[$key])) {
                $map[$key] = [];
                foreach ($fields as $field) {
                    $map[$key][$field] = 0;
                }
            }

            foreach ($fields as $field) {
                $map[$key][$field] += (float) ($row->{$field} ?? 0);
            }
        }

        return $map;
    }

    private function resolveWarehouseId(int $productId, ?int $warehouseId): ?int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        if (array_key_exists($productId, $this->warehouseResolutionCache)) {
            return $this->warehouseResolutionCache[$productId];
        }

        $resolved = ProductStock::where('product_id', $productId)
            ->orderByDesc('quantity')
            ->value('warehouse_id');

        $resolved ??= StockLot::where('product_id', $productId)
            ->orderByDesc('quantity')
            ->value('warehouse_id');

        $resolved ??= StockMovement::where('product_id', $productId)
            ->whereNotNull('warehouse_id')
            ->orderByDesc('id')
            ->value('warehouse_id');

        $this->warehouseResolutionCache[$productId] = $resolved ? (int) $resolved : null;

        return $this->warehouseResolutionCache[$productId];
    }

    /**
     * @param  array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}  $audit
     */
    private function renderAudit(string $title, array $audit): void
    {
        $this->newLine();
        $this->info('=== '.$title.' ===');
        $this->table(
            ['Produit', 'Depot', 'Bob.', 'Lots', 'Mouv.', 'A', 'B', 'C', 'D', 'A-C', 'A-B', 'D-C', 'Val A-C', 'Etat'],
            array_map(function (array $row): array {
                return [
                    $row['product_id'].' '.$row['product_name'],
                    $row['warehouse_id'].' '.$row['warehouse_name'],
                    $row['coil_count'],
                    $row['lot_count'],
                    $row['movement_count'],
                    number_format((float) $row['a_qty'], 4, ',', ' '),
                    number_format((float) $row['b_qty'], 4, ',', ' '),
                    number_format((float) $row['c_qty'], 4, ',', ' '),
                    number_format((float) $row['d_qty'], 4, ',', ' '),
                    number_format((float) $row['delta_a_c'], 4, ',', ' '),
                    number_format((float) $row['delta_a_b'], 4, ',', ' '),
                    number_format((float) $row['delta_d_c'], 4, ',', ' '),
                    number_format((float) $row['estimated_value_gap_a_c'], 4, ',', ' '),
                    $row['is_anomaly'] ? 'ECART' : 'OK',
                ];
            }, $audit['rows'])
        );

        $summary = $audit['summary'];
        $this->line(sprintf(
            'Perimetre: %d groupe(s), %d produit(s), %d depot(s), %d bobine(s), %d lot(s), %d mouvement(s).',
            $summary['group_count'],
            $summary['product_count'],
            $summary['warehouse_count'],
            $summary['coil_count'],
            $summary['lot_count'],
            $summary['movement_count'],
        ));
        $this->line(sprintf(
            'Anomalies: %d. Corrections candidates: %d. Valorisation A/B/C: %.2f / %.2f / %.2f.',
            $summary['anomalies'],
            $summary['fix_candidates'],
            $summary['value_a'],
            $summary['value_b'],
            $summary['value_c'],
        ));
        $this->line(sprintf(
            'Tolerance absolue: %.4f %s. Ecarts cumules |A-C| / |A-B| / |D-C|: %.4f / %.4f / %.4f. Valeur theorique cumulee |A-C|: %.4f.',
            self::ANOMALY_TOLERANCE_QTY,
            self::ANOMALY_TOLERANCE_UNIT,
            $summary['cumulative_abs_delta_a_c'],
            $summary['cumulative_abs_delta_a_b'],
            $summary['cumulative_abs_delta_d_c'],
            $summary['estimated_value_gap_a_c'],
        ));
        if ($summary['ml_without_stock_uom'] > 0 || $summary['null_uom_count'] > 0) {
            $this->warn(sprintf(
                'Avertissements historiques: %d mouvement(s) ML sans quantity_in_stock_uom, %d mouvement(s) sans uom.',
                $summary['ml_without_stock_uom'],
                $summary['null_uom_count'],
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reportMeta(string $mode, string $runId, $productFilter, $warehouseFilter, array $safety): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app_env' => app()->environment(),
            'db_connection' => DB::getDefaultConnection(),
            'db_database' => DB::getDatabaseName(),
            'mode' => $mode,
            'run_id' => $runId,
            'filters' => [
                'product' => $productFilter !== null ? (int) $productFilter : null,
                'warehouse' => $warehouseFilter !== null ? (int) $warehouseFilter : null,
            ],
            'tolerance' => [
                'absolute_qty' => self::ANOMALY_TOLERANCE_QTY,
                'unit' => self::ANOMALY_TOLERANCE_UNIT,
                'type' => 'absolute',
                'configurable' => false,
                'source' => self::class,
            ],
            'reference_formulas' => [
                'A' => 'sum coils.remaining_weight (KG)',
                'B' => 'sum stock_lots.quantity (KG)',
                'C' => 'sum product_stocks.quantity (KG)',
                'D' => 'sum stock_movements in stock unit (KG)',
            ],
            'safety' => $safety,
            'user_id' => auth()->id(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reportWarnings(): array
    {
        return [
            'Cette commande n ecrit aucune ecriture comptable automatique ; verifier la regularisation GL separement si la quantite corrigee porte une valeur comptable.',
            'Les mouvements historiques sans quantity_in_stock_uom restent interpretes via quantity pour conserver la verite historique disponible.',
            'Le rejeu d un meme --run-id est idempotent via idempotency_key ; un run interrompu peut etre relance avec le meme run-id.',
            'La tolerance de 0.01 KG est absolue et s applique a ce perimetre bobines / lots / product_stocks / stock_movements.',
        ];
    }

    private function confirmDangerousAction(string $question): bool
    {
        if (app()->environment('testing') || (bool) $this->option('force')) {
            return true;
        }

        return $this->confirm($question, false);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(array $report): string
    {
        $path = (string) ($this->option('report') ?: storage_path('logs/audit-coil-lots-'.now()->format('Ymd-His').'.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->lastReportSha256 = hash_file('sha256', $path) ?: null;
        $this->lastReportSha256File = $path.'.sha256';
        if ($this->lastReportSha256) {
            File::put($this->lastReportSha256File, $this->lastReportSha256.'  '.basename($path).PHP_EOL);
        }

        return $path;
    }

    private function reportDisplay(string $path): string
    {
        if ($this->lastReportSha256) {
            return sprintf('Rapport : %s (SHA-256: %s)', $path, $this->lastReportSha256);
        }

        return 'Rapport : '.$path;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function emptyAuditSummary(): array
    {
        return [
            'rows' => [],
            'summary' => [
                'group_count' => 0, 'product_count' => 0, 'warehouse_count' => 0, 'coil_count' => 0, 'lot_count' => 0, 'movement_count' => 0,
                'anomalies' => 0, 'fix_candidates' => 0, 'value_a' => 0.0, 'value_b' => 0.0, 'value_c' => 0.0,
                'ml_without_stock_uom' => 0, 'null_uom_count' => 0, 'cumulative_abs_delta_a_c' => 0.0, 'cumulative_abs_delta_a_b' => 0.0,
                'cumulative_abs_delta_d_c' => 0.0, 'estimated_value_gap_a_c' => 0.0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSafetyContext(string $runId, bool $runIdExplicit): array
    {
        $connection = (string) DB::getDefaultConnection();
        $database = (string) (config("database.connections.{$connection}.database") ?? '');
        $driver = (string) (config("database.connections.{$connection}.driver") ?? '');
        $backupFile = (string) ($this->option('backup-file') ?: '');

        return [
            'db_connection' => $connection,
            'driver' => $driver, 'db_database' => $database, 'run_id' => $runId, 'run_id_explicit' => $runIdExplicit,
            'backup_id' => (string) ($this->option('backup-id') ?: ''), 'backup_file' => $backupFile,
            'backup_file_exists' => $backupFile !== '' ? File::exists($backupFile) : null,
            'confirm_db' => (string) ($this->option('confirm-db') ?: ''), 'allow_production' => (bool) $this->option('allow-production'),
            'dedicated_test_database' => $this->isDedicatedTestDatabase($driver, $database),
        ];
    }

    /**
     * @param  array<string, mixed>  $safety
     */
    private function assertFixSafetyPreconditions(array $safety): void
    {
        if (app()->environment('production') && ! $safety['allow_production']) {
            throw new \RuntimeException('Execution de --fix interdite en production sans --allow-production.');
        }
        if ($safety['dedicated_test_database']) {
            return;
        }
        if (! $safety['run_id_explicit']) {
            throw new \RuntimeException('Execution de --fix refusee : un --run-id explicite est obligatoire hors base de test dediee.');
        }
        if ($safety['backup_id'] === '' && $safety['backup_file'] === '') {
            throw new \RuntimeException('Execution de --fix refusee : sauvegarde obligatoire. Fournissez --backup-id ou --backup-file hors base de test dediee.');
        }
        if ($safety['backup_file'] !== '' && ! $safety['backup_file_exists']) {
            throw new \RuntimeException('Execution de --fix refusee : le fichier de sauvegarde declare est introuvable.');
        }
        if ($safety['confirm_db'] !== $safety['db_database']) {
            throw new \RuntimeException(sprintf('Execution de --fix refusee : confirmez explicitement la base cible avec --confirm-db=%s.', $safety['db_database'] !== '' ? $safety['db_database'] : '(base inconnue)'));
        }
    }

    private function isDedicatedTestDatabase(string $driver, string $database): bool
    {
        if ($driver === 'sqlite' && $database === ':memory:') {
            return true;
        }
        if ($driver === 'mysql' && $database !== '') {
            return (bool) preg_match('/(^|_)(test|testing)(_|$)/i', $database);
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function revertConflicts(StockMovement $movement): array
    {
        return StockMovement::query()->where('product_id', $movement->product_id)->where('warehouse_id', $movement->warehouse_id)->where('id', '>', $movement->id)->where(function ($q) use ($movement) {
            $q->whereNull('reversal_of_movement_id')->orWhere('reversal_of_movement_id', '!=', $movement->id);
        })->orderBy('id')->limit(10)->get(['id', 'type', 'idempotency_key'])->map(fn ($row) => '#'.$row->id.':'.$row->type.':'.($row->idempotency_key ?: 'sans-cle'))->all();
    }
}
