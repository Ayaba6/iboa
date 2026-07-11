<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionMachineController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct()
    {
        $this->middleware('permission:production.create');
        
    }

    public function index(Request $request): View
    {
        $machines = ProductionMachine::with('createdBy')
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('production.machines.index', compact('machines'));
    }

    public function create(): View { return view('production.machines.form', $this->formData(new ProductionMachine())); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        unset($data['documents']);
        $data['company_id'] = currentCompany()->id;
        $machine = ProductionMachine::create($data);
        $this->uploadDocuments($machine, $request);
        return redirect()->route('production.machines.index')->with('success', 'Machine créée.');
    }

    public function edit(ProductionMachine $machine): View
    {
        $machine->load('attachments');
        return view('production.machines.form', $this->formData($machine));
    }

    private function formData(ProductionMachine $machine): array
    {
        return [
            'machine'     => $machine,
            'lines'       => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'code', 'name']),
            'units'       => \App\Models\Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'users'       => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'workCenters' => \App\Modules\Production\Models\WorkCenter::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    public function update(Request $request, ProductionMachine $machine): RedirectResponse
    {
        $data = $this->validateData($request, $machine->id);
        unset($data['documents']);
        $machine->update($data);
        $this->uploadDocuments($machine, $request);
        return redirect()->route('production.machines.index')->with('success', 'Machine mise à jour.');
    }

    public function destroy(ProductionMachine $machine): RedirectResponse
    {
        $machine->delete();
        return back()->with('success', 'Machine supprimée.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'name'        => ['required', 'string', 'max:120'],
            'type'        => ['required', 'in:decoupe,profilage,mixte'],
            'manufacturer'    => ['nullable', 'string', 'max:80'],
            'model'           => ['nullable', 'string', 'max:80'],
            'serial_number'   => ['nullable', 'string', 'max:80'],
            'site'            => ['nullable', 'string', 'max:20'],
            'atelier'         => ['nullable', 'string', 'max:60'],
            'commissioned_at' => ['nullable', 'date'],
            'power_kw'        => ['nullable', 'numeric', 'min:0'],
            'documents'       => ['nullable', 'array'],
            'documents.*'     => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
            'hourly_cost' => ['nullable', 'integer', 'min:0'],
            'energy_cost_per_hour'      => ['nullable', 'integer', 'min:0'],
            'maintenance_cost_per_hour' => ['nullable', 'integer', 'min:0'],
            'maintenance_frequency_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'status'      => ['required', 'in:active,maintenance,arret'],
            'is_active'   => ['boolean'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            // [Maquette Machine] général
            'category'            => ['nullable', 'string', 'max:40'],
            'production_line_id'  => ['nullable', 'integer', 'exists:production_lines,id'],
            'location'            => ['nullable', 'string', 'max:100'],
            'country_origin'      => ['nullable', 'string', 'max:40'],
            'brand'               => ['nullable', 'string', 'max:60'],
            'nominal_capacity'    => ['nullable', 'numeric', 'min:0'],
            'capacity_unit'       => ['nullable', 'string', 'max:15'],
            'unit_id'             => ['nullable', 'integer', 'exists:units,id'],
            'responsible_id'      => ['nullable', 'integer', 'exists:users,id'],
            'power_supply'        => ['nullable', 'string', 'max:60'],
            'acquisition_cost'    => ['nullable', 'integer', 'min:0'],
            'weight_kg'           => ['nullable', 'numeric', 'min:0'],
            // caractéristiques techniques
            'length_mm'           => ['nullable', 'numeric', 'min:0'],
            'width_mm'            => ['nullable', 'numeric', 'min:0'],
            'height_mm'           => ['nullable', 'numeric', 'min:0'],
            'footprint_m3'        => ['nullable', 'numeric', 'min:0'],
            'max_speed'           => ['nullable', 'numeric', 'min:0'],
            'nominal_speed'       => ['nullable', 'numeric', 'min:0'],
            'useful_width_mm'     => ['nullable', 'numeric', 'min:0'],
            'thickness_min'       => ['nullable', 'numeric', 'min:0'],
            'thickness_max'       => ['nullable', 'numeric', 'min:0'],
            'waves_count'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'shaft_diameter_mm'   => ['nullable', 'numeric', 'min:0'],
            'motor_type'          => ['nullable', 'string', 'max:30'],
            'reducer'             => ['nullable', 'string', 'max:60'],
            'cutting_system'      => ['nullable', 'string', 'max:30'],
            'power_kva'           => ['nullable', 'numeric', 'min:0'],
            'air_pressure_bar'    => ['nullable', 'numeric', 'min:0'],
            'hydraulic_pressure_bar' => ['nullable', 'numeric', 'min:0'],
            'temp_min'            => ['nullable', 'numeric', 'min:-50', 'max:100'],
            'temp_max'            => ['nullable', 'numeric', 'min:-50', 'max:100'],
            'humidity_max'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'environment'         => ['nullable', 'string', 'max:20'],
            // disponibilités et affectation
            'work_calendar'       => ['nullable', 'string', 'max:30'],
            'work_center_id'      => ['nullable', 'integer', 'exists:work_centers,id'],
            'default_team'        => ['nullable', 'string', 'max:30'],
            'priorite'            => ['nullable', 'string', 'max:15'],
        ]);

        // [FIX null-vs-défaut] Champ numérique vide = valeur par défaut (colonnes NOT NULL) —
        // un null explicite provoquait « Column 'hourly_cost' cannot be null » (500).
        // Même classe que Nomenclature / Gammes / Centres de travail.
        foreach (['hourly_cost' => 0, 'energy_cost_per_hour' => 0, 'maintenance_cost_per_hour' => 0] as $key => $default) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = $default;
            }
        }

        return $data + [
            'is_active'            => $request->boolean('is_active'),
            'integrated_decoiler'  => $request->boolean('integrated_decoiler'),
            'assigned_to_atelier'  => $request->boolean('assigned_to_atelier'),
            'assigned_to_line'     => $request->boolean('assigned_to_line'),
        ];
    }
}
