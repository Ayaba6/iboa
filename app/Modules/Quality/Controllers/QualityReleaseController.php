<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Quality\Models\QualityRelease;
use App\Modules\Quality\Services\QualityReleaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [QUA-07] Libération qualité des lots de fabrication.
 */
class QualityReleaseController extends Controller
{
    public function __construct(private QualityReleaseService $service)
    {
        $this->middleware('permission:quality.view')->only(['index']);
        $this->middleware('permission:quality.manage')->only(['decide']);
    }

    public function index(Request $request): View
    {
        $state = $request->input('state', 'a_liberer');

        $items = ProductionBatch::with(['product', 'productionOrder', 'qualityRelease'])
            ->when($state === 'a_liberer', fn ($q) => $q->where(fn ($w) => $w
                ->whereDoesntHave('qualityRelease')
                ->orWhereHas('qualityRelease', fn ($r) => $r->whereIn('status', ['en_attente', 'refuse']))))
            ->when($state === 'libere', fn ($q) => $q->whereHas('qualityRelease', fn ($r) => $r->where('status', 'libere')))
            ->when($state === 'derogation', fn ($q) => $q->whereHas('qualityRelease', fn ($r) => $r->where('status', 'derogation')))
            ->when($state === 'refuse', fn ($q) => $q->whereHas('qualityRelease', fn ($r) => $r->where('status', 'refuse')))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'a_liberer' => ProductionBatch::where('status', 'en_cours')
                ->where(fn ($w) => $w->whereDoesntHave('qualityRelease')
                    ->orWhereHas('qualityRelease', fn ($r) => $r->where('status', '!=', 'libere')))
                ->count(),
            'liberes'    => QualityRelease::where('status', 'libere')->count(),
            'derogation' => QualityRelease::where('status', 'derogation')->count(),
            'refuses'    => QualityRelease::where('status', 'refuse')->count(),
        ];

        return view('qualite.releases.index', compact('items', 'stats', 'state'));
    }

    public function decide(Request $request, ProductionBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'decision'             => ['required', 'in:libere,refuse,derogation'],
            'decision_comment'     => ['nullable', 'string', 'max:1000'],
            'derogation_reference' => ['nullable', 'string', 'max:120', 'required_if:decision,derogation'],
        ], [
            'derogation_reference.required_if' => 'La référence de dérogation est obligatoire.',
        ]);

        $this->service->decide(
            $batch,
            $data['decision'],
            $data['decision_comment'] ?? null,
            $data['derogation_reference'] ?? null,
        );

        $msg = match ($data['decision']) {
            'libere'     => 'Lot libéré — disponible pour expédition.',
            'refuse'     => 'Lot refusé — bloqué en quarantaine.',
            'derogation' => 'Lot libéré sous dérogation.',
        };

        return back()->with('success', $msg);
    }
}
