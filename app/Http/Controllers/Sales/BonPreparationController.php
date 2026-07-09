<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\BonPreparation;
use App\Models\Order;
use App\Services\BonPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BonPreparationController extends Controller
{
    public function __construct(private BonPreparationService $service)
    {
        $this->middleware('permission:bon_preparations.view')->only('index', 'show');
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

        $summary = [
            'total'      => BonPreparation::count(),
            'en_attente' => BonPreparation::where('status', 'en_attente')->count(),
            'en_cours'   => BonPreparation::where('status', 'en_cours')->count(),
            'charge'     => BonPreparation::where('status', 'charge')->count(),
        ];

        return view('ventes.bons-preparation.index', compact('bps', 'filters', 'summary'));
    }

    public function show(BonPreparation $bonPreparation): View
    {
        $bonPreparation->load(['order.client', 'order.items.product', 'validatedBy', 'loadedBy', 'paymentRecordedBy']);
        return view('ventes.bons-preparation.show', compact('bonPreparation'));
    }

    public function startLoading(BonPreparation $bonPreparation): RedirectResponse
    {
        try {
            $this->service->startLoading($bonPreparation);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Chargement démarré.');
    }

    public function finishLoading(BonPreparation $bonPreparation): RedirectResponse
    {
        try {
            $this->service->finishLoading($bonPreparation);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()
            ->route('ventes.commandes.show', $bonPreparation->order_id)
            ->with('success', 'Chargement terminé — bon de livraison à créer.');
    }
}
