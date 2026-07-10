<?php

namespace App\Http\Controllers;

use App\Models\CommercialValidation;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * [CDC §Workflow/validation] Page « Mes validations » : tout ce qui attend
 * MON action, selon mes permissions — plus mes soumissions et mon historique.
 */
class MyValidationsController extends Controller
{
    public function __construct(private \App\Services\PendingValidationsService $pendingValidations) {}

    public function index(Request $request): View
    {
        $user    = $request->user();
        $filters = $request->only(['type', 'search', 'late', 'date_from', 'date_to', 'amount_min']);

        // Source de vérité partagée avec la cloche de notifications.
        $pending = $this->pendingValidations->for($user);

        // ── Tri + filtres (type, recherche, retard, période, montant) ───────
        $types     = $pending->pluck('type')->unique()->sort()->values();
        $lateCount = $pending->where('is_late', true)->count();

        $pending = $pending
            ->sortBy('submitted_at')
            ->when($filters['type'] ?? null, fn (Collection $c, $v) => $c->where('type', $v))
            ->when($filters['search'] ?? null, function (Collection $c, $v) {
                $needle = mb_strtolower($v);
                return $c->filter(fn ($r) =>
                    str_contains(mb_strtolower($r['number']), $needle)
                    || str_contains(mb_strtolower($r['tiers'] ?? ''), $needle)
                    || str_contains(mb_strtolower($r['requester'] ?? ''), $needle)
                );
            })
            ->when(($filters['late'] ?? null) === '1', fn (Collection $c) => $c->where('is_late', true))
            ->when($filters['date_from'] ?? null, fn (Collection $c, $v) =>
                $c->filter(fn ($r) => $r['submitted_at'] && $r['submitted_at']->gte(\Illuminate\Support\Carbon::parse($v)->startOfDay())))
            ->when($filters['date_to'] ?? null, fn (Collection $c, $v) =>
                $c->filter(fn ($r) => $r['submitted_at'] && $r['submitted_at']->lte(\Illuminate\Support\Carbon::parse($v)->endOfDay())))
            ->when($filters['amount_min'] ?? null, fn (Collection $c, $v) =>
                $c->filter(fn ($r) => ($r['amount'] ?? 0) >= (int) $v))
            ->values();

        // Pagination manuelle (collection agrégée multi-sources) — 25/page.
        $page    = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pending = new \Illuminate\Pagination\LengthAwarePaginator(
            $pending->forPage($page, 25)->values(),
            $pending->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // ── Mes soumissions en cours ────────────────────────────────────────
        $mySubmissions = collect([
            ['Devis', Quote::class, 'ventes.devis.show'],
            ['Commande', Order::class, 'ventes.commandes.show'],
            ['BL', DeliveryNote::class, 'ventes.bons-livraison.show'],
            ['Facture', Invoice::class, 'ventes.factures.show'],
            ['Avoir', CreditNote::class, 'ventes.avoirs.show'],
        ])->flatMap(function ($def) use ($user) {
            [$label, $class, $routeName] = $def;
            return $class::where('submitted_by', $user->id)
                ->where('status', 'en_attente_validation')
                ->get()
                ->map(fn ($doc) => [
                    'type'         => $label,
                    'number'       => $doc->number,
                    'url'          => route($routeName, $doc),
                    'submitted_at' => $doc->submitted_at,
                ]);
        })->sortByDesc('submitted_at')->values();

        // ── Mon historique de validations — paginé (pageName distinct pour ne
        // pas entrer en collision avec la pagination du tableau principal) ───
        $history = CommercialValidation::where('user_id', $user->id)
            ->whereIn('action', [CommercialValidation::ACTION_VALIDATION, CommercialValidation::ACTION_REFUS])
            ->latest()
            ->paginate(10, ['*'], 'hist')
            ->withQueryString();

        return view('validations.index', compact('pending', 'lateCount', 'types', 'mySubmissions', 'history', 'filters'));
    }
}
