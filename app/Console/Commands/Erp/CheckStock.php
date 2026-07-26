<?php

namespace App\Console\Commands\Erp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan erp:check-stock
 *
 * Vérifie l'intégrité des stocks :
 *  - Niveaux négatifs
 *  - Stock réservé > stock disponible
 *  - Mouvements sans référence source
 *  - Incohérence entre product_stocks et sum(stock_movements)
 */
class CheckStock extends Command
{
    protected $signature = 'erp:check-stock
                                {--product= : Vérifier uniquement l\'article ID}
                                {--warehouse= : Filtrer par entrepôt ID}
                                {--recalc : Recalculer les niveaux depuis les mouvements (READ-ONLY sans --apply)}
                                {--apply : Appliquer les corrections de recalcul (nécessite --recalc)}';

    protected $description = 'Contrôle d\'intégrité des stocks';

    public function handle(): int
    {
        $issues = 0;

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ERP — Contrôle des stocks');
        $this->info('═══════════════════════════════════════════════════════');

        // ── 1. Stock négatif ──────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Niveaux de stock négatifs</>');
        $q = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->join('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->where('ps.quantity', '<', 0)
            ->select('ps.id', 'p.reference', 'p.name', 'w.name as warehouse',
                'ps.quantity', 'ps.reserved_quantity');
        if ($this->option('product')) {
            $q->where('ps.product_id', $this->option('product'));
        }
        if ($this->option('warehouse')) {
            $q->where('ps.warehouse_id', $this->option('warehouse'));
        }

        $negative = $q->get();
        if ($negative->isEmpty()) {
            $this->line('  <fg=green>✓</> Aucun stock négatif');
        } else {
            $issues += $negative->count();
            $this->warn("  ✗ {$negative->count()} article(s) en stock négatif :");
            $this->table(['Réf.', 'Article', 'Entrepôt', 'Quantité', 'Réservé'],
                $negative->map(fn ($r) => [$r->reference, $r->name, $r->warehouse,
                    $r->quantity, $r->reserved_quantity])->toArray());
        }

        // ── 2. Stock réservé > disponible ─────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Stock réservé supérieur au stock disponible</>');
        $q2 = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->join('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->whereColumn('ps.reserved_quantity', '>', 'ps.quantity')
            ->where('ps.reserved_quantity', '>', 0);
        $overReserved = $q2->select('p.reference', 'p.name', 'w.name as warehouse',
            'ps.quantity', 'ps.reserved_quantity')->get();
        if ($overReserved->isEmpty()) {
            $this->line('  <fg=green>✓</> Aucune sur-réservation détectée');
        } else {
            $issues += $overReserved->count();
            $this->warn("  ✗ {$overReserved->count()} sur-réservation(s) :");
            $this->table(['Réf.', 'Article', 'Entrepôt', 'Stock', 'Réservé'],
                $overReserved->map(fn ($r) => [$r->reference, $r->name, $r->warehouse,
                    $r->quantity, $r->reserved_quantity])->toArray());
        }

        // ── 3. Mouvements sans référence source ───────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Mouvements de stock sans référence source</>');
        $noRef = DB::table('stock_movements')
            ->whereNull('reference_type')
            ->whereNotIn('type', ['ajustement', 'inventaire'])
            ->count();
        if ($noRef === 0) {
            $this->line('  <fg=green>✓</> Tous les mouvements (hors ajustements) ont une référence');
        } else {
            $issues += $noRef;
            $this->warn("  ✗ {$noRef} mouvement(s) sans référence (hors ajustements manuels)");
        }

        // ── 4. Cohérence niveaux vs mouvements ────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Cohérence niveaux de stock ↔ mouvements</>');
        $this->line('  (Calcul : Σ entrées - Σ sorties depuis les mouvements par entrepôt)');

        // Calcul de la quantité théorique depuis les mouvements
        // Types entrants: entree, retour_client (qty +)
        // Types sortants: sortie, retour_fournisseur (qty -)
        // Ajustements: selon le signe de qty
        $sourceDeltas = DB::table('stock_movements')->select(
            'product_id',
            'warehouse_id',
            DB::raw('CASE
                WHEN type IN (\'entree\',\'retour_client\') THEN COALESCE(quantity_in_stock_uom, quantity)
                WHEN type IN (\'sortie\',\'retour_fournisseur\',\'transfert\') THEN -COALESCE(quantity_in_stock_uom, quantity)
                WHEN type = \'ajustement\' THEN COALESCE(quantity_in_stock_uom, quantity)
                ELSE 0
            END as quantity_delta')
        );
        $destinationTransferDeltas = DB::table('stock_movements')
            ->where('type', 'transfert')
            ->whereNotNull('to_warehouse_id')
            ->select(
                'product_id',
                DB::raw('to_warehouse_id as warehouse_id'),
                DB::raw('COALESCE(quantity_in_stock_uom, quantity) as quantity_delta')
            );

        $theoretical = DB::query()
            ->fromSub($sourceDeltas->unionAll($destinationTransferDeltas), 'movement_deltas')
            ->groupBy('product_id', 'warehouse_id')
            ->select('product_id', 'warehouse_id', DB::raw('SUM(quantity_delta) as theoretical_qty'))
            ->get()
            ->keyBy(fn ($r) => $r->product_id.'_'.$r->warehouse_id);

        $actual = DB::table('product_stocks')
            ->select('product_id', 'warehouse_id', 'quantity')
            ->get();

        $discrepancies = [];
        foreach ($actual as $row) {
            $key = $row->product_id.'_'.$row->warehouse_id;
            $theo = (float) ($theoretical->get($key)?->theoretical_qty ?? 0);
            $real = (float) $row->quantity;
            $diff = round(abs($theo - $real), 4);

            if ($diff > 0.01) {
                $discrepancies[] = [
                    'product_id' => $row->product_id,
                    'warehouse_id' => $row->warehouse_id,
                    'stock_reel' => $real,
                    'stock_theo' => $theo,
                    'ecart' => $theo - $real,
                ];
            }
        }

        if (empty($discrepancies)) {
            $this->line('  <fg=green>✓</> Niveaux de stock cohérents avec les mouvements');
        } else {
            $issues += count($discrepancies);
            $this->warn('  ✗ '.count($discrepancies).' incohérence(s) détectée(s) :');
            $rows = array_slice($discrepancies, 0, 20);
            $productNames = DB::table('products')->whereIn('id', array_column($rows, 'product_id'))
                ->pluck('name', 'id');
            $this->table(['Produit', 'Entrepôt', 'Niveau réel', 'Théorique', 'Écart'],
                collect($rows)->map(fn ($r) => [
                    ($productNames[$r['product_id']] ?? "ID#{$r['product_id']}"),
                    "W#{$r['warehouse_id']}",
                    $r['stock_reel'], $r['stock_theo'],
                    ($r['ecart'] > 0 ? '+' : '').$r['ecart'],
                ])->toArray());

            if ($this->option('recalc') && $this->option('apply')) {
                if ($this->confirm('⚠️  Appliquer le recalcul des niveaux de stock ?', false)) {
                    $fixed = 0;
                    foreach ($discrepancies as $disc) {
                        DB::table('product_stocks')
                            ->where('product_id', $disc['product_id'])
                            ->where('warehouse_id', $disc['warehouse_id'])
                            ->update(['quantity' => $disc['stock_theo']]);
                        $fixed++;
                    }
                    $this->info("  → {$fixed} niveau(x) recalculé(s) depuis les mouvements");
                }
            } elseif ($this->option('recalc')) {
                $this->line('  → Ajoutez <fg=cyan>--apply</> pour corriger les niveaux');
            }
        }

        // ── 5. Lots expirés toujours en stock ─────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Lots expirés avec stock positif</>');
        $expiredLots = DB::table('stock_lots')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('quantity', '>', 0)
            ->where('status', 'disponible')
            ->count();
        if ($expiredLots === 0) {
            $this->line('  <fg=green>✓</> Aucun lot expiré avec stock positif');
        } else {
            $issues += $expiredLots;
            $this->warn("  ✗ {$expiredLots} lot(s) expiré(s) avec quantité > 0 — vérifier en urgence");
        }

        // ── Rapport ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('─────────────────────────────────────────────────────');
        if ($issues === 0) {
            $this->info('  ✅  Stocks intègres — aucune anomalie détectée');
        } else {
            $this->warn("  ⚠️   {$issues} anomalie(s) de stock détectée(s)");
        }
        $this->info('═══════════════════════════════════════════════════════');

        return $issues > 0 ? self::FAILURE : self::SUCCESS;
    }
}
