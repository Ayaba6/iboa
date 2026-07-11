<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\PlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionPlanningController extends Controller
{
    public function __construct(private PlanningService $planning)
    {
        $this->middleware('permission:production.view');
        $this->middleware('permission:production.create')->only(['replan']);
    }

    public function index(Request $request): View
    {
        $horizon = (int) $request->input('horizon', 7);
        $horizon = max(1, min(60, $horizon));

        $plan = $this->planning->loadByWorkCenter($horizon);

        // [X3 §19] Replanification : OF actifs déplaçables (dates / ligne)
        $ofActifs = ProductionOrder::with(['product:id,name', 'productionLine:id,name', 'client:id,name,trade_name'])
            ->whereIn('status', ['brouillon', 'matiere_allouee', 'attente_chef', 'attente_responsable', 'lance', 'en_cours', 'suspendu'])
            ->orderByRaw('date_fabrication_prevue IS NULL, date_fabrication_prevue')
            ->orderByDesc('id')->limit(30)->get();

        $lignes = ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']);

        return view('production.planning.index', compact('plan', 'horizon', 'ofActifs', 'lignes'));
    }

    /**
     * [X3 §19] Déplacer un OF (dates prévues) et/ou le réaffecter à une autre ligne.
     * Bloqué sur OF clôturé/annulé ; ligne indisponible refusée.
     */
    public function replan(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(in_array($order->status, ['termine', 'annule'], true), 422, 'OF clôturé ou annulé — replanification impossible.');

        $data = $request->validate([
            'production_line_id'      => ['nullable', 'integer', 'exists:production_lines,id'],
            'date_fabrication_prevue' => ['nullable', 'date'],
            'date_fin_prevue'         => ['nullable', 'date', 'after_or_equal:date_fabrication_prevue'],
        ]);

        if (! empty($data['production_line_id'])) {
            $ligne = ProductionLine::findOrFail($data['production_line_id']);
            if (in_array($ligne->status, ['indisponible', 'arretee', 'en_panne'], true)) {
                return back()->with('error', 'Ligne « ' . $ligne->name . ' » indisponible — réaffectation refusée.');
            }
        }

        $order->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));

        return back()->with('success', 'OF ' . $order->number . ' replanifié.');
    }
}
