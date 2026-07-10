<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use Illuminate\Support\Collection;

/**
 * [CDC §Workflow] Source de vérité unique des documents « en attente de
 * validation » pour un utilisateur donné, selon ses permissions.
 *
 * Consommée par la page « Mes validations » ET par la cloche de notifications :
 * un document apparaît tant que son statut est en attente, disparaît dès
 * validation/refus/annulation — indépendamment de toute notion de « lu ».
 */
class PendingValidationsService
{
    /** Au-delà de ce délai, une validation en attente est « en retard ». */
    public const LATE_AFTER_HOURS = 48;

    /** @return Collection<int, array<string,mixed>> */
    public function for(User $user): Collection
    {
        $pending = collect();

        // ── Documents commerciaux (sales.validate) ─────────────────────────
        if ($user->can('sales.validate')) {
            $commercial = [
                ['Devis',    Quote::class,        'ventes.devis.show'],
                ['Commande', Order::class,        'ventes.commandes.show'],
                ['BL',       DeliveryNote::class, 'ventes.bons-livraison.show'],
                ['Facture',  Invoice::class,      'ventes.factures.show'],
                ['Avoir',    CreditNote::class,   'ventes.avoirs.show'],
            ];
            foreach ($commercial as [$label, $class, $routeName]) {
                $class::where('status', 'en_attente_validation')
                    ->with(['client:id,name', 'submittedBy:id,name'])
                    ->get()
                    ->each(function ($doc) use (&$pending, $label, $routeName) {
                        $pending->push($this->row(
                            type: $label,
                            number: $doc->number,
                            url: route($routeName, $doc),
                            tiers: $doc->client?->name,
                            amount: (int) ($doc->total_ttc ?? 0),
                            requester: $doc->submittedBy?->name,
                            submittedAt: $doc->submitted_at,
                            level: 'Validation interne',
                        ));
                    });
            }
        }

        // ── Ordres de fabrication (§13.3 — 2 niveaux) ──────────────────────
        if ($user->can('production.validate_chef')) {
            ProductionOrder::where('status', 'attente_chef')->with('client:id,name')->get()
                ->each(fn ($of) => $pending->push($this->row(
                    'OF', $of->number, route('production.orders.show', $of),
                    $of->client?->name, null, null, $of->updated_at, 'Validation Chef Atelier',
                )));
        }
        if ($user->can('production.validate_responsable')) {
            ProductionOrder::where('status', 'attente_responsable')->with('client:id,name')->get()
                ->each(fn ($of) => $pending->push($this->row(
                    'OF', $of->number, route('production.orders.show', $of),
                    $of->client?->name, null, null, $of->updated_at, 'Validation Responsable Production',
                )));
        }

        // ── Modifications exceptionnelles d'OF (§13.10 — étape selon rôle) ──
        $modSteps = [
            ['production.modification.avis_chef',       'modification_chef_avis_at',       'Avis Chef Production (1/4)'],
            ['production.modification.avis_commercial', 'modification_commercial_avis_at', 'Avis Commercial (2/4)'],
            ['production.modification.avis_finance',    'modification_finance_avis_at',    'Avis Finance (3/4)'],
            ['production.modification.avis_dg',         'modification_dg_approved_at',     'Validation DG (4/4)'],
        ];
        $previousStepCol = null;
        foreach ($modSteps as [$perm, $col, $label]) {
            if ($user->can($perm)) {
                ProductionOrder::where('modification_status', 'en_attente')
                    ->whereNull($col)
                    ->when($previousStepCol, fn ($q) => $q->whereNotNull($previousStepCol))
                    ->get()
                    ->each(fn ($of) => $pending->push($this->row(
                        'Modification OF', $of->number, route('production.orders.show', $of),
                        null, null, null, $of->modification_requested_at, $label,
                    )));
            }
            $previousStepCol = $col;
        }

        // ── Déclarations de production (§13.3 — visa chef d'équipe) ─────────
        if ($user->can('production.validate_declaration')) {
            ProductionOutput::where('status', 'declaree')->with('productionOrder:id,number')->get()
                ->each(fn ($o) => $pending->push($this->row(
                    'Déclaration', 'OF ' . ($o->productionOrder?->number ?? '#'.$o->production_order_id),
                    $o->productionOrder ? route('production.orders.show', $o->production_order_id) : '#',
                    null, null, null, $o->created_at, 'Visa chef d\'équipe',
                )));
        }

        // ── Rebuts (§13.9 — chef atelier puis qualité) ──────────────────────
        if ($user->can('production.declare')) {
            ProductionWaste::where('type', 'rebut')->whereNull('validated_by_chef')
                ->with('productionOrder:id,number')->get()
                ->each(fn ($w) => $pending->push($this->row(
                    'Rebut', 'OF ' . ($w->productionOrder?->number ?? '#'.$w->production_order_id),
                    $w->productionOrder ? route('production.orders.show', $w->production_order_id) : '#',
                    null, (int) $w->value, null, $w->created_at, 'Validation Chef Atelier',
                )));
        }
        if ($user->can('quality.manage')) {
            ProductionWaste::where('type', 'rebut')->whereNotNull('validated_by_chef')->whereNull('validated_by_quality')
                ->with('productionOrder:id,number')->get()
                ->each(fn ($w) => $pending->push($this->row(
                    'Rebut', 'OF ' . ($w->productionOrder?->number ?? '#'.$w->production_order_id),
                    $w->productionOrder ? route('production.orders.show', $w->production_order_id) : '#',
                    null, (int) $w->value, null, $w->created_at, 'Validation Qualité',
                )));
        }

        // ── Maintenance (§13.8 — interventions planifiées à traiter) ────────
        if ($user->can('maintenance.manage')) {
            MachineMaintenance::whereIn('status', ['planifie', 'en_cours'])->with('machine:id,name')->get()
                ->each(fn ($m) => $pending->push($this->row(
                    'Maintenance', $m->machine?->name ?? ('#'.$m->id),
                    route('production.maintenance.index'),
                    null, (int) $m->cost, null, $m->planned_at, $m->status === 'planifie' ? 'À démarrer' : 'À clôturer',
                )));
        }

        // ── Demandes d'achat (§13.4 — seuils) ───────────────────────────────
        $daLevel = match (true) {
            $user->can('purchase_requests.validate_l3') => 3,
            $user->can('purchase_requests.validate_l2') => 2,
            $user->can('purchase_requests.validate_l1') => 1,
            default => 0,
        };
        if ($daLevel > 0) {
            PurchaseRequest::where('status', 'soumis')->with('requestedBy:id,name')->get()
                ->filter(function ($da) use ($daLevel) {
                    $needed = match (true) {
                        (float) $da->total_estimated >= 5_000_000 => 3,
                        (float) $da->total_estimated >= 500_000   => 2,
                        default                                   => 1,
                    };
                    return $daLevel >= $needed;
                })
                ->each(fn ($da) => $pending->push($this->row(
                    'Demande achat', $da->number, route('achats.demandes-achat.show', $da),
                    null, (int) $da->total_estimated, $da->requestedBy?->name ?? null,
                    $da->submitted_at, 'Seuil ' . number_format((float) $da->total_estimated, 0, ',', ' ') . ' F',
                )));
        }

        // [ANTI-DOUBLON] Une même demande ne doit apparaître qu'une fois,
        // même si plusieurs règles la remontent (ex. cumul de permissions).
        return $pending->unique(fn ($r) => $r['type'] . '|' . $r['number'] . '|' . $r['level'])->values();
    }

    /** @return array<string,mixed> */
    private function row(
        string $type, string $number, string $url, ?string $tiers,
        ?int $amount, ?string $requester, $submittedAt, string $level,
    ): array {
        return [
            'type'         => $type,
            'number'       => $number,
            'url'          => $url,
            'tiers'        => $tiers,
            'amount'       => $amount,
            'requester'    => $requester,
            'submitted_at' => $submittedAt,
            'level'        => $level,
            'is_late'      => $submittedAt && $submittedAt->lt(now()->subHours(self::LATE_AFTER_HOURS)),
        ];
    }
}
