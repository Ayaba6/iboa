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
     * [SEC-PHASE2 §6] Le valideur ne peut JAMAIS être le bénéficiaire de
     * l'opération (conflit d'intérêt direct) — contrôle TOUJOURS actif,
     * indépendant de la configuration maker-checker, sans exemption.
     * S'applique quand le bénéficiaire est identifiable par une relation
     * métier fiable (ex. employees.user_id pour prêts/avances/bulletins).
     *
     * @throws \RuntimeException
     */
    public function assertNotBeneficiary(?int $beneficiaryUserId, string $documentLabel, ?object $model = null): void
    {
        $user = Auth::user();
        if (! $user || $beneficiaryUserId === null) {
            return;
        }

        if ((int) $user->id === (int) $beneficiaryUserId) {
            \Illuminate\Support\Facades\Log::channel('security')->warning('beneficiaire.refus', [
                'user_id'  => $user->id,
                'document' => $documentLabel,
                'model'    => $model ? get_class($model) . '#' . $model->getKey() : null,
                'ip'       => request()?->ip(),
            ]);
            app(AuditService::class)->log('beneficiaire.refus', $model, [], ['document' => $documentLabel]);

            throw new \RuntimeException(sprintf(
                'Conflit d\'intérêt : vous êtes le bénéficiaire de %s — la validation doit être faite par un autre utilisateur habilité.',
                $documentLabel
            ));
        }
    }

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
        // Exemption par action : jamais honorée en production pour les actions
        // non exemptables (fail closed).
        $exempted = ! (config('security.maker_checker.actions')[$action] ?? true);
        $nonExemptable = in_array($action, config('security.maker_checker.non_exemptable', []), true);
        if ($exempted && ! (app()->environment('production') && $nonExemptable)) {
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
            // [SEC-PHASE2 §10] Refus = catégorie « action refusée » : journal de
            // sécurité FICHIER (hors transaction DB, survit au rollback), puis
            // best-effort en base (annulé si transaction englobante rollback).
            \Illuminate\Support\Facades\Log::channel('security')->warning('maker_checker.refus', [
                'user_id'  => $user->id,
                'action'   => $action,
                'document' => $documentLabel,
                'model'    => $model ? get_class($model) . '#' . $model->getKey() : null,
                'ip'       => request()?->ip(),
            ]);
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
