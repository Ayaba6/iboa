<?php

namespace App\Console\Commands;

use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Modules\Production\Models\ProductionOrder;
use App\Notifications\ValidationStepNotification;
use Illuminate\Console\Command;

/**
 * [CDC §Workflow — relance automatique] Relance les valideurs quand un
 * document reste « en attente de validation » au-delà du délai configuré
 * (config/validation.php, par module). Planifiée quotidiennement.
 */
class RemindPendingValidations extends Command
{
    protected $signature = 'validations:remind {--dry-run : Liste sans notifier}';

    protected $description = 'Relance les valideurs pour les documents en attente depuis trop longtemps';

    public function handle(): int
    {
        $sent = 0;

        // ── Documents commerciaux → rôles valideurs par type ────────────────
        $commercial = [
            ['Devis',    Quote::class,        'ventes.devis.show',          ['responsable_commercial']],
            ['Commande', Order::class,        'ventes.commandes.show',      ['comptable', 'daf']],
            ['BL',       DeliveryNote::class, 'ventes.bons-livraison.show', ['responsable_commercial']],
            ['Facture',  Invoice::class,      'ventes.factures.show',       ['comptable']],
            ['Avoir',    CreditNote::class,   'ventes.avoirs.show',         ['comptable']],
        ];
        $hours = (int) config('validation.reminder_hours.commercial', 48);
        foreach ($commercial as [$label, $class, $routeName, $roles]) {
            $docs = $class::where('status', 'en_attente_validation')
                ->where('submitted_at', '<', now()->subHours($hours))
                ->get();
            foreach ($docs as $doc) {
                $sent += $this->remind(
                    roles: $roles,
                    label: $label,
                    number: $doc->number,
                    url: route($routeName, $doc),
                    modelType: class_basename($class),
                    modelId: $doc->id,
                    since: $doc->submitted_at,
                );
            }
        }

        // ── OF en attente de validation (§13.3) ─────────────────────────────
        $hoursOf = (int) config('validation.reminder_hours.production', 24);
        foreach ([
            ['attente_chef',        ['chef_atelier']],
            ['attente_responsable', ['chef_production', 'directeur_usine']],
        ] as [$status, $roles]) {
            $ofs = ProductionOrder::where('status', $status)
                ->where('updated_at', '<', now()->subHours($hoursOf))
                ->get();
            foreach ($ofs as $of) {
                $sent += $this->remind($roles, 'OF', $of->number,
                    route('production.orders.show', $of), 'ProductionOrder', $of->id, $of->updated_at);
            }
        }

        // ── Demandes d'achat soumises (§13.4) ───────────────────────────────
        $hoursDa = (int) config('validation.reminder_hours.achats', 48);
        $das = PurchaseRequest::where('status', 'soumis')
            ->where('submitted_at', '<', now()->subHours($hoursDa))
            ->get();
        foreach ($das as $da) {
            $roles = match (true) {
                (float) $da->total_estimated >= 5_000_000 => ['directeur'],
                (float) $da->total_estimated >= 500_000   => ['daf'],
                default                                   => ['directeur_usine'],
            };
            $sent += $this->remind($roles, 'Demande achat', $da->number,
                route('achats.demandes-achat.show', $da), 'PurchaseRequest', $da->id, $da->submitted_at);
        }

        $this->info("Relances envoyées : {$sent}");

        return self::SUCCESS;
    }

    /** @param array<int,string> $roles */
    private function remind(array $roles, string $label, string $number, string $url, string $modelType, int $modelId, $since): int
    {
        $waiting = $since ? $since->diffForHumans(short: true) : 'un moment';

        if ($this->option('dry-run')) {
            $this->line("[dry-run] {$label} {$number} — en attente depuis {$waiting} → " . implode(',', $roles));
            return 0;
        }

        ValidationStepNotification::sendToRoles(
            $roles,
            title: "Relance : {$label} en attente",
            message: "{$label} {$number} attend votre validation depuis {$waiting} — merci de le traiter.",
            url: $url,
            modelType: $modelType,
            modelId: $modelId,
            type: 'validation_reminder',
            icon: 'bell-alert',
            color: 'red',
        );

        return 1;
    }
}
