<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [Production] Ordonnancement — positionne les OF dans le temps, ligne par ligne.
 *
 * Distinct du plan de charge, qui mesure une capacité agrégée. Ici on place des
 * ordres nommés sur des créneaux, et l'on refuse qu'une ligne en produise deux
 * à la fois.
 */
class ProductionScheduleController extends Controller
{
    public function __construct(private ProductionSchedulingService $scheduling)
    {
        // Même exigence que le plan de charge : ordonnancer, c'est piloter.
        $this->middleware('permission:production.update');
    }

    public function index(Request $request): View
    {
        return view('production.schedule.index', $this->scheduling->board(
            $request->input('du'),
            $request->input('au'),
        ));
    }

    public function schedule(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'date_debut_prevue'  => ['nullable', 'date'],
            'heure_debut_prevue' => ['nullable', 'date_format:H:i'],
            'date_fin_prevue'    => ['nullable', 'date'],
            'heure_fin_prevue'   => ['nullable', 'date_format:H:i'],
            'priorite'           => ['nullable', 'string', 'max:20'],
            'equipe_prevue'      => ['nullable', 'string', 'max:60'],
        ]);

        $this->scheduling->schedule($order, $data);

        return back()->with('success', 'OF '.$order->number.' ordonnancé.');
    }

    public function unschedule(ProductionOrder $order): RedirectResponse
    {
        // Retire du planning SANS toucher à l'avancement : un OF dépositionné
        // reste lancé, consommé, déclaré. Seul son créneau disparaît.
        $this->scheduling->unschedule($order);

        return back()->with('success', 'OF '.$order->number.' retiré du planning.');
    }
}
