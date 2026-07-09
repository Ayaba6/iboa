<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;

// [Sync ERP] État de santé du journal de synchronisation.
class SyncCheckCommand extends Command
{
    protected $signature = 'sync:check';
    protected $description = 'Vérifie l\'état du journal des synchronisations inter-modules';

    public function handle(): int
    {
        $stats = SyncLog::selectRaw('status, COUNT(*) as nb')->groupBy('status')->pluck('nb', 'status');

        $this->table(['Statut', 'Nombre'], collect([
            ['success',  $stats['success'] ?? 0],
            ['failed',   $stats['failed'] ?? 0],
            ['pending',  $stats['pending'] ?? 0],
            ['skipped',  $stats['skipped'] ?? 0],
            ['retrying', $stats['retrying'] ?? 0],
        ]));

        // pending anciens = flux interrompus (crash pendant la transaction)
        $stale = SyncLog::where('status', SyncLog::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(30))->count();
        if ($stale > 0) {
            $this->warn("⚠ {$stale} synchronisation(s) « pending » depuis plus de 30 min — flux probablement interrompus.");
        }

        $failed = (int) ($stats['failed'] ?? 0);
        if ($failed > 0) {
            $this->warn("⚠ {$failed} échec(s) — relancez avec : php artisan sync:replay --failed");
            return self::FAILURE;
        }

        $this->info('✓ Aucune synchronisation en échec.');
        return self::SUCCESS;
    }
}
