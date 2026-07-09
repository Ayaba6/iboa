<?php

namespace App\Services\Sync;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * [Sync ERP] Journalisation centralisée des synchronisations inter-modules.
 *
 * IMPORTANT : la journalisation ne doit JAMAIS faire échouer le flux métier.
 * Toute erreur d'écriture du log est absorbée (Log::warning) — le document
 * de l'utilisateur passe toujours en priorité.
 *
 * Échecs & rollback : un log d'échec écrit DANS la transaction métier serait
 * effacé par le rollback. Les échecs détectés en transaction sont donc mis en
 * attente ($deferredFailures) puis écrits après le rollback (listener
 * TransactionRolledBack enregistré dans AppServiceProvider) — hors transaction.
 */
class SyncLogger
{
    /** @var array<int, array> échecs à écrire après rollback */
    private static array $deferredFailures = [];
    public function start(
        string $sourceModule,
        string $targetModule,
        string $eventName,
        string $action,
        Model $source,
        array $payload = [],
        ?string $handlerClass = null,
    ): ?SyncLog {
        try {
            return SyncLog::create([
                'source_module' => $sourceModule,
                'target_module' => $targetModule,
                'event_name'    => $eventName,
                'action'        => $action,
                'source_type'   => $source->getMorphClass(),
                'source_id'     => $source->getKey(),
                'status'        => SyncLog::STATUS_PENDING,
                'payload'       => $payload ?: null,
                'handler_class' => $handlerClass,
                'attempts'      => 1,
                'created_by'    => Auth::id(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[Sync] impossible de créer le sync_log', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function success(?SyncLog $log, ?string $message = null): void
    {
        $this->finish($log, SyncLog::STATUS_SUCCESS, $message);
    }

    public function skipped(?SyncLog $log, ?string $message = null): void
    {
        $this->finish($log, SyncLog::STATUS_SKIPPED, $message);
    }

    public function failed(?SyncLog $log, Throwable $e): void
    {
        if (!$log) {
            return;
        }

        $failure = [
            'status'       => SyncLog::STATUS_FAILED,
            'message'      => mb_substr($e->getMessage(), 0, 500),
            'error_trace'  => mb_substr($e->getTraceAsString(), 0, 8000),
            'processed_at' => now(),
        ];

        try {
            if (DB::transactionLevel() > 0) {
                // En transaction : l'update serait rollbacké avec l'insert du log.
                // On mémorise les attributs complets pour ré-insérer après rollback.
                self::$deferredFailures[] = array_merge(
                    $log->only([
                        'source_module', 'target_module', 'event_name', 'action',
                        'source_type', 'source_id', 'payload', 'handler_class',
                        'attempts', 'created_by',
                    ]),
                    $failure,
                );
                // Tentative directe aussi : si l'appelant catch SANS rollback,
                // cet update survivra et le différé sera dédupliqué au flush.
                $log->update($failure);
            } else {
                $log->update($failure);
            }
        } catch (Throwable $inner) {
            Log::warning('[Sync] impossible de marquer le sync_log en échec', ['error' => $inner->getMessage()]);
        }
    }

    /**
     * Écrit les échecs mis en attente pendant une transaction rollbackée.
     * Appelé par le listener TransactionRolledBack (AppServiceProvider).
     */
    public static function flushDeferredFailures(): void
    {
        $pending = self::$deferredFailures;
        self::$deferredFailures = [];

        foreach ($pending as $attrs) {
            try {
                // Déduplication : si l'update direct a survécu (pas de rollback),
                // un log FAILED existe déjà pour cette clé — ne pas doubler.
                $exists = SyncLog::forLogicalKey(
                        $attrs['source_type'], (int) $attrs['source_id'],
                        $attrs['target_module'], $attrs['action']
                    )
                    ->whereIn('status', [SyncLog::STATUS_FAILED, SyncLog::STATUS_SUCCESS])
                    ->exists();
                if (!$exists) {
                    SyncLog::create($attrs);
                }
            } catch (Throwable $e) {
                Log::warning('[Sync] flush échec différé impossible', ['error' => $e->getMessage()]);
            }
        }
    }

    private function finish(?SyncLog $log, string $status, ?string $message): void
    {
        try {
            $log?->update([
                'status'       => $status,
                'message'      => $message ? mb_substr($message, 0, 500) : null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[Sync] impossible de finaliser le sync_log', ['error' => $e->getMessage()]);
        }
    }
}
