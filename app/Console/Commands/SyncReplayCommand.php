<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Console\Command;

// [Sync ERP] Relance les synchronisations échouées (handlers idempotents).
class SyncReplayCommand extends Command
{
    protected $signature = 'sync:replay {--failed : Relancer toutes les synchronisations en échec} {--id= : Relancer un sync_log précis}';
    protected $description = 'Relance les synchronisations inter-modules échouées';

    public function handle(SyncOrchestrator $orchestrator): int
    {
        $query = SyncLog::query()->whereNotNull('handler_class');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        } elseif ($this->option('failed')) {
            $query->failed();
        } else {
            $this->error('Précisez --failed ou --id=N.');
            return self::INVALID;
        }

        $logs = $query->orderBy('id')->get();
        if ($logs->isEmpty()) {
            $this->info('Aucune synchronisation à relancer.');
            return self::SUCCESS;
        }

        $ok = 0;
        $ko = 0;
        foreach ($logs as $log) {
            if (!$log->isRetryable() && !$this->option('id')) {
                continue;
            }
            $result = $orchestrator->retry($log);
            if ($result->status === SyncLog::STATUS_SUCCESS) {
                $ok++;
                $this->line("  ✓ #{$log->id} {$log->event_name} → {$log->target_module}");
            } else {
                $ko++;
                $this->error("  ✗ #{$log->id} {$log->event_name} : {$result->message}");
            }
        }

        $this->info("Relance terminée : {$ok} succès, {$ko} échec(s).");
        return $ko === 0 ? self::SUCCESS : self::FAILURE;
    }
}
