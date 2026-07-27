<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

/**
 * [Sécurité] Acteur d'exécution EXPLICITE.
 *
 * Règle absolue : « absence d'utilisateur ≠ autorisation automatique ».
 * Un job, un import ou une commande Artisan ne contourne pas les permissions
 * parce qu'aucun humain n'est connecté : il doit déclarer un ACTEUR SYSTÈME
 * autorisé, avec son identifiant, ses permissions, son origine et un motif.
 *
 * Trois situations distinctes :
 *   - utilisateur humain authentifié        → permissions du user ;
 *   - acteur système déclaré et autorisé    → permissions explicitement listées ;
 *   - aucun acteur                          → exécution REFUSÉE.
 *
 * Usage (job / commande) :
 *   ExecutionContext::asSystem('import-bobines', ['coils.split.execute'],
 *       origin: 'artisan:coils:import', reason: 'Import quotidien', fn () => ...);
 */
class ExecutionContext
{
    private static ?array $systemActor = null;

    /**
     * Exécute un traitement sous un acteur SYSTÈME explicitement autorisé.
     *
     * @param  array<int,string>  $permissions
     */
    public static function asSystem(string $id, array $permissions, string $origin, string $reason, \Closure $callback): mixed
    {
        $previous = self::$systemActor;
        self::$systemActor = [
            'id'          => $id,
            'type'        => 'system',
            'permissions' => $permissions,
            'origin'      => $origin,
            'reason'      => $reason,
            'started_at'  => now()->toIso8601String(),
        ];

        try {
            return $callback();
        } finally {
            self::$systemActor = $previous;
        }
    }

    /** Acteur système courant (null si aucun). */
    public static function systemActor(): ?array
    {
        return self::$systemActor;
    }

    /** Purge (tests). */
    public static function clear(): void
    {
        self::$systemActor = null;
    }

    /**
     * Description de l'acteur courant, pour journalisation.
     */
    public static function describe(): string
    {
        if ($user = Auth::user()) {
            return 'user#' . $user->id;
        }
        if (self::$systemActor) {
            return 'system:' . self::$systemActor['id'] . ' (' . self::$systemActor['origin'] . ')';
        }

        return 'aucun acteur';
    }

    /**
     * L'acteur courant détient-il la permission ?
     *
     * @throws \RuntimeException si AUCUN acteur n'est déclaré (jamais un
     *                           contournement silencieux)
     */
    public static function assertCan(string $permission, string $action): void
    {
        $user = Auth::user();
        if ($user) {
            if (! $user->can($permission)) {
                throw new \RuntimeException(sprintf(
                    'Permission « %s » requise pour %s.', $permission, $action
                ));
            }

            return;
        }

        if (self::$systemActor === null) {
            throw new \RuntimeException(sprintf(
                'Aucun acteur d\'exécution déclaré pour %s : un utilisateur authentifié ou un '
                . 'acteur système explicitement autorisé est requis (l\'absence d\'utilisateur '
                . 'n\'autorise rien).', $action
            ));
        }

        if (! in_array($permission, self::$systemActor['permissions'], true)) {
            throw new \RuntimeException(sprintf(
                'Acteur système « %s » : permission « %s » non accordée pour %s.',
                self::$systemActor['id'], $permission, $action
            ));
        }
    }
}
