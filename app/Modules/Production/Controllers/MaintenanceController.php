<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $service)
    {
        $this->middleware('permission:maintenance.view')->only('index');
        $this->middleware('permission:production.update')->except('index');
    }

    public function index(): View
    {
        $maintenances = MachineMaintenance::with(['machine', 'operator'])
            ->orderByRaw("CASE status WHEN 'en_cours' THEN 0 WHEN 'planifie' THEN 1 ELSE 2 END")
            ->orderByDesc('id')->paginate(25);

        $due = $this->service->dueList();

        $machineKpis = ProductionMachine::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($m) => ['machine' => $m] + $this->service->machineKpis($m, 30));

        return view('production.maintenance.index', compact('maintenances', 'due', 'machineKpis'));
    }

    public function create(Request $request): View
    {
        $m = new MachineMaintenance(['type' => $request->input('type', 'preventive'), 'machine_id' => $request->input('machine_id'), 'planned_at' => now()]);

        return view('production.maintenance.form', $this->formData($m));
    }

    public function store(Request $request): RedirectResponse
    {
        $maintenance = MachineMaintenance::create($this->validateData($request) + ['company_id' => currentCompany()->id]);
        $this->syncOperations($maintenance, $request);

        // [CDC §13.8] Intervention créée → Service Maintenance notifié.
        \App\Notifications\ValidationStepNotification::sendToRoles(
            ['technicien_maintenance'],
            title: 'Intervention maintenance à traiter',
            message: "Intervention {$maintenance->type} planifiée sur {$maintenance->machine?->name}.",
            url: route('production.maintenance.edit', $maintenance),
            modelType: 'MachineMaintenance',
            modelId: $maintenance->id,
            type: 'maintenance_requested',
            icon: 'wrench-screwdriver',
            color: 'orange',
        );

        return redirect()->route('production.maintenance.index')->with('success', 'Intervention enregistrée.');
    }

    public function edit(MachineMaintenance $maintenance): View
    {
        $maintenance->load(['parts.product', 'operations']);
        return view('production.maintenance.form', $this->formData($maintenance));
    }

    public function update(Request $request, MachineMaintenance $maintenance): RedirectResponse
    {
        $maintenance->update($this->validateData($request));
        $maintenance->operations()->delete();
        $this->syncOperations($maintenance, $request);

        return redirect()->route('production.maintenance.index')->with('success', 'Intervention mise à jour.');
    }

    /** [Maquette Intervention] Enregistre les opérations planifiées. */
    private function syncOperations(MachineMaintenance $maintenance, Request $request): void
    {
        foreach ((array) $request->input('operations', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $maintenance->operations()->create([
                'number'               => $row['number'] ?? ($i + 1),
                'code'                 => $row['code'] ?? null,
                'name'                 => $row['name'],
                'technician_id'        => $row['technician_id'] ?? null,
                'planned_duration_min' => $row['planned_duration_min'] ?? null,
                'start_time'           => $row['start_time'] ?? null,
                'end_time'             => $row['end_time'] ?? null,
                'status'               => $row['status'] ?? 'planifiee',
                'is_critical'          => ! empty($row['is_critical']),
                'sort_order'           => $i,
            ]);
        }
    }

    public function destroy(MachineMaintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return back()->with('success', 'Intervention supprimée.');
    }

    public function start(MachineMaintenance $maintenance): RedirectResponse
    {
        try {
            $this->service->start($maintenance);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Intervention démarrée — machine en maintenance.');
    }

    /** [CDC §13.8] Ajoute une pièce de rechange consommée — sortie de stock réelle. */
    public function storePart(Request $request, MachineMaintenance $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity'     => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $this->service->consumePart($maintenance, $data['product_id'], (float) $data['quantity'], $data['warehouse_id']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pièce enregistrée — sortie de stock effectuée.');
    }

    public function destroyPart(\App\Modules\Production\Models\MaintenancePart $part): RedirectResponse
    {
        $part->delete();

        return back()->with('success', 'Pièce retirée de l\'intervention.');
    }

    public function finish(Request $request, MachineMaintenance $maintenance): RedirectResponse
    {
        $request->validate(['downtime_minutes' => ['nullable', 'numeric', 'min:0'], 'cost' => ['nullable', 'integer', 'min:0']]);
        $this->service->finish(
            $maintenance,
            $request->filled('downtime_minutes') ? (float) $request->downtime_minutes : null,
            $request->filled('cost') ? (int) $request->cost : null,
        );

        // [CDC §13.8] Clôture intervention → notifie le demandeur (machine de
        // nouveau disponible pour reprendre la production).
        $maintenance->refresh();
        if ($maintenance->createdBy) {
            $maintenance->createdBy->notify(new \App\Notifications\ValidationStepNotification(
                title: 'Intervention maintenance clôturée',
                message: "Intervention sur {$maintenance->machine?->name} terminée — machine réactivée.",
                url: route('production.maintenance.edit', $maintenance),
                modelType: 'MachineMaintenance', modelId: $maintenance->id,
                type: 'maintenance_closed', icon: 'wrench-screwdriver', color: 'green',
            ));
        }

        return back()->with('success', 'Intervention terminée — machine réactivée.');
    }

    private function formData(MachineMaintenance $m): array
    {
        return [
            'maintenance' => $m,
            'machines'    => ProductionMachine::orderBy('name')->get(['id', 'name', 'code']),
            'employees'   => Employee::orderBy('last_name')->get(),
            'products'    => \App\Models\Product::where('is_stockable', true)->orderBy('name')->get(['id', 'name', 'reference']),
            'warehouses'  => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'lines'       => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'code', 'name']),
            'users'       => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id'       => ['required', 'integer', 'exists:production_machines,id'],
            'type'             => ['required', 'in:preventive,corrective,curative,ameliorative'],
            'title'            => ['required', 'string', 'max:200'],
            'status'           => ['required', 'in:brouillon,planifie,en_cours,termine'],
            'planned_at'       => ['nullable', 'date'],
            'downtime_minutes' => ['nullable', 'numeric', 'min:0'],
            'cost'             => ['nullable', 'integer', 'min:0'],
            'operator_id'      => ['nullable', 'integer', 'exists:employees,id'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            // [Maquette Intervention] général
            'code'               => ['nullable', 'string', 'max:30'],
            'site'               => ['nullable', 'string', 'max:20'],
            'requester_id'       => ['nullable', 'integer', 'exists:users,id'],
            'priorite'           => ['nullable', 'string', 'max:15'],
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time'   => ['nullable', 'date_format:H:i'],
            'atelier'            => ['nullable', 'string', 'max:60'],
            'depot_pieces_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
            'request_source'     => ['nullable', 'string', 'max:30'],
            'urgency_level'      => ['nullable', 'string', 'max:30'],
            'ot_reference'       => ['nullable', 'string', 'max:30'],
            // résumé technique
            'symptom'            => ['nullable', 'string', 'max:150'],
            'probable_cause'     => ['nullable', 'string', 'max:150'],
            'critical_part'      => ['nullable', 'string', 'max:150'],
            'production_impact'  => ['nullable', 'string', 'max:150'],
            // opérations
            'operations'                        => ['nullable', 'array'],
            'operations.*.number'               => ['nullable', 'integer', 'min:0'],
            'operations.*.code'                 => ['nullable', 'string', 'max:20'],
            'operations.*.name'                 => ['nullable', 'string', 'max:150'],
            'operations.*.technician_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'operations.*.planned_duration_min' => ['nullable', 'numeric', 'min:0'],
            'operations.*.start_time'           => ['nullable', 'date_format:H:i'],
            'operations.*.end_time'             => ['nullable', 'date_format:H:i'],
            'operations.*.status'               => ['nullable', 'string', 'max:20'],
            'operations.*.is_critical'          => ['nullable', 'boolean'],
        ]) + [
            'machine_stop_required'           => $request->boolean('machine_stop_required'),
            'electrical_lockout'              => $request->boolean('electrical_lockout'),
            'allow_subcontracting'            => $request->boolean('allow_subcontracting'),
            'maintenance_validation_required' => $request->boolean('maintenance_validation_required'),
            'quality_check_after'             => $request->boolean('quality_check_after'),
        ];
    }
}
