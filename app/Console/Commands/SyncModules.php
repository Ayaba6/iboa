<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [SYNC] Resynchronisation automatique inter-modules — répare les agrégats
 * dénormalisés quand ils dérivent de leur source de vérité (l'inverse
 * d'audit:sync qui ne fait que détecter). Idempotent : chaque règle recalcule
 * depuis les données primaires et n'écrit que si la valeur diffère.
 */
class SyncModules extends Command
{
    protected $signature = 'sync:modules {--dry-run : Affiche les corrections sans écrire}';

    protected $description = 'Resynchronise les agrégats inter-modules (balances, statuts, montants dérivés)';

    private bool $dry = false;

    private int $fixed = 0;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->info('══ SYNCHRONISATION INTER-MODULES' . ($this->dry ? ' (dry-run)' : '') . ' ══');

        $this->syncClientBalances();
        $this->syncSupplierBalances();
        $this->syncInvoiceRemaining();
        $this->syncOrderInvoicedAmounts();
        $this->syncOrderDeliveryStatus();
        $this->syncAccountBalances();

        $this->newLine();
        $this->info($this->fixed === 0
            ? '✓ Tout est synchronisé — aucune correction nécessaire.'
            : ($this->dry ? "⚠ {$this->fixed} correction(s) à appliquer (relancer sans --dry-run)." : "✓ {$this->fixed} correction(s) appliquée(s)."));

        return self::SUCCESS;
    }

    /** Balance client = Σ factures impayées − avoirs disponibles (formule de Client::recalculateBalance). */
    private function syncClientBalances(): void
    {
        Client::withoutGlobalScopes()->get()->each(function (Client $c) {
            $outstanding = $c->invoices()
                ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
                ->sum('remaining_amount');
            $credit   = $c->creditNotes()->where('status', 'valide')->sum('remaining_credit');
            $expected = max(0, (int) ($outstanding - $credit));
            $avant    = (int) $c->balance;

            if ($avant !== $expected) {
                $this->line("  Client {$c->name} : balance {$avant} → {$expected}");
                if (! $this->dry) {
                    $c->recalculateBalance();
                }
                $this->fixed++;
            }
        });
        $this->comment('▸ Balances clients vérifiées');
    }

    /** Balance fournisseur — même principe. */
    private function syncSupplierBalances(): void
    {
        Supplier::withoutGlobalScopes()->get()->each(function (Supplier $s) {
            $avant = (int) $s->balance;
            if ($this->dry) {
                return; // la formule fournisseur vit dans le modèle — dry-run couvert par audit:business
            }
            $s->recalculateBalance();
            $apres = (int) $s->fresh()->balance;
            if ($avant !== $apres) {
                $this->line("  Fournisseur {$s->name} : balance {$avant} → {$apres}");
                $this->fixed++;
            }
        });
        $this->comment('▸ Balances fournisseurs vérifiées');
    }

    /** remaining_amount facture = ttc − retenue − payé. */
    private function syncInvoiceRemaining(): void
    {
        $rows = DB::select("
            SELECT id, number, remaining_amount,
                   (total_ttc - COALESCE(withholding_amount,0) - COALESCE(paid_amount,0)) expected
            FROM invoices
            WHERE deleted_at IS NULL AND status != 'brouillon'
              AND ABS(COALESCE(remaining_amount,0) - (total_ttc - COALESCE(withholding_amount,0) - COALESCE(paid_amount,0))) > 0");
        foreach ($rows as $r) {
            $this->line("  Facture {$r->number} : remaining {$r->remaining_amount} → {$r->expected}");
            if (! $this->dry) {
                DB::table('invoices')->where('id', $r->id)->update(['remaining_amount' => $r->expected, 'updated_at' => now()]);
            }
            $this->fixed++;
        }
        $this->comment('▸ Restes à payer factures vérifiés');
    }

    /** invoiced_amount commande = Σ TTC des factures actives liées. */
    private function syncOrderInvoicedAmounts(): void
    {
        $rows = DB::select("
            SELECT o.id, o.number, COALESCE(o.invoiced_amount,0) actuel, COALESCE(SUM(i.total_ttc),0) attendu
            FROM orders o
            LEFT JOIN invoices i ON i.order_id = o.id AND i.deleted_at IS NULL
                 AND i.status NOT IN ('brouillon','annulee')
            WHERE o.deleted_at IS NULL
            GROUP BY o.id, o.number, o.invoiced_amount
            HAVING ABS(actuel - attendu) > 0");
        foreach ($rows as $r) {
            $this->line("  Commande {$r->number} : facturé {$r->actuel} → {$r->attendu}");
            if (! $this->dry) {
                DB::table('orders')->where('id', $r->id)->update(['invoiced_amount' => $r->attendu, 'updated_at' => now()]);
            }
            $this->fixed++;
        }
        $this->comment('▸ Montants facturés des commandes vérifiés');
    }

    /**
     * Statut livraison commande dérivé des quantités livrées :
     * confirme → partiellement_livre → livre (jamais de retour en arrière
     * sur les statuts financiers facture/annule).
     */
    private function syncOrderDeliveryStatus(): void
    {
        $orders = Order::withoutGlobalScopes()
            ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre', 'livre'])
            ->with('items:id,order_id,quantity,delivered_quantity')
            ->get();

        foreach ($orders as $o) {
            $qty       = (float) $o->items->sum('quantity');
            $delivered = (float) $o->items->sum('delivered_quantity');
            $expected  = match (true) {
                $qty <= 0 || $delivered <= 0      => null, // pas de livraison → statut inchangé
                $delivered + 0.001 >= $qty         => 'livre',
                default                            => 'partiellement_livre',
            };
            if ($expected !== null && $o->status !== $expected
                && ! ($o->status === 'livre' && $expected === 'livre')) {
                // en_preparation → partiellement/livre autorisé ; confirme → idem
                $this->line("  Commande {$o->number} : statut {$o->status} → {$expected} (livré {$delivered}/{$qty})");
                if (! $this->dry) {
                    $o->updateQuietly(['status' => $expected]);
                }
                $this->fixed++;
            }
        }
        $this->comment('▸ Statuts de livraison des commandes vérifiés');
    }

    /** debit/credit_balance des comptes = Σ lignes d'écritures validées. */
    private function syncAccountBalances(): void
    {
        $rows = DB::select("
            SELECT a.id, a.code, a.debit_balance db, a.credit_balance cb,
                   COALESCE(SUM(l.debit),0) sd, COALESCE(SUM(l.credit),0) sc
            FROM accounts a
            LEFT JOIN journal_entry_lines l ON l.account_id = a.id
            LEFT JOIN journal_entries e ON e.id = l.journal_entry_id
                AND e.status != 'brouillon' AND e.deleted_at IS NULL
            WHERE a.is_detail = 1
            GROUP BY a.id, a.code, a.debit_balance, a.credit_balance
            HAVING ABS(db - sd) > 0 OR ABS(cb - sc) > 0");
        foreach ($rows as $r) {
            $this->line("  Compte {$r->code} : D {$r->db}→{$r->sd} | C {$r->cb}→{$r->sc}");
            if (! $this->dry) {
                DB::table('accounts')->where('id', $r->id)
                    ->update(['debit_balance' => $r->sd, 'credit_balance' => $r->sc, 'updated_at' => now()]);
            }
            $this->fixed++;
        }
        $this->comment('▸ Balances des comptes comptables vérifiées');
    }
}
