<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [QA §6] Audit de cohérence NON DESTRUCTIF de la base A3 ERP.
 * Ne modifie jamais rien — produit un rapport par section et un code de
 * sortie 1 si des anomalies existent (utilisable en recette/CI/cron).
 */
class AuditDatabase extends Command
{
    protected $signature = 'a3:audit-database';

    protected $description = 'Audit de cohérence de la base (orphelins, doublons, finance, stocks) — lecture seule';

    /** Relations métier critiques : [table enfant, colonne, table parent]. */
    private const RELATIONS = [
        ['order_items', 'order_id', 'orders'],
        ['order_items', 'product_id', 'products'],
        ['quote_items', 'quote_id', 'quotes'],
        ['invoice_items', 'invoice_id', 'invoices'],
        ['invoice_items', 'product_id', 'products'],
        ['delivery_note_items', 'delivery_note_id', 'delivery_notes'],
        ['client_payment_allocations', 'invoice_id', 'invoices'],
        ['client_payment_allocations', 'client_payment_id', 'client_payments'],
        ['supplier_payment_allocations', 'supplier_invoice_id', 'supplier_invoices'],
        ['stock_movements', 'product_id', 'products'],
        ['stock_movements', 'warehouse_id', 'warehouses'],
        ['product_stocks', 'product_id', 'products'],
        ['stock_reservations', 'product_id', 'products'],
        ['production_orders', 'product_id', 'products'],
        ['production_consumptions', 'production_order_id', 'production_orders'],
        ['production_outputs', 'production_order_id', 'production_orders'],
        ['journal_entry_lines', 'journal_entry_id', 'journal_entries'],
        ['journal_entry_lines', 'account_id', 'accounts'],
        ['invoices', 'client_id', 'clients'],
        ['orders', 'client_id', 'clients'],
        ['products', 'family_id', 'product_families'],
        ['products', 'sub_family_id', 'product_families'],
        ['products', 'item_category_id', 'item_categories'],
        ['inventory_items', 'product_id', 'products'],
        ['coils', 'product_id', 'products'],
        ['purchase_orders', 'supplier_id', 'suppliers'],
        ['supplier_invoices', 'supplier_id', 'suppliers'],
    ];

    private int $anomalies = 0;

    public function handle(): int
    {
        $this->section('1. Orphelins de clés étrangères', function () {
            foreach (self::RELATIONS as [$child, $col, $parent]) {
                if (! Schema::hasTable($child) || ! Schema::hasColumn($child, $col) || ! Schema::hasTable($parent)) {
                    continue;
                }
                $n = DB::table($child)->whereNotNull($col)
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($parent)->whereColumn("$parent.id", "$child.$col"))
                    ->count();
                $this->report($n, "$child.$col → $parent : $n orphelin(s)");
            }
        });

