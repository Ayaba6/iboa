<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\MrpService;
use App\Modules\Production\Services\TransferProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [PRODUCTION] MRP — réapprovisionnement bobines (matières premières).
 */
class MrpController extends Controller
{
    public function __construct(private MrpService $mrp)
    {
        $this->middleware('permission:production.view')->only(['index', 'ofProposals', 'transferProposals']);
        $this->middleware('permission:production.update')->only('generate');
        // Generer des OF, c'est creer des documents de production : le droit de
        // consulter le MRP ne suffit pas.
        $this->middleware('permission:production.create')->only('generateOrders');
    }

    public function index(): View
    {
        $shortfalls = $this->mrp->analyze();

        $stats = [
            'count'     => $shortfalls->count(),
            'deficit'   => (float) $shortfalls->sum('deficit'),
            'estimated' => (int) $shortfalls->sum('estimated'),
        ];

        return view('production.mrp.index', compact('shortfalls', 'stats'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $pr = $this->mrp->generatePurchaseRequest($data['product_ids'] ?? []);

        if (! $pr) {
            return back()->with('error', 'Aucun déficit matière à réapprovisionner.');
        }

        return redirect()
            ->route('achats.demandes-achat.show', $pr)
            ->with('success', 'Demande d\'achat ' . $pr->number . ' générée (réappro bobines).');
    }

    /**
     * [MRP] Propositions d'ordre de fabrication — articles fabriques pour le
     * stock dont le besoin net est positif et dont la nomenclature est active.
     */
    public function ofProposals(): View
    {
        $proposals = $this->mrp->productionProposals();

        return view('production.mrp.of', [
            'proposals' => $proposals,
            'stats'     => [
                'count'  => $proposals->count(),
                'besoin' => (float) $proposals->sum('besoin'),
            ],
        ]);
    }

    public function generateOrders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $result = $this->mrp->generateProductionOrders($data['product_ids'] ?? []);
        $n = count($result['created']);

        if ($n === 0 && $result['skipped'] === []) {
            return back()->with('error', 'Aucune proposition d’ordre de fabrication à générer.');
        }

        $message = $n . ' ordre(s) de fabrication créé(s) depuis le calcul des besoins.';

        // Les refus ne sont pas tus : on nomme l'article et le motif.
        if ($result['skipped'] !== []) {
            $details = collect($result['skipped'])
                ->map(fn ($s) => $s['produit'] . ' — ' . $s['raison'])->implode(' | ');

            return back()->with($n > 0 ? 'success' : 'error', $message)
                ->with('warning', count($result['skipped']) . ' refusé(s) : ' . $details);
        }

        return redirect()->route('production.orders.index')->with('success', $message);
    }

    /**
     * [MRP] Propositions de transfert entre depots.
     *
     * Ecran de LECTURE : il propose, il ne transfere pas. L'execution reste au
     * module Stock, qui porte deja ses controles et sa tracabilite.
     */
    public function transferProposals(TransferProposalService $transfers): View
    {
        $proposals = $transfers->proposals();

        return view('production.mrp.transfers', [
            'proposals' => $proposals,
            'stats'     => [
                'count'    => $proposals->count(),
                'quantite' => (float) $proposals->sum('quantite'),
            ],
        ]);
    }
}
