<?php

namespace App\Services\Sync;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * [Sync ERP] Exécution idempotente et journalisée d'une synchronisation.
 *
 * Usage — envelopper un flux EXISTANT sans dupliquer la logique métier :
 *
 *   app(SyncOrchestrator::class)->run(
 *       sourceModule: 'achats', targetModule: 'stock',
 *       eventName: 'reception.validated', action: 'create_stock_entries',
 *       source: $reception,
 *       callback: fn () => $this->stockService->createEntriesFor($reception),
 *   );
 *
 * Garanties :
 *  - Idempotence : si un log SUCCESS existe pour la clé logique
 *    (source_type + source_id + target_module + action), la callback
 *    n'est PAS ré-exécutée (statut skipped).
 *  - Journalisation : succès, échec (message + trace), skip.
 *  - Non-intrusif : par défaut l'exception métier est RELANCÉE après log
 *    (le flux appelant garde son comportement transactionnel actuel).
 *  - Retry : si $handlerClass est fourni (invokable acceptant le modèle
 *    source), l'échec est relançable depuis /admin/synchronisations ou
 *    `php artisan sync:replay --failed`.
 */
class SyncOrchestrator
{
    public function __construct(private SyncLogger $logger)
    {
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T|null null si skipped (déjà synchronisé)
     */
    public function run(
        string $sourceModule,
        string $targetModule,
        string $eventName,
        string $action,
        Model $source,
        callable $callback,
        array $payload = [],
        ?string $handlerClass = null,
        bool $rethrow = true,
        bool $idempotent = true,
    ) {
        // Idempotence — jamais deux synchronisations réussies pour la même clé.
        // $idempotent=false pour les TRANSITIONS D'ÉTAT répétables (ex : machine
        // maintenance→active→maintenance) : journalisées mais jamais skippées.
        if ($idempotent && $this->alreadySucceeded($source, $targetModule, $action)) {
            $log = $this->logger->start($sourceModule, $targetModule, $eventName, $action, $source, $payload, $handlerClass);
            $this->logger->skipped($log, 'Déjà synchronisé — clé logique existante.');
            return null;
        }

        $log = $this->logger->start($sourceModule, $targetModule, $eventName, $action, $source, $payload, $handlerClass);

        try {
            $result = $callback();
            $this->logger->success($log);
            return $result;
        } catch (Throwable $e) {
            $this->logger->failed($log, $e);
            if ($rethrow) {
                throw $e;
            }
            return null;
        }
    }

    public function alreadySucceeded(Model $source, string $targetModule, string $action): bool
    {
        return SyncLog::forLogicalKey($source->getMorphClass(), (int) $source->getKey(), $targetModule, $action)
            ->where('status', SyncLog::STATUS_SUCCESS)
            ->exists();
    }

    /**
     * Rejoue un échec : instancie le handler (invokable) avec le document
     * source rechargé. Le handler DOIT être idempotent (vérifier l'existant
     * avant de créer).
     */
    public function retry(SyncLog $log): SyncLog
    {
        if (!$log->isRetryable()) {
            throw new \RuntimeException('Cette synchronisation ne peut pas être relancée (statut ou handler manquant).');
        }

        // [FIX morph] source_type peut être un alias morphMap (ex: 'delivery_note')
        // et non un FQCN : on le résout via la map d'Eloquent avant find().
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($log->source_type)
            ?? $log->source_type;
        if (!class_exists($class)) {
            $log->update([
                'status'  => SyncLog::STATUS_FAILED,
                'message' => "Type source « {$log->source_type} » non résoluble en classe modèle.",
            ]);
            return $log;
        }

        /** @var Model|null $source */
        $source = $class::find($log->source_id);
        if (!$source) {
            $log->update(['status' => SyncLog::STATUS_FAILED, 'message' => 'Document source introuvable (supprimé ?).']);
            return $log;
        }

        $log->update(['status' => SyncLog::STATUS_RETRYING, 'attempts' => $log->attempts + 1]);

        try {
            $handler = app($log->handler_class);
            $handler($source, $log->payload ?? []);
            $log->update(['status' => SyncLog::STATUS_SUCCESS, 'message' => 'Relance réussie.', 'processed_at' => now()]);
        } catch (Throwable $e) {
            $log->update([
                'status'       => SyncLog::STATUS_FAILED,
                'message'      => mb_substr($e->getMessage(), 0, 500),
                'error_trace'  => mb_substr($e->getTraceAsString(), 0, 8000),
                'processed_at' => now(),
            ]);
        }

        return $log->refresh();
    }
}
