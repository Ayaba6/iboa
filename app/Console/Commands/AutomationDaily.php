<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Models\SupplierInvoice;
use App\Models\Invoice;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [AUTOMATION] Transitions de statut quotidiennes + alertes.
 *
 * Tâches accomplies :
 *  1. Devis envoyés dont expires_at < aujourd'hui → status = expire
 *  2. Factures clients en retard → status = en_retard (déjà fait via mark-overdue, mais on consolide)
 *  3. Factures fournisseur en retard → idem (consolidation)
 *  4. PO en attente d'approbation depuis > 7 jours → log d'alerte (pour notification UI/mail futur)
 *  5. Synthèse écrite dans audit_logs (action='daily_automation')
 *
 * Idempotent : peut tourner plusieurs fois sans effet secondaire.
 *
 * Usage :
 *   php artisan automation:daily          # exécution réelle
 *   php artisan automation:daily --dry    # ne change rien, affiche seulement
 */
class AutomationDaily extends Command
{
    protected $signature = 'automation:daily {--dry : Mode simulation sans modification DB}';
    protected $description = 'Transitions de statut automatiques (devis expirés, factures en retard, etc.) + alertes.';

    public function handle(AuditService $audit): int
    {
        $dry = (bool) $this->option('dry');
        $today = now()->toDateString();

        $this->info('═══ AUTOMATION QUOTIDIENNE ' . now()->format('d/m/Y H:i') . ' ═══');
        if ($dry) $this->warn('  (mode simulation — aucune modification appliquée)');

        $stats = [];

        // 1. Devis expirés — offres émises non converties : envoyées ou validées
        // en interne. Le brouillon n'est pas une offre ; un devis accepté avant
        // expiration reste accepté.
        $expiredQuotesQuery = Quote::whereIn('status', ['envoye', 'valide'])
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today);

        $count = $expiredQuotesQuery->count();
        $stats['quotes_expired'] = $count;
        $this->line(sprintf('  Devis à expirer : %d', $count));
        if ($count > 0 && !$dry) {
            $expiredQuotesQuery->update(['status' => 'expire']);
        }

        // 2. Factures clients en retard
        if (\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'due_at')) {
            $overdueInvQuery = Invoice::whereIn('status', ['emise', 'partiellement_payee'])
                ->whereDate('due_at', '<', $today)
                ->where('remaining_amount', '>', 0);
            $count = $overdueInvQuery->count();
            $stats['invoices_overdue'] = $count;
            $this->line(sprintf('  Factures clients en retard : %d', $count));
            if ($count > 0 && !$dry) {
                $overdueInvQuery->update(['status' => 'en_retard']);
            }
        }

        // 3. Factures fournisseur en retard
        $overdueSupQuery = SupplierInvoice::whereIn('status', ['validee', 'partiellement_payee'])
            ->whereDate('due_at', '<', $today)
            ->where('remaining_amount', '>', 0);
        $count = $overdueSupQuery->count();
        $stats['supplier_invoices_overdue'] = $count;
        $this->line(sprintf('  Factures fournisseur en retard : %d', $count));
        if ($count > 0 && !$dry) {
            $overdueSupQuery->update(['status' => 'en_retard']);
        }

        // 4. Échéances cadenciers à marquer "partiel"/"paye" si déjà payées (cohérence)
        if (\Illuminate\Support\Facades\Schema::hasTable('payment_schedules')) {
            $schedFixed = DB::table('payment_schedules')
                ->where('status', 'en_attente')
                ->whereRaw('paid_amount > 0 AND paid_amount < amount')
                ->count();
            $schedFixedPaid = DB::table('payment_schedules')
                ->where('status', '!=', 'paye')
                ->whereRaw('paid_amount >= amount')
                ->count();
            $stats['schedules_to_partiel'] = $schedFixed;
            $stats['schedules_to_paye']    = $schedFixedPaid;
            $this->line(sprintf('  Échéances à corriger (partiel) : %d · (payé) : %d', $schedFixed, $schedFixedPaid));
            if (!$dry) {
                DB::table('payment_schedules')
                    ->where('status', 'en_attente')
                    ->whereRaw('paid_amount > 0 AND paid_amount < amount')
                    ->update(['status' => 'partiel', 'updated_at' => now()]);
                DB::table('payment_schedules')
                    ->where('status', '!=', 'paye')
                    ->whereRaw('paid_amount >= amount')
                    ->update(['status' => 'paye', 'updated_at' => now()]);
            }
        }

        // 5. PO en attente d'approbation depuis > 7j → log d'alerte
        if (\Illuminate\Support\Facades\Schema::hasColumn('purchase_orders', 'approval_status')) {
            $staleApprovals = \App\Models\PurchaseOrder::where('approval_status', 'en_attente')
                ->whereDate('submitted_for_approval_at', '<', now()->subDays(7))
                ->get(['id', 'number', 'total_ttc']);
            $stats['stale_approvals'] = $staleApprovals->count();
            $this->line(sprintf('  PO en attente d\'approbation > 7j : %d', $staleApprovals->count()));
            if ($staleApprovals->isNotEmpty() && !$dry) {
                foreach ($staleApprovals as $po) {
                    $audit->log('approval_stale_alert', $po, [], [
                        'days_pending' => 7,
                        'number'       => $po->number,
                        'amount'       => $po->total_ttc,
                    ]);
                }
            }
        }

        // 6. [CONCURRENCE] Purge des verrous d'édition expirés
        $expiredLocks = \App\Models\EditLock::expired()->count();
        $stats['edit_locks_purged'] = $expiredLocks;
        $this->line(sprintf('  Verrous d\'édition expirés à purger : %d', $expiredLocks));
        if ($expiredLocks > 0 && !$dry) {
            \App\Models\EditLock::expired()->delete();
        }

        // 7. Synthèse audit_logs
        if (!$dry) {
            $audit->log('daily_automation', null, [], $stats);
        }

        $this->newLine();
        $this->info('  Synthèse : ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
