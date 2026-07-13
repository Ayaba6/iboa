<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionDowntime;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionDowntimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [PRO Temps d'arrêt] Écran de suivi et déclaration des arrêts de production.
 */
class ProductionDowntimeController extends Controller
{
    public function __construct(private ProductionDowntimeService $service) {}

    public function index(Request $request): View
    {
        $days = max(1, min(365, (int) $request->integer('jours', 30)));

        $downtimes = ProductionDowntime::with(['machine', 'productionOrder', 'declaredBy'])
            ->where('started_at', '>=', now()->subDays($days))
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();

        $byMachineStats = $this->service->statsByMachine($days);
        $machines = ProductionMachine::whereIn('id', $byMachineStats->keys()->filter())->get()->keyBy('id');
        $byMachine = $byMachineStats->map(fn ($r) => [
            'machine' => $machines[$r->machine_id]->name ?? '— Non affectée',
            'minutes' => (int) $r->minutes,
            'count'   => (int) $r->n,
        ])->sortByDesc('minutes')->values();

        $byReason = $this->service->statsByReason($days);

        $totalMinutes = $downtimes->whereNotNull('duration_minutes')->sum('duration_minutes');
        $ongoing      = $downtimes->where('ended_at', null)->count();

        $orders   = ProductionOrder::whereIn('status', ['lance', 'en_cours'])->orderByDesc('id')->limit(100)->get();
        $machinesList = ProductionMachine::where('is_active', true)->orderBy('name')->get();

        return view('production.downtimes.index', compact(
            'downtimes', 'byMachine', 'byReason', 'totalMinutes', 'ongoing', 'days', 'orders', 'machinesList'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'production_order_id' => ['nullable', 'exists:production_orders,id'],
            'machine_id'          => ['nullable', 'exists:production_machines,id'],
            'category'            => ['required', 'in:planifie,non_planifie'],
            'reason'              => ['required', 'in:panne,changement_outil,rupture_matiere,reglage,attente,nettoyage,autre'],
            'description'         => ['nullable', 'string', 'max:255'],
            'started_at'          => ['required', 'date'],
            'ended_at'            => ['nullable', 'date', 'after_or_equal:started_at'],
        ]);

        try {
            $this->service->declare($data);
            return back()->with('success', 'Arrêt déclaré.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function close(ProductionDowntime $downtime): RedirectResponse
    {
        try {
            $this->service->close($downtime);
            return back()->with('success', 'Arrêt clôturé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(ProductionDowntime $downtime): RedirectResponse
    {
        $downtime->delete();
        return back()->with('success', 'Arrêt supprimé.');
    }
}