        $this->section('2. Doublons de références', function () {
            foreach ([
                ['quotes', 'number'], ['orders', 'number'], ['invoices', 'number'],
                ['delivery_notes', 'number'], ['client_payments', 'number'],
                ['production_orders', 'number'], ['purchase_orders', 'number'],
                ['products', 'code_article'], ['clients', 'name'], ['suppliers', 'name'],
                ['item_categories', 'code'], ['product_families', 'code'],
            ] as [$t, $c]) {
                if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) {
                    continue;
                }
                // Insensible à la casse et aux espaces parasites.
                $d = DB::table($t)->whereNotNull($c)->where($c, '!=', '')
                    ->when(Schema::hasColumn($t, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->groupBy(DB::raw("LOWER(TRIM($c))"))->havingRaw('COUNT(*) > 1')
                    ->selectRaw("LOWER(TRIM($c)) v, COUNT(*) n")->get();
                foreach ($d as $x) {
                    $this->report($x->n, "$t.$c « {$x->v} » ×{$x->n}");
                }
            }
        });

        $this->section('3. Données mal formées', function () {
            foreach ([
                ['clients', 'email'], ['suppliers', 'email'], ['users', 'email'],
            ] as [$t, $c]) {
                if (! Schema::hasColumn($t, $c)) {
                    continue;
                }
                $n = DB::table($t)->whereNotNull($c)->where($c, '!=', '')
                    ->where($c, 'NOT LIKE', '%@%.%')->count();
                $this->report($n, "$t.$c : $n email(s) invalide(s)");
            }
            foreach ([['products', 'code_article'], ['clients', 'name'], ['suppliers', 'name']] as [$t, $c]) {
                $n = DB::table($t)->whereNotNull($c)
                    ->where(fn ($q) => $q->where($c, 'LIKE', ' %')->orWhere($c, 'LIKE', '% '))->count();
                $this->report($n, "$t.$c : $n valeur(s) avec espaces parasites");
            }
        });

        $this->section('4. Cohérence financière', function () {
            $n = count(DB::select('
                SELECT journal_entry_id FROM journal_entry_lines
                GROUP BY journal_entry_id HAVING ABS(SUM(debit) - SUM(credit)) > 0.01'));
            $this->report($n, "$n écriture(s) déséquilibrée(s)");

            $n = count(DB::select("
                SELECT i.id FROM invoices i
                LEFT JOIN client_payment_allocations a ON a.invoice_id = i.id
                    AND a.client_payment_id IN (SELECT id FROM client_payments WHERE status='confirme')
                WHERE i.deleted_at IS NULL AND i.status NOT IN ('annulee','brouillon')
                GROUP BY i.id, i.total_ttc, i.remaining_amount
                HAVING ABS(i.total_ttc - COALESCE(SUM(a.amount),0) - i.remaining_amount) > 1"));
            $this->report($n, "$n facture(s) au solde incohérent");

            $n = DB::table('invoices')->where('status', 'payee')->where('remaining_amount', '>', 0)->whereNull('deleted_at')->count();
            $this->report($n, "$n facture(s) « payée » avec reste dû");
        });

        $this->section('5. Cohérence des stocks', function () {
            $n = DB::table('product_stocks as ps')->join('products as p', 'p.id', '=', 'ps.product_id')
                ->where('ps.quantity', '<', 0)->where('p.allow_negative_stock', 0)->count();
            $this->report($n, "$n stock(s) négatif(s) non autorisé(s)");

            $n = DB::table('product_stocks')->whereColumn('reserved_quantity', '>', 'quantity')->count();
            $this->report($n, "$n réservation(s) supérieure(s) au stock");

            $n = count(DB::select("
                SELECT ps.id FROM product_stocks ps
                WHERE ABS(ps.reserved_quantity - COALESCE((
                    SELECT SUM(r.quantity) FROM stock_reservations r
                    JOIN orders o ON o.id = r.order_id
                    WHERE r.product_id = ps.product_id AND r.warehouse_id = ps.warehouse_id
                      AND r.status = 'reserved' AND o.status NOT IN ('livre','facture','annule')), 0)
                  + COALESCE((
                    SELECT SUM(r2.quantity) FROM stock_reservations r2
                    WHERE r2.product_id = ps.product_id AND r2.warehouse_id = ps.warehouse_id
                      AND r2.status = 'reserved' AND r2.order_id IS NULL), 0) * 0) > 0.001
                  AND ps.reserved_quantity > 0
                  AND NOT EXISTS (
                    SELECT 1 FROM stock_reservations r3
                    WHERE r3.product_id = ps.product_id AND r3.warehouse_id = ps.warehouse_id
                      AND r3.status = 'reserved')"));
            $this->report($n, "$n réservation(s) fantôme(s) (aucune ligne de réservation vivante)");
        });

        $this->newLine();
        if ($this->anomalies === 0) {
            $this->info('AUDIT PROPRE — aucune anomalie détectée.');

            return self::SUCCESS;
        }
        $this->error("{$this->anomalies} anomalie(s) détectée(s) — voir sections ci-dessus. Aucune modification effectuée.");

        return self::FAILURE;
    }

    private function section(string $title, callable $checks): void
    {
        $this->newLine();
        $this->line("<comment>── $title ──</comment>");
        $before = $this->anomalies;
        $checks();
        if ($this->anomalies === $before) {
            $this->line('  OK');
        }
    }

    private function report(int $count, string $message): void
    {
        if ($count > 0) {
            $this->warn('  ⚠ ' . $message);
            $this->anomalies += $count;
        }
    }
}
