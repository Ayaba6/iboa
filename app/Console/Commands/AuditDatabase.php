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

        // [PHASE 2.3] Contrôles supplémentaires exigés par la directive du 23/07
        $this->section('6. Documents sans lignes', function () {
            foreach ([
                ['invoices', 'invoice_items', 'invoice_id', ['brouillon', 'annulee']],
                ['orders', 'order_items', 'order_id', ['brouillon', 'annule']],
                ['quotes', 'quote_items', 'quote_id', ['brouillon', 'annule']],
                ['purchase_orders', 'purchase_order_items', 'purchase_order_id', ['brouillon', 'annule']],
                ['credit_notes', 'credit_note_items', 'credit_note_id', ['brouillon', 'annule']],
            ] as [$parent, $child, $fk, $okStatuses]) {
                if (! Schema::hasTable($parent) || ! Schema::hasTable($child)) {
                    continue;
                }
                $n = DB::table($parent)->whereNotIn('status', $okStatuses)
                    ->when(Schema::hasColumn($parent, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($child)->whereColumn("$child.$fk", "$parent.id"))
                    ->count();
                $this->report($n, "$parent : $n document(s) validé(s) SANS ligne");
            }
        });

        $this->section('7. Cohérence trésorerie ↔ paiements', function () {
            // Paiement client confirmé en caisse sans transaction de trésorerie
            $n = DB::table('client_payments as p')
                ->where('p.status', 'confirme')->whereNotNull('p.cash_account_id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cash_transactions as t')
                    ->whereColumn('t.reference_id', 'p.id')->where('t.reference_type', 'like', '%ClientPayment%'))
                ->count();
            $this->report($n, "$n encaissement(s) confirmé(s) en caisse SANS transaction de trésorerie");

            // Transaction de caisse orpheline de son paiement source
            $n = DB::table('cash_transactions as t')
                ->where('t.reference_type', 'like', '%ClientPayment%')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('client_payments as p')->whereColumn('p.id', 't.reference_id'))
                ->count();
            $this->report($n, "$n transaction(s) de caisse sans paiement source");
        });

        $this->section('8. Bobines et lots', function () {
            if (Schema::hasTable('coils') && Schema::hasColumn('coils', 'remaining_weight')) {
                $n = DB::table('coils')->whereColumn('remaining_weight', '>', 'initial_weight')->count();
                $this->report($n, "$n bobine(s) au poids restant > poids initial");
                $n = DB::table('coils')->where('remaining_weight', '<', 0)->count();
                $this->report($n, "$n bobine(s) au poids restant négatif");
            }
            if (Schema::hasTable('stock_lots')) {
                $n = DB::table('stock_lots')->where('quantity', '<', 0)->count();
                $this->report($n, "$n lot(s) à quantité négative");
            }
        });

        $this->section('9. Dates et périodes', function () {
            $n = DB::table('invoices')->whereNotNull('due_at')->whereColumn('due_at', '<', 'issued_at')->count();
            $this->report($n, "$n facture(s) à échéance antérieure à l'émission");

            // Écritures VALIDÉES sur période verrouillée (posées avant le verrou = normal ;
            // ce contrôle attrape une écriture postée APRÈS coup sur un mois verrouillé)
            if (Schema::hasTable('accounting_period_locks')) {
                $n = count(DB::select("
                    SELECT je.id FROM journal_entries je
                    JOIN accounting_period_locks l ON l.company_id = je.company_id
                     AND l.year = " . $this->yearExpr('je.entry_date') . "
                     AND l.month = " . $this->monthExpr('je.entry_date') . "
                    WHERE je.status = 'valide' AND je.validated_at > l.created_at"));
                $this->report($n, "$n écriture(s) validée(s) APRÈS le verrouillage de leur période");
            }
        });

        $this->section('11. Invariant des avoirs', function () {
            // [R2 §2] total_ttc = applied_amount + refunded_amount + remaining_credit
            if (Schema::hasColumn('credit_notes', 'refunded_amount')) {
                $n = DB::table('credit_notes')->whereNull('deleted_at')
                    ->whereRaw('total_ttc <> applied_amount + refunded_amount + remaining_credit')
                    ->count();
                $this->report($n, "$n avoir(s) à invariant rompu (total ≠ imputé + remboursé + disponible)");

                $n = DB::table('credit_notes')
                    ->where(fn ($q) => $q->where('applied_amount', '<', 0)->orWhere('refunded_amount', '<', 0)->orWhere('remaining_credit', '<', 0))
                    ->count();
                $this->report($n, "$n avoir(s) à composante négative");
            }
        });

        $this->section('10. Paie', function () {
            if (Schema::hasTable('payroll_bulletins')) {
                $n = DB::table('payroll_bulletins as b')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payroll_runs as r')->whereColumn('r.id', 'b.payroll_run_id'))
                    ->count();
                $this->report($n, "$n bulletin(s) sans run de paie");
            }
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

    /** Expression SQL année/mois portable MySQL + SQLite. */
    private function yearExpr(string $col): string
    {
        return DB::getDriverName() === "sqlite" ? "CAST(strftime('%Y', $col) AS INTEGER)" : "YEAR($col)";
    }

    private function monthExpr(string $col): string
    {
        return DB::getDriverName() === "sqlite" ? "CAST(strftime('%m', $col) AS INTEGER)" : "MONTH($col)";
    }
}