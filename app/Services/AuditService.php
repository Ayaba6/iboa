<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        ?object $model = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        $attributes = [
            'user_id'     => Auth::id(),
            'user_name'   => Auth::user()?->name ?? 'Système',
            'action'      => $action,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->getKey(),
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
            'url'         => Request::fullUrl(),
        ];

        // [SEC-PHASE2 §9] Chaînage : chaque entrée scelle la précédente.
        // Une suppression ou modification casse la chaîne (a3:audit-security §8).
        $prevHash = AuditLog::orderByDesc('id')->value('row_hash') ?? str_repeat('0', 64);
        $attributes['prev_hash'] = $prevHash;
        $attributes['row_hash']  = self::computeRowHash($attributes, $prevHash);

        AuditLog::create($attributes);
    }

    /** Empreinte déterministe d'une entrée (indépendante de l'ordre PHP des clés). */
    public static function computeRowHash(array $attributes, string $prevHash): string
    {
        return hash('sha256', implode('|', [
            $prevHash,
            $attributes['user_id'] ?? '',
            $attributes['action'] ?? '',
            $attributes['model_type'] ?? '',
            $attributes['model_id'] ?? '',
            json_encode($attributes['old_values'] ?? null),
            json_encode($attributes['new_values'] ?? null),
            $attributes['ip_address'] ?? '',
        ]));
    }

    /**
     * Vérifie l'intégrité de la chaîne. Retourne la liste des IDs dont le
     * chaînage est rompu (hash recalculé différent ou prev_hash incohérent).
     * Les entrées antérieures au déploiement du chaînage (hash null) sont
     * ignorées.
     *
     * @return array<int>
     */
    public function verifyChain(): array
    {
        $broken = [];
        $prev = null;
        AuditLog::orderBy('id')->whereNotNull('row_hash')
            ->chunk(500, function ($logs) use (&$broken, &$prev) {
                foreach ($logs as $log) {
                    if ($prev !== null && $log->prev_hash !== $prev) {
                        $broken[] = $log->id;
                    }
                    $recomputed = self::computeRowHash([
                        'user_id'    => $log->user_id,
                        'action'     => $log->action,
                        'model_type' => $log->model_type,
                        'model_id'   => $log->model_id,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'ip_address' => $log->ip_address,
                    ], $log->prev_hash ?? '');
                    if ($recomputed !== $log->row_hash) {
                        $broken[] = $log->id;
                    }
                    $prev = $log->row_hash;
                }
            });

        return array_values(array_unique($broken));
    }
}
