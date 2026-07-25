<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business-logic integrity audit across all ERP modules.
 *
 * Surfaces real-world incoherences :
 *   - Comptabilité  : balanced entries, line/header totals, account balances vs ledger
 *   - Ventes        : invoice payment status (withholding-aware), credit-notes (client balance)
 *   - Achats        : supplier invoice payment status
 *   - Stock         : product_stocks vs movements, negatives, over-reservation
 *   - Trésorerie    : cash balance vs movements
 *   - Numérotation  : duplicate document numbers
 *
 * Usage:
 *   php artisan audit:business
 *   php artisan audit:business --json     # machine-readable
 *   php artisan audit:business --module=ventes  # filter by module
 *
 * Designed for scheduling — exits with non-zero status if any HIGH-severity issue is found,
 * so it can be wired to alerting (cron + mail).
 */
class AuditBusiness extends Command
{
    protected $signature = 'audit:business
                            {--json : Output as JSON instead of human-readable table}
                            {--module= : Filter findings to a single module (Compta, Ventes, Achats, Stock, Trésorerie, Clients, Numéro)}
                            {--fail-on=HIGH : Exit non-zero when an issue of this severity or worse is found (HIGH|MED|LOW|never)}';

    protected $description = 'Audit business-logic integrity across all ERP modules and report incoherences.';

