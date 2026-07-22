<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [MISE EN PRODUCTION] Purge des données transactionnelles de test en
 * PRÉSERVANT tout le paramétrage (sociétés, utilisateurs, rôles, plan
 * comptable, catégories, familles, articles, unités, TVA, dépôts,
 * employés, paramètres de vente/paie, séquences remises à zéro).
 *
 * Dry-run par défaut : n'écrit RIEN sans --execute + confirmation tapée.
 * DELETE ligne à ligne (jamais TRUNCATE), enfants avant parents.
 */
class PreProductionClean extends Command
{
    protected $signature = 'erp:pre-production-clean
                            {--execute : Exécuter réellement la purge (sinon dry-run)}
                            {--tiers : Purger aussi les clients et fournisseurs de test}
                            {--drop-backups : Supprimer les tables *_backup_* }';

    protected $description = 'Purge les données transactionnelles de test avant mise en production (paramétrage préservé, dry-run par défaut)';

    /** Tables transactionnelles, ordonnées enfants -> parents (FK-safe). */
    private const TRANSACTIONAL = [
        // Trésorerie / ventes
        'client_payment_allocations', 'client_payments',
        'invoice_items', 'invoices',
        'delivery_note_items', 'delivery_notes',
        'order_items', 'orders',
        'quote_items', 'quotes',
        'commercial_validations',
        // Achats
        'supplier_payment_allocations', 'supplier_payments',
        'supplier_invoice_items', 'supplier_invoices',
        'reception_items', 'receptions',
        'purchase_order_items', 'purchase_orders',
        'purchase_request_items', 'purchase_requests',
        'supplier_return_items', 'supplier_returns',
        // Production
        'production_consumptions', 'production_outputs',
        'production_quality_controls', 'production_order_operations',
        'production_orders',
        // Stock
        'stock_reservations', 'inventory_items', 'inventory_sessions',
        'stock_transfer_items', 'stock_transfers',
        'stock_movements', 'stock_lots', 'coils',
        'product_stocks',
        // Comptabilité (écritures transactionnelles ; le PLAN de comptes est préservé)
        'analytic_lines', 'journal_entry_lines', 'journal_entries',
        // Paie (runs ; employés et paramètres préservés)
        'payroll_run_lines', 'payroll_runs',
        // Journaux techniques
        'audit_logs', 'sync_logs', 'document_sequence_audits',
        'notifications',
    ];

    /** Tables de tiers, purgées seulement avec --tiers. */
    private const TIERS = [
        'client_contacts', 'client_addresses', 'clients',
        'supplier_contacts', 'supplier_addresses', 'suppliers',
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $tables  = self::TRANSACTIONAL;
        if ($this->option('tiers')) {
            $tables = array_merge($tables, self::TIERS);
        }

        $this->info($execute ? 'Mode EXÉCUTION' : 'Mode DRY-RUN (aucune modification)');
        $this->newLine();

        $plan = [];
        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            $n = DB::table($t)->count();
            if ($n > 0) {
                $plan[$t] = $n;
            }
        }

        $backups = collect(Schema::getTables())->pluck('name')->unique()
            ->filter(fn ($t) => str_contains($t, 'backup'))->values();

        $this->table(['Table', 'Lignes à purger'], collect($plan)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Total : ' . array_sum($plan) . ' lignes sur ' . count($plan) . ' tables.');
        if ($backups->isNotEmpty()) {
            $this->line('Tables backup ' . ($this->option('drop-backups') ? 'À SUPPRIMER' : 'conservées (--drop-backups pour les supprimer)') . ' : ' . $backups->implode(', '));
        }
        $this->line('Préservés : sociétés, utilisateurs, rôles/permissions, plan comptable, catégories, familles, articles, unités, TVA, dépôts, employés, paramètres' . ($this->option('tiers') ? '' : ', clients, fournisseurs'));

        if (! $execute) {
            $this->newLine();
            $this->warn('Dry-run terminé. Relancer avec --execute pour purger.');

            return self::SUCCESS;
        }

        if ($this->ask('Taper exactement NETTOYER PRODUCTION pour confirmer') !== 'NETTOYER PRODUCTION') {
            $this->error('Confirmation invalide — abandon.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($plan) {
            Schema::disableForeignKeyConstraints();
            try {
                foreach (array_keys($plan) as $t) {
                    $n = DB::table($t)->delete();
                    $this->line("  $t : $n supprimée(s)");
                }

                // Remise à zéro des compteurs de numérotation
                if (Schema::hasTable('document_sequences')) {
                    DB::table('document_sequences')->update(['last_number' => 0]);
                    $this->line('  document_sequences : compteurs remis à 0');
                }

                // Soldes des comptes de trésorerie remis à zéro (les écritures sont purgées)
                if (Schema::hasTable('cash_accounts')) {
                    DB::table('cash_accounts')->update(['current_balance' => 0]);
                    $this->line('  cash_accounts : soldes remis à 0');
                }
                if (Schema::hasColumn('accounts', 'debit_balance')) {
                    DB::table('accounts')->update(['debit_balance' => 0, 'credit_balance' => 0]);
                    $this->line('  accounts : soldes remis à 0');
                }
                if (Schema::hasColumn('clients', 'current_balance')) {
                    DB::table('clients')->update(['current_balance' => 0]);
                    $this->line('  clients : soldes remis à 0');
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });

        if ($this->option('drop-backups')) {
            foreach ($backups as $b) {
                Schema::dropIfExists($b);
                $this->line("  table backup supprimée : $b");
            }
        }

        $this->newLine();
        $this->info('Nettoyage pré-production terminé. Paramétrage intact.');

        return self::SUCCESS;
    }
}
