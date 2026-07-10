<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionLineController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.create');
        
    }

    public function index(): View
    {
        $lines = ProductionLine::with(['machine', 'createdBy'])->orderBy('name')->paginate(25);
        return view('production.lines.index', compact('lines'));
    }

    public function create(): View
    {
        return view('production.lines.form', $this->formData(new ProductionLine()));
    }

    private function formData(ProductionLine $line): array
    {
        return [
            'line'       => $line,
            'machines'   => ProductionMachine::where('is_active', true)->orderBy('name')->get(),
            'products'   => \App\Models\Product::orderBy('name')->get(['id', 'name', 'reference']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'users'      => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        ProductionLine::create($data);
        return redirect()->route('production.lines.index')->with('success', 'Ligne créée.');
    }

    public function edit(ProductionLine $line): View
    {
        return view('production.lines.form', $this->formData($line));
    }

    public function update(Request $request, ProductionLine $line): RedirectResponse
    {
        $line->update($this->validateData($request));
        return redirect()->route('production.lines.index')->with('success', 'Ligne mise à jour.');
    }

    public function destroy(ProductionLine $line): RedirectResponse
    {
        $line->delete();
        return back()->with('success', 'Ligne supprimée.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id' => ['nullable', 'integer', 'exists:production_machines,id'],
            'code'       => ['required', 'string', 'max:30'],
            'name'       => ['required', 'string', 'max:120'],
            'is_active'  => ['boolean'],
            // [Maquette Ligne] général
            'type_ligne'          => ['nullable', 'string', 'max:30'],
            'product_id'          => ['nullable', 'integer', 'exists:products,id'],
            'depot_production_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'atelier'             => ['nullable', 'string', 'max:60'],
            'location'            => ['nullable', 'string', 'max:100'],
            'line_group'          => ['nullable', 'string', 'max:60'],
            'site'                => ['nullable', 'string', 'max:20'],
            'nominal_capacity'    => ['nullable', 'numeric', 'min:0'],
            'capacity_unit'       => ['nullable', 'string', 'max:15'],
            'work_calendar'       => ['nullable', 'string', 'max:30'],
            'commissioned_at'     => ['nullable', 'date'],
            'responsible_id'      => ['nullable', 'integer', 'exists:users,id'],
            'status'              => ['nullable', 'string', 'max:20'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            // capacité et performances
            'theoretical_capacity'      => ['nullable', 'numeric', 'min:0'],
            'theoretical_capacity_unit' => ['nullable', 'string', 'max:15'],
            'practical_capacity'        => ['nullable', 'numeric', 'min:0'],
            'practical_capacity_unit'   => ['nullable', 'string', 'max:15'],
            'trs_target'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cycle_time'                => ['nullable', 'numeric', 'min:0'],
            'cycle_time_unit'           => ['nullable', 'string', 'max:15'],
            // organisation + plages
            'teams_count'         => ['nullable', 'integer', 'min:0', 'max:10'],
            'default_team'        => ['nullable', 'string', 'max:30'],
            'operators_per_team'  => ['nullable', 'integer', 'min:0', 'max:1000'],
            'start_time'          => ['nullable', 'date_format:H:i'],
            'end_time'            => ['nullable', 'date_format:H:i'],
            'break1_start'        => ['nullable', 'date_format:H:i'],
            'break1_end'          => ['nullable', 'date_format:H:i'],
            'break2_start'        => ['nullable', 'date_format:H:i'],
            'break2_end'          => ['nullable', 'date_format:H:i'],
            // contrôle + identification
            'quality_control_point' => ['nullable', 'string', 'max:60'],
            'control_frequency'     => ['nullable', 'string', 'max:30'],
            'barcode'               => ['nullable', 'string', 'max:60'],
            'serial_number'         => ['nullable', 'string', 'max:60'],
            'priorite'              => ['nullable', 'string', 'max:15'],
        ]) + [
            'is_active'                => $request->boolean('is_active'),
            'continuous_work'          => $request->boolean('continuous_work'),
            'require_production_entry' => $request->boolean('require_production_entry'),
            'allow_of_start'           => $request->boolean('allow_of_start'),
            'allow_interline'          => $request->boolean('allow_interline'),
            'scrap_management'         => $request->boolean('scrap_management'),
            'auto_cost_allocation'     => $request->boolean('auto_cost_allocation'),
            'block_if_unavailable'     => $request->boolean('block_if_unavailable'),
            'track_stoppages'          => $request->boolean('track_stoppages'),
        ];
    }
}
