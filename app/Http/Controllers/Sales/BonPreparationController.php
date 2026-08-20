<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\BonPreparation;
use App\Models\DocumentSetting;
use App\Models\Order;
use App\Services\BonPreparationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BonPreparationController extends Controller
{
    public function __construct(private BonPreparationService $service)
    {
        $this->middleware('permission:bon_preparations.view')->only('index', 'show', 'pdf');
        $this->middleware('permission:bon_preparations.update')->only('startLoading', 'finishLoading');
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'payment_mode', 'search']);

        $bps = BonPreparation::with(['order.client', 'validatedBy', 'loadedBy'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['payment_mode'] ?? null, fn ($q, $m) => $q->where('payment_mode', $m))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($sq) =>
                $sq->where('number', 'like', "%{$s}%")
                   ->orWhereHas('order', fn ($oq) => $oq->where('number', 'like', "%{$s}%"))
            ))
            ->orderByRaw("CASE status WHEN 'en_attente' THEN 0 WHEN 'en_cours' THEN 1 WHEN 'charge' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(25);

        // [Perf] Un COUNT GROUPÉ, pas un COUNT par statut. La version précédente
        // lançait quatre requêtes là où une suffit, dont trois strictement
        // identiques à un paramètre près.
        $counts = BonPreparation::query()
            ->groupBy('status')
            ->pluck(DB::raw('COUNT(*)'), 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $summary = [
            'total'      => array_sum($counts),
            'en_attente' => $counts['en_attente'] ?? 0,
            'en_cours'   => $counts['en_cours'] ?? 0,
            'charge'     => $counts['charge'] ?? 0,
        ];

        return view('ventes.bons-preparation.index', compact('bps', 'filters', 'summary'));
    }

    public function show(BonPreparation $bonPreparation): View
    {
        $bonPreparation->load(['order.client', 'order.items.product', 'validatedBy', 'loadedBy', 'paymentRecordedBy']);
        return view('ventes.bons-preparation.show', compact('bonPreparation'));
    }

    /** [Ventes] Impression PDF du bon de préparation (liste des articles à charger). */
    public function pdf(BonPreparation $bonPreparation, Request $request)
    {
        $bonPreparation->load(['order.client', 'order.items.product', 'validatedBy', 'loadedBy']);
        $settings = DocumentSetting::query()->first();

        $filename = 'BP-' . $bonPreparation->number . '.pdf';
        $pdf = Pdf::loadView('ventes.pdf.bon-preparation', compact('bonPreparation', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        return $request->boolean('stream')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    public function startLoading(Request $request, BonPreparation $bonPreparation): RedirectResponse
    {
        try {
            // [MTO §15] Champ DISTINCT de tout autre motif : déroger à la qualité
            // doit être un geste explicite, jamais l'effet de bord d'une note.
            $this->service->startLoading($bonPreparation, $request->input('derogation_qualite_motif'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Chargement démarré.');
    }

    public function finishLoading(Request $request, BonPreparation $bonPreparation): RedirectResponse
    {
        try {
            $this->service->finishLoading($bonPreparation, $request->input('derogation_qualite_motif'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()
            ->route('ventes.commandes.show', $bonPreparation->order_id)
            ->with('success', 'Chargement terminé — bon de livraison à créer.');
    }

    /**
     * POST ventes/bons-preparation/{bonPreparation}/cancel — annulation motivée.
     *
     * [Ventes §17] Le module n'exposait aucune action d'annulation : un bon créé
     * par erreur restait à l'écran indéfiniment.
     */
    public function cancel(Request $request, BonPreparation $bonPreparation): RedirectResponse
    {
        $data = $request->validate(
            ['motif' => ['required', 'string', 'min:5', 'max:500']],
            [
                'motif.required' => "Le motif d'annulation est obligatoire.",
                'motif.min'      => 'Le motif doit compter au moins 5 caractères.',
            ]
        );

        try {
            $this->service->cancel($bonPreparation, $data['motif']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bon de préparation ' . $bonPreparation->number . ' annulé.');
    }
}
