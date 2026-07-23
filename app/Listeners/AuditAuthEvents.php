<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

/**
 * [SEC-PHASE2 §8] Journal d'audit des événements d'authentification :
 * connexion réussie, échec (email tenté, jamais le mot de passe),
 * déconnexion. Double écriture : audit_logs + security.log (fichier).
 */
class AuditAuthEvents
{
    public function handleLogin(Login $event): void
    {
        app(AuditService::class)->log('auth.login', $event->user);
        Log::channel('security')->info('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'ip'      => request()?->ip(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        // Jamais le mot de passe — uniquement l'identifiant tenté.
        Log::channel('security')->warning('auth.failed', [
            'email' => $event->credentials['email'] ?? null,
            'ip'    => request()?->ip(),
        ]);
        app(AuditService::class)->log('auth.failed', null, [], [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            app(AuditService::class)->log('auth.logout', $event->user);
        }
    }
}
