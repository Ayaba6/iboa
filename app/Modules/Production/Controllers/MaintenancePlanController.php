<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\MaintenancePlan;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Services\MaintenancePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** [CDC §13.8/§14] Plans de maintenance préventive. */
class MaintenancePlanController extends Controller
{
    public function __construct(private MaintenancePlanService $service)
    {
        $this->middleware('permission:maintenance.view')->only('index');
        $this->middleware('permission:production.update')->except('index');
    }

    public function index(): View
    {
        $plans = MaintenancePlan::with('machine')->orderBy('next_due_at')->paginate(25);
        $due   = $this->service->dueePlans();

        return view('production.maintenance.plans.index', compact('plans', 'due'));
    }

    public function create(): View
    {
        return view('production.maintenance.plans.form', [
            'plan'     => new MaintenancePlan(['frequency_days' => 30, 'is_active' => true]),
            'machines' => ProductionMachine::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($this->validateData($request));

        return redirect()->route('production.maintenance.plans.index')->with('success', 'Plan de maintenance créé.');
    }

    public function edit(MaintenancePlan $plan): View
    {
        return view('production.maintenance.plans.form', [
            'plan'     => $plan,
            'machines' => ProductionMachine::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $this->service->update($plan, $this->validateData($request));

        return redirect()->route('production.maintenance.plans.index')->with('success', 'Plan mis à jour.');
    }

    public function destroy(MaintenancePlan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('success', 'Plan supprimé.');
    }

    /** Génère les interventions pour tous les plans dus. */
    public function generate(): RedirectResponse
    {
        $generated = $this->service->generateDueInterventions();

        if ($generated->isEmpty()) {
            return back()->with('info', 'Aucun plan dû — rien à générer.');
        }

        return redirect()->route('production.maintenance.index')
            ->with('success', count($generated) . ' intervention(s) préventive(s) générée(s).');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id'      => ['required', 'integer', 'exists:production_machines,id'],
            'name'            => ['required', 'string', 'max:150'],
            'frequency_days'  => ['required', 'integer', 'min:1'],
            'instructions'    => ['nullable', 'string', 'max:2000'],
            'next_due_at'     => ['nullable', 'date'],
            'is_active'       => ['nullable', 'boolean'],
        ]);
    }
}
