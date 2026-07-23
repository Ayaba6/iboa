<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

/**
 * [SEC-PHASE2 §2] Séparation créateur/valideur configurable.
 * Voir config/security.php — désactivé par défaut, à activer en production.
 */
class MakerCheckerService
{
    /**
     * Refuse l'opération si le contrôle est actif pour cette action et que le
     * valideur courant est l'auteur de l'opération. Tentative journalisée.
     *
     * @throws \RuntimeException
     */
    public function assert(?int $makerId, string $action, string $documentLabel, ?object $model = null): void
    {
        if (! config('security.maker_checker.enabled')) {
            return;
        }
        if (! (config('security.maker_checker.actions')[$action] ?? true)) {
            return;
        }

        $user = Auth::user();
        if (! $user || $makerId === null) {
            return;
        }
        if ($user->hasRole('super_admin')) {
            return;
        }

        if ((int) $user->id === (int) $makerId) {
            app(AuditService::class)->log(
                'maker_checker.refus',
                $model,
                [],
                ['action' => $action, 'document' => $documentLabel]
            );

            throw new \RuntimeException(sprintf(
                'Séparation des tâches : vous êtes l\'auteur de %s — la validation doit être faite par un autre utilisateur habilité.',
                $documentLabel
            ));
        }
    }
}
