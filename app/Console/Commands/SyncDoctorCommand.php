<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Sync ERP] Diagnostic de cohérence inter-modules :
 * stocks négatifs, PMP nul avec stock, réceptions validées sans mouvement,
 * BL validés sans sortie, factures émises sans écriture comptable,
 * réservations orphelines.
 */
class SyncDoctorCommand extends Command
{
    protected $signature = 'sync:doctor';
    protected $description = 'Diagnostique les incohérences stock / ventes / achats / comptabilité';

    private int $issues = 0;

    public function handle(): int
    {
        $this->info('── Diagnostic de cohérence inter-modules ──');

        // 1. Stocks négatifs
        $this->section('Stocks négatifs', DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.quantity', '<', 0)
            ->selectRaw("CONCAT(p.name, ' — qté ', ps.quantity) as issue")
            ->pluck('issue'));

        // 2. PMP nul avec stock positif
        $this->section('PMP à zéro avec stock positif', DB::table('products as p')
            ->join('product_stocks as ps', 'ps.product_id', '=', 'p.id')
            ->where('p.weighted_avg_cost', 0)
            ->where('ps.quantity', '>', 0)
            ->distinct()
            ->pluck('p.name'));

        // 3. Réceptions validées sans aucun mouvement stock
        $this->section('Réceptions validées sans mouvement stock', DB::table('receptions as r')
            ->where('r.status', 'valide')
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('reception_items as ri')
                ->whereColumn('ri.reception_id', 'r.id')
                ->whereNotNull('ri.product_id')->where('ri.received_quantity', '>', 0))
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('stock_movements as sm')
                ->whereColumn('sm.reference_id', 'r.id')->where('sm.reference_type', 'reception'))
            ->pluck('r.number'));

        // 4. BL validés sans sortie stock
        $this->section('BL validés sans sortie stock', DB::table('delivery_notes as dn')
            ->where('dn.status', 'valide')
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('delivery_note_items as di')
                ->whereColumn('di.delivery_note_id', 'dn.id')->whereNotNull('di.product_id'))
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('stock_movements as sm')
                ->whereColumn('sm.reference_id', 'dn.id')->where('sm.reference_type', 'delivery_note'))
            ->pluck('dn.number'));

        // 5. Factures émises sans écriture comptable
        $this->section('Factures émises sans écriture comptable', DB::table('invoices as i')
            ->whereIn('i.status', ['emise', 'partiellement_payee', 'payee'])
            ->where('i.type', '!=', 'proforma')
            ->where('i.total_ttc', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('journal_entries as je')
                ->whereColumn('je.reference', 'i.number')->whereNull('je.deleted_at'))
            ->pluck('i.number'));

        // 6. Réservations sans commande active (fantômes)
        $this->section('Réservations de stock fantômes', DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.reserved_quantity', '>', 0)
            ->whereRaw('ps.reserved_quantity > ps.quantity')
            ->selectRaw("CONCAT(p.name, ' — réservé ', ps.reserved_quantity, ' / stock ', ps.quantity) as issue")
            ->pluck('issue'));

        $this->newLine();
        if ($this->issues === 0) {
            $this->info('✓ Aucune incohérence détectée — modules synchronisés.');
            return self::SUCCESS;
        }

        $this->warn("⚠ {$this->issues} incohérence(s) détectée(s). Consultez /admin/synchronisations et sync:replay --failed.");
        return self::FAILURE;
    }

    private function section(string $title, $items): void
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            $this->line("  ✓ {$title} : OK");
            return;
        }
        $this->issues += $items->count();
        $this->warn("  ✗ {$title} : {$items->count()}");
        foreach ($items->take(10) as $item) {
            $this->line("      - {$item}");
        }
        if ($items->count() > 10) {
            $this->line('      … et ' . ($items->count() - 10) . ' autres.');
        }
    }
}
