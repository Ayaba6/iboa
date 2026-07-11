<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkCenterController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct()
    {
        $this->middleware('permission:production.create');
        
    }

    public function index(): View
    {
        $centers = WorkCenter::with(['machine', 'createdBy'])->orderBy('name')->paginate(25);

        return view('production.work-centers.index', compact('centers'));
    }

    public function create(): View
    {
        return view('production.work-centers.form', $this->formData(new WorkCenter()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        unset($data['documents']);
        $wc = WorkCenter::create($data + ['company_id' => currentCompany()->id]);
        $this->uploadDocuments($wc, $request);

        return redirect()->route('production.work-centers.index')->with('success', 'Centre de travail créé.');
    }

    public function edit(WorkCenter $workCenter): View
    {
        $workCenter->load('attachments');
        return view('production.work-centers.form', $this->formData($workCenter));
    }

    private function formData(WorkCenter $center): array
    {
        return [
            'center'      => $center,
            'machines'    => $this->machines(),
            'lines'       => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'code', 'name']),
            'users'       => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'warehouses'  => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'workCenters' => WorkCenter::when($center->exists, fn ($q) => $q->whereKeyNot($center->id))
                                ->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    public function update(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $data = $this->validateData($request);
        unset($data['documents']);
        $workCenter->update($data);
        $this->uploadDocuments($workCenter, $request);

        return redirect()->route('production.work-centers.index')->with('success', 'Centre de travail mis à jour.');
    }

    public function destroy(WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->delete();

        return back()->with('success', 'Centre de travail supprimé.');
    }

    private function machines()
    {
        return ProductionMachine::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'machine_id'             => ['nullable', 'integer', 'exists:production_machines,id'],
            'code'                   => ['required', 'string', 'max:30'],
            'name'                   => ['required', 'string', 'max:120'],
            'type'                   => ['nullable', 'string', 'max:20'],
            'atelier'                => ['nullable', 'string', 'max:60'],
            'site'                   => ['nullable', 'string', 'max:20'],
            'capacity_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'cost_per_hour'          => ['nullable', 'numeric', 'min:0'],
            'efficiency_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'              => ['boolean'],
            'documents'              => ['nullable', 'array'],
            'documents.*'            => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
            // [Maquette Poste de charge] général
            'category'               => ['nullable', 'string', 'max:40'],
            'location'               => ['nullable', 'string', 'max:100'],
            'production_line_id'     => ['nullable', 'integer', 'exists:production_lines,id'],
            'poste_group'            => ['nullable', 'string', 'max:60'],
            'responsible_id'         => ['nullable', 'integer', 'exists:users,id'],
            'depot_production_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
            'priorite'               => ['nullable', 'string', 'max:15'],
            'work_calendar'          => ['nullable', 'string', 'max:30'],
            'similar_work_center_id' => ['nullable', 'integer', 'exists:work_centers,id'],
            // capacité + temps
            'nominal_capacity'       => ['nullable', 'numeric', 'min:0'],
            'capacity_unit'          => ['nullable', 'string', 'max:15'],
            'theoretical_capacity'   => ['nullable', 'numeric', 'min:0'],
            'theoretical_capacity_unit' => ['nullable', 'string', 'max:15'],
            'utilization_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trs_standard'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cycle_time'             => ['nullable', 'numeric', 'min:0'],
            'cycle_time_unit'        => ['nullable', 'string', 'max:15'],
            'setup_time_min'         => ['nullable', 'numeric', 'min:0'],
            'adjustment_time_min'    => ['nullable', 'numeric', 'min:0'],
            'transfer_time_min'      => ['nullable', 'numeric', 'min:0'],
            // organisation + contrôle + identification
            'operators_count'        => ['nullable', 'integer', 'min:0', 'max:1000'],
            'default_team'           => ['nullable', 'string', 'max:30'],
            'operating_mode'         => ['nullable', 'string', 'max:20'],
            'quality_control_point'  => ['nullable', 'string', 'max:60'],
            'control_frequency'      => ['nullable', 'string', 'max:30'],
            'documentation_ref'      => ['nullable', 'string', 'max:60'],
            'criticality'            => ['nullable', 'string', 'max:15'],
            'barcode'                => ['nullable', 'string', 'max:60'],
            'serial_number'          => ['nullable', 'string', 'max:60'],
        ]);

        // [FIX null-vs-défaut] Champ numérique vide = valeur par défaut (colonnes NOT NULL) —
        // un null explicite provoquait « Column cannot be null » (500). Même classe que BOM.
        foreach (['capacity_hours_per_day' => 8, 'cost_per_hour' => 0, 'efficiency_rate' => 100] as $key => $default) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = $default;
            }
        }

        return $data + [
            'is_active'            => $request->boolean('is_active'),
            'parallel_work'        => $request->boolean('parallel_work'),
            'include_in_capacity'  => $request->boolean('include_in_capacity'),
            'allow_overload'       => $request->boolean('allow_overload'),
            'scrap_management'     => $request->boolean('scrap_management'),
            'require_time_entry'   => $request->boolean('require_time_entry'),
            'auto_cost_allocation' => $request->boolean('auto_cost_allocation'),
            'required_on_of'       => $request->boolean('required_on_of'),
        ];
    }
}