    /** @var array<int, array{module:string,severity:string,title:string,count:int,sample:mixed}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->collectComptaFindings();
        $this->collectVentesFindings();
        $this->collectAchatsFindings();
        $this->collectStockFindings();
        $this->collectTresorerieFindings();
        $this->collectClientsFindings();
        $this->collectNumberingFindings();

        // Module filter
        if ($module = $this->option('module')) {
            $this->findings = array_values(array_filter(
                $this->findings,
                fn ($f) => mb_strtolower($f['module']) === mb_strtolower($module)
            ));
        }

        // Sort : severity (HIGH first), then issues before OK
        $rank = ['HIGH' => 0, 'MED' => 1, 'LOW' => 2];
        usort($this->findings, fn ($a, $b) => [$rank[$a['severity']], $a['count'] > 0 ? 0 : 1, $a['module']]
            <=>
            [$rank[$b['severity']], $b['count'] > 0 ? 0 : 1, $b['module']]
        );

        if ($this->option('json')) {
            $this->line(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable();
        }

        // Exit code
        $failOn = strtoupper($this->option('fail-on') ?? 'HIGH');
        if ($failOn === 'NEVER') {
            return self::SUCCESS;
        }
        $threshold = $rank[$failOn] ?? 0;
        foreach ($this->findings as $f) {
            if ($f['count'] > 0 && ($rank[$f['severity']] ?? 99) <= $threshold) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function add(string $module, string $severity, string $title, $count, $sample = null): void
    {
        $arr = function ($x) {
            if (is_array($x)) {
                return $x;
            }
            if (is_object($x) && method_exists($x, 'all')) {
                return $x->all();
            }

            return (array) $x;
        };
        $this->findings[] = [
            'module' => $module,
            'severity' => $severity,
            'title' => $title,
            'count' => (int) $count,
            'sample' => $count > 0 && $sample ? array_map(fn ($s) => is_object($s) ? get_object_vars($s) : (array) $s, $arr($sample)) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPTA
    // ─────────────────────────────────────────────────────────────────────────
    private function collectComptaFindings(): void
    {
        $x = DB::table('journal_entries')->where('status', '!=', 'brouillon')->whereNull('deleted_at')
            ->whereRaw('total_debit != total_credit')->get(['id', 'number', 'total_debit', 'total_credit']);
        $this->add('Compta', 'HIGH', 'Écritures validées : D ≠ C', $x->count(), $x->take(3));

        $x = DB::select("
            SELECT e.id, e.number, e.total_debit, e.total_credit, SUM(l.debit) sd, SUM(l.credit) sc
            FROM journal_entries e JOIN journal_entry_lines l ON l.journal_entry_id = e.id
            WHERE e.status != 'brouillon' AND e.deleted_at IS NULL
            GROUP BY e.id, e.number, e.total_debit, e.total_credit
            HAVING ABS(e.total_debit - sd) > 0 OR ABS(e.total_credit - sc) > 0 LIMIT 5");
        $this->add('Compta', 'HIGH', 'Écritures : totaux header ≠ Σ lignes', count($x), array_slice($x, 0, 3));

        $x = DB::table('journal_entry_lines')->where('debit', '>', 0)->where('credit', '>', 0)
            ->get(['id', 'journal_entry_id', 'debit', 'credit']);
        $this->add('Compta', 'HIGH', 'Lignes avec débit ET crédit > 0', $x->count(), $x->take(3));

        $x = DB::table('journal_entries as e')->join('fiscal_years as f', 'f.id', '=', 'e.fiscal_year_id')
            ->where('e.status', '!=', 'brouillon')->whereIn('f.status', ['cloture', 'archive'])
            ->whereNull('e.deleted_at')
            ->select('e.id', 'e.number', 'f.label as fy', 'f.status as fy_status')->get();
        $this->add('Compta', 'MED', 'Écritures validées sur exercice clos/archivé', $x->count(), $x->take(3));

        $x = DB::select("
            SELECT a.id, a.code, a.name, a.debit_balance, a.credit_balance,
                   COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit ELSE 0 END),0) sd,
                   COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.credit ELSE 0 END),0) sc
            FROM accounts a
            LEFT JOIN journal_entry_lines l ON l.account_id = a.id
            LEFT JOIN journal_entries e ON e.id = l.journal_entry_id
                AND e.status != 'brouillon' AND e.deleted_at IS NULL
            WHERE a.is_detail = 1
            GROUP BY a.id, a.code, a.name, a.debit_balance, a.credit_balance
            HAVING ABS(a.debit_balance - sd) > 0 OR ABS(a.credit_balance - sc) > 0 LIMIT 5");
        $this->add('Compta', 'HIGH', 'Comptes : (debit/credit)_balance ≠ Σ lignes', count($x), array_slice($x, 0, 3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VENTES
    // ─────────────────────────────────────────────────────────────────────────
    private function collectVentesFindings(): void
    {
        $x = DB::table('quotes')->where('status', 'converti')->whereNull('converted_to_order_id')
            ->whereNull('deleted_at')->get(['id', 'number']);
        $this->add('Ventes', 'HIGH', 'Devis converti sans commande liée', $x->count(), $x->take(3));

        $x = DB::table('invoices')->where('status', 'payee')->whereNull('deleted_at')
            ->whereRaw('COALESCE(paid_amount,0) + COALESCE(withholding_amount,0) < total_ttc - 1')
            ->get(['id', 'number', 'total_ttc', 'paid_amount', 'withholding_amount']);
        $this->add('Ventes', 'HIGH', 'Facture payée mais (paid + withholding) < total_ttc', $x->count(), $x->take(3));

        $x = DB::select("
            SELECT id, number, total_ttc, COALESCE(paid_amount,0) pa, COALESCE(withholding_amount,0) wa,
                   COALESCE(remaining_amount,0) ra,
                   (total_ttc - COALESCE(withholding_amount,0) - COALESCE(paid_amount,0)) expected
            FROM invoices
            WHERE deleted_at IS NULL AND status != 'brouillon'
              AND ABS(COALESCE(remaining_amount,0) - (total_ttc - COALESCE(withholding_amount,0) - COALESCE(paid_amount,0))) > 1
            LIMIT 5");
        $this->add('Ventes', 'HIGH', 'remaining_amount ≠ (ttc − withholding − paid)', count($x), array_slice($x, 0, 3));

        $x = DB::table('invoices')->where('status', 'partiellement_payee')->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereRaw('COALESCE(paid_amount,0) = 0')
                    ->orWhereRaw('COALESCE(paid_amount,0) + COALESCE(withholding_amount,0) >= total_ttc');
            })
            ->get(['id', 'number', 'total_ttc', 'paid_amount']);
        $this->add('Ventes', 'HIGH', 'Facture partiellement_payée incohérente', $x->count(), $x->take(3));

        $x = DB::table('invoices')->where('status', 'emise')->whereNull('deleted_at')
            ->whereRaw('COALESCE(paid_amount,0) + COALESCE(withholding_amount,0) >= total_ttc AND total_ttc > 0')
            ->get(['id', 'number', 'total_ttc', 'paid_amount']);
        $this->add('Ventes', 'HIGH', 'Facture émise mais en réalité payée', $x->count(), $x->take(3));

        $x = DB::table('invoices')->where('status', 'brouillon')->where('paid_amount', '>', 0)
            ->whereNull('deleted_at')->get(['id', 'number', 'paid_amount']);
        $this->add('Ventes', 'HIGH', 'Brouillon avec paid_amount > 0', $x->count(), $x->take(3));

        $x = DB::select('
            SELECT o.id, o.number, o.status FROM orders o
            LEFT JOIN order_items i ON i.order_id = o.id
            WHERE o.deleted_at IS NULL GROUP BY o.id, o.number, o.status HAVING COUNT(i.id) = 0');
        $this->add('Ventes', 'MED', 'Commandes sans lignes', count($x), array_slice($x, 0, 3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACHATS
    // ─────────────────────────────────────────────────────────────────────────
    private function collectAchatsFindings(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        $x = DB::table('supplier_invoices')->where('status', 'payee')->whereNull('deleted_at')
            ->whereRaw('COALESCE(paid_amount,0) < total_ttc - 1')->get(['id', 'number', 'total_ttc', 'paid_amount']);
        $this->add('Achats', 'HIGH', 'FF payée mais paid_amount < total_ttc', $x->count(), $x->take(3));

        $x = DB::table('supplier_invoices')->where('status', 'brouillon')->where('paid_amount', '>', 0)
            ->whereNull('deleted_at')->get(['id', 'number', 'paid_amount']);
        $this->add('Achats', 'HIGH', 'FF brouillon avec paiements', $x->count(), $x->take(3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STOCK
    // ─────────────────────────────────────────────────────────────────────────
    private function collectStockFindings(): void
    {
        if (! Schema::hasTable('product_stocks') || ! Schema::hasTable('stock_movements')) {
            return;
        }

        $x = DB::select("
            SELECT ps.product_id, p.reference, p.name, ps.warehouse_id, ps.quantity qty,
                   COALESCE(SUM(CASE
                     WHEN m.type IN ('entree','retour_client') THEN COALESCE(m.quantity_in_stock_uom, m.quantity)
                     WHEN m.type IN ('sortie','retour_fournisseur') THEN -COALESCE(m.quantity_in_stock_uom, m.quantity)
                     WHEN m.type = 'ajustement' THEN COALESCE(m.quantity_in_stock_uom, m.quantity)
                     WHEN m.type = 'transfert' AND m.from_warehouse_id = ps.warehouse_id THEN -COALESCE(m.quantity_in_stock_uom, m.quantity)
                     WHEN m.type = 'transfert' AND m.to_warehouse_id = ps.warehouse_id THEN COALESCE(m.quantity_in_stock_uom, m.quantity)
                     ELSE 0 END),0) calc
            FROM product_stocks ps
            JOIN products p ON p.id = ps.product_id
            LEFT JOIN stock_movements m ON m.product_id = ps.product_id
                AND (m.warehouse_id = ps.warehouse_id OR m.from_warehouse_id = ps.warehouse_id OR m.to_warehouse_id = ps.warehouse_id)
            GROUP BY ps.product_id, p.reference, p.name, ps.warehouse_id, ps.quantity
            HAVING ABS(ps.quantity - calc) > 0.001 LIMIT 5");
        $this->add('Stock', 'HIGH', 'product_stocks.quantity ≠ Σ mouvements', count($x), array_slice($x, 0, 3));

        $x = DB::table('product_stocks as ps')->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.quantity', '<', 0)->where('p.is_active', 1)
            ->get(['p.reference', 'p.name', 'ps.warehouse_id', 'ps.quantity']);
        $this->add('Stock', 'MED', 'Stocks négatifs sur produits actifs', $x->count(), $x->take(3));

        $x = DB::table('product_stocks')->whereColumn('reserved_quantity', '>', 'quantity')
            ->get(['product_id', 'warehouse_id', 'quantity', 'reserved_quantity']);
        $this->add('Stock', 'MED', 'Réservé > stock physique', $x->count(), $x->take(3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRÉSORERIE
    // ─────────────────────────────────────────────────────────────────────────
    private function collectTresorerieFindings(): void
    {
        if (! Schema::hasTable('cash_accounts') || ! Schema::hasTable('cash_transactions')) {
            return;
        }

        $x = DB::select("
            SELECT c.id, c.name, c.opening_balance, c.current_balance,
                   COALESCE(SUM(CASE WHEN t.type IN ('credit','encaissement','depot') THEN t.amount
                                     WHEN t.type IN ('debit','decaissement','retrait') THEN -t.amount
                                     ELSE 0 END),0) net,
                   (c.opening_balance + COALESCE(SUM(CASE WHEN t.type IN ('credit','encaissement','depot') THEN t.amount
                                     WHEN t.type IN ('debit','decaissement','retrait') THEN -t.amount
                                     ELSE 0 END),0)) expected
            FROM cash_accounts c LEFT JOIN cash_transactions t ON t.cash_account_id = c.id
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.opening_balance, c.current_balance
            HAVING ABS(c.current_balance - expected) > 0.01 LIMIT 5");
        $this->add('Trésorerie', 'HIGH', 'current_balance ≠ opening + Σ mouvements', count($x), array_slice($x, 0, 5));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLIENTS (credit-note aware)
    // ─────────────────────────────────────────────────────────────────────────
    private function collectClientsFindings(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'balance')) {
            return;
        }

        $x = DB::select("
            SELECT id, name, balance, expected
            FROM (
                SELECT c.id, c.name, c.balance,
                       (
                         COALESCE((SELECT SUM(remaining_amount) FROM invoices
                                   WHERE client_id = c.id AND deleted_at IS NULL
                                     AND status IN ('emise','envoyee','partiellement_payee','en_retard')), 0)
                         - COALESCE((SELECT SUM(remaining_credit) FROM credit_notes
                                     WHERE client_id = c.id AND deleted_at IS NULL AND status = 'valide'), 0)
                       ) AS expected
                FROM clients c
            ) audited_clients
            WHERE ABS(balance - CASE WHEN expected > 0 THEN expected ELSE 0 END) > 1
            LIMIT 5");
        $this->add('Clients', 'MED', 'balance ≠ Σ(factures impayées) − Σ(avoirs disponibles)', count($x), array_slice($x, 0, 3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NUMÉROTATION
    // ─────────────────────────────────────────────────────────────────────────
    private function collectNumberingFindings(): void
    {
        $tables = ['quotes', 'orders', 'invoices', 'delivery_notes', 'credit_notes',
            'purchase_orders', 'supplier_invoices', 'journal_entries'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'number')) {
                continue;
            }
            $q = DB::table($tbl)->select('number', DB::raw('COUNT(*) as n'))
                ->groupBy('number')->havingRaw('n > 1')->limit(3);
            if (Schema::hasColumn($tbl, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $dup = $q->get();
            if ($dup->count() > 0) {
                $this->add('Numéro', 'HIGH', "Doublons dans $tbl", $dup->count(), $dup->take(3));
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RENDERING
    // ─────────────────────────────────────────────────────────────────────────
    private function renderTable(): void
    {
        $issues = array_filter($this->findings, fn ($f) => $f['count'] > 0);

        $this->newLine();
        $this->line('  <fg=cyan>══ AUDIT MÉTIER ══</>');
        if (empty($issues)) {
            $this->line('  <fg=green;options=bold>✓ Aucune anomalie détectée — '.count($this->findings).' contrôles passés.</>');
            $this->newLine();

            return;
        }

        $this->line(sprintf('  <fg=yellow>%d / %d contrôles ont remonté des anomalies</>',
            count($issues), count($this->findings)));
        $this->newLine();

        foreach ($this->findings as $f) {
            if ($f['count'] === 0) {
                continue;
            }

            $color = match ($f['severity']) {
                'HIGH' => 'red',
                'MED' => 'yellow',
                default => 'gray',
            };
            $this->line(sprintf(
                '  <fg=%s>[%s]</> <fg=cyan>%s</> %s : <fg=%s;options=bold>%d</>',
                $color, $f['severity'], str_pad($f['module'], 12), $f['title'], $color, $f['count']
            ));
            if ($f['sample']) {
                foreach ($f['sample'] as $s) {
                    $this->line('       <fg=gray>→ '.json_encode($s, JSON_UNESCAPED_UNICODE).'</>');
                }
            }
        }
        $this->newLine();
    }
}
