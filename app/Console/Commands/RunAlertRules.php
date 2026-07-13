<?php

namespace App\Console\Commands;

use App\Services\AlertRuleService;
use Illuminate\Console\Command;

/**
 * [PIL-04] Évalue les règles d'alerte actives et notifie les destinataires.
 * À planifier (ex. horaire) dans routes/console.php.
 */
class RunAlertRules extends Command
{
    protected $signature = 'alerts:run';

    protected $description = 'Évalue les alertes par seuil et notifie les rôles cibles des règles déclenchées';

    public function handle(AlertRuleService $service): int
    {
        $n = $service->run();
        $this->info("Alertes évaluées — {$n} déclenchée(s).");

        return self::SUCCESS;
    }
}
