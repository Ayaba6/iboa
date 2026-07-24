<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * [Phase 2.7] a3:audit-production — vérifie que la configuration est saine
 * pour un déploiement de production. Lecture seule, exit 1 si un point
 * bloquant est détecté. À lancer AVANT toute bascule.
 */
class AuditProduction extends Command
{
    protected $signature = 'a3:audit-production';
    protected $description = 'Audit de préparation à la production (config, sécurité, exploitation) — lecture seule';

    private int $blocking = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $isProd = app()->environment('production');
        $this->line('Environnement : ' . app()->environment());

        // ── Bloquants en production ────────────────────────────────────────
        if (config('app.debug')) {
            $this->flag($isProd, 'APP_DEBUG=true — fuite de traces/secrets en cas d\'erreur. Doit être false en production.');
        }
        if (config('app.key') === null || config('app.key') === '') {
            $this->flag(true, 'APP_KEY vide — chiffrement des sessions/cookies inopérant.');
        }

        // Drivers : sync/file inacceptables en production multi-utilisateurs
        if ($isProd) {
            if (config('queue.default') === 'sync') {
                $this->flag(true, 'QUEUE_CONNECTION=sync en production — les jobs (paie, mails, sync) bloquent la requête.');
            }
            if (config('session.driver') === 'file') {
                $this->warn_('SESSION_DRIVER=file — acceptable mono-serveur, préférer redis/database en multi-serveurs.');
            }
            if (config('cache.default') === 'array') {
                $this->flag(true, 'CACHE_STORE=array en production — cache non persistant, perte à chaque requête.');
            }
        }

        // ── Maker-checker (fail-closed) ────────────────────────────────────
        if ($isProd && ! config('security.maker_checker.enabled')) {
            $this->flag(true, 'Maker-checker désactivé en production — séparation des tâches non appliquée.');
        }

        // ── Tables d'infrastructure ────────────────────────────────────────
        foreach (['jobs', 'failed_jobs', 'sessions', 'cache', 'personal_access_tokens', 'audit_logs'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->warn_("Table d'infrastructure « $t » absente (requise selon la configuration retenue).");
            }
        }

        // ── HTTPS / URL ────────────────────────────────────────────────────
        if ($isProd && ! str_starts_with((string) config('app.url'), 'https://')) {
            $this->flag(true, 'APP_URL non-HTTPS en production — HSTS et cookies sécurisés inopérants.');
        }

        // ── Journal d'audit : intégrité de la chaîne ───────────────────────
        $broken = app(\App\Services\AuditService::class)->verifyChain();
        if ($broken !== []) {
            $this->flag(true, 'Chaîne du journal d\'audit rompue (' . count($broken) . ' entrée(s)) — traçabilité compromise.');
        }

        $this->newLine();
        if ($this->blocking === 0 && $this->warnings === 0) {
            $this->info('AUDIT PRODUCTION PROPRE — aucun point bloquant ni avertissement.');

            return self::SUCCESS;
        }
        $this->line("Bilan : {$this->blocking} bloquant(s), {$this->warnings} avertissement(s).");
        if ($this->blocking > 0) {
            $this->error('Des points BLOQUANTS empêchent la mise en production.');

            return self::FAILURE;
        }
        $this->warn('Avertissements seulement — mise en production possible après revue.');

        return self::SUCCESS;
    }

    private function flag(bool $active, string $msg): void
    {
        if (! $active) {
            $this->line('  (hors production) ' . $msg);

            return;
        }
        $this->blocking++;
        $this->warn('  ✗ BLOQUANT : ' . $msg);
    }

    private function warn_(string $msg): void
    {
        $this->warnings++;
        $this->line('  ⚠ ' . $msg);
    }
}
