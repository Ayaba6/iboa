<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoutingController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct()
    {
        $this->middleware('permission:production.create');
        
    }

    public function index(): View
    {
        $routings = Routing::with('billOfMaterial')->withCount('operations')->orderBy('name')->paginate(25);

        return view('production.routings.index', compact('routings'));
    }

    public function create(): View
    {
        return view('production.routings.form', $this->formData(new Routing()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $routing = DB::transaction(function () use ($data, $request) {
            $routing = Routing::create($data + ['company_id' => currentCompany()->id]);
            $this->syncOperations($routing, $request);
            return $routing;
        });
        $this->uploadDocuments($routing, $request);

        return redirect()->route('production.routings.index')->with('success', 'Gamme créée.');
    }

    public function edit(Routing $routing): View
    {
        $routing->load(['operations', 'attachments']);

        return view('production.routings.form', $this->formData($routing));
    }

    public function update(Request $request, Routing $routing): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($routing, $data, $request) {
            $routing->update($data);
            $routing->operations()->delete();
            $this->syncOperations($routing, $request);
        });
        $this->uploadDocuments($routing, $request);

        return redirect()->route('production.routings.index')->with('success', 'Gamme mise à jour.');
    }

    public function destroy(Routing $routing): RedirectResponse
    {
        $routing->delete();

        return back()->with('success', 'Gamme supprimée.');
    }

    private function syncOperations(Routing $routing, Request $request): void
    {
        foreach ((array) $request->input('operations', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $routing->operations()->create([
                'work_center_id'       => $row['work_center_id'] ?? null,
                'sequence'             => $row['sequence'] ?? (($i + 1) * 10),
                'name'                 => $row['name'],
                'setup_minutes'        => $row['setup_minutes'] ?? 0,
                'run_minutes_per_unit' => $row['run_minutes_per_unit'] ?? 0,
                // [SAGE parité]
                'operation_number'     => $row['operation_number'] ?? (($i + 1) * 10),
                'labor_minutes'        => $row['labor_minutes'] ?? 0,
                'quantite_base'        => $row['quantite_base'] ?? 1,
                'uo'                   => $row['uo'] ?? null,
                'rendement'            => $row['rendement'] ?? null,
                'controle_qualite'     => ! empty($row['controle_qualite']),
                'sous_traitance'       => ! empty($row['sous_traitance']),
                'statut'               => $row['statut'] ?? 'elaboration',
                // [Maquette Gamme]
                'code'                 => $row['code'] ?? null,
                'type_operation'       => $row['type_operation'] ?? null,
                'waiting_minutes'      => $row['waiting_minutes'] ?? 0,
                'point_controle'       => $row['point_controle'] ?? null,
                'is_critical'          => ! empty($row['is_critical']),
            ]);
        }
    }

    private function formData(Routing $routing): array
    {
        return [
            'routing'    => $routing,
            'boms'       => BillOfMaterial::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'centers'    => WorkCenter::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'capacity_hours_per_day', 'efficiency_rate', 'is_active']),
            'products'   => \App\Models\Product::orderBy('name')->get(['id', 'name', 'reference']),
            'units'      => \App\Models\Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'bill_of_material_id'            => ['nullable', 'integer', 'exists:bills_of_materials,id'],
            'product_id'                     => ['nullable', 'integer', 'exists:products,id'],
            'code'                           => ['required', 'string', 'max:30'],
            'name'                           => ['required', 'string', 'max:150'],
            'is_active'                      => ['boolean'],
            // [SAGE parité] en-tête
            'site'                           => ['nullable', 'string', 'max:20'],
            'alternative'                    => ['nullable', 'string', 'max:5'],
            'version_majeure'                => ['nullable', 'string', 'max:5'],
            'version_mineure'                => ['nullable', 'string', 'max:5'],
            'date_reference'                 => ['nullable', 'date'],
            'statut'                         => ['nullable', 'string', 'max:20'],
            'date_debut_validite'            => ['nullable', 'date'],
            'date_fin_validite'              => ['nullable', 'date'],
            'unite_temps'                    => ['nullable', 'string', 'max:20'],
            'quantite_base'                  => ['nullable', 'numeric', 'min:0'],
            'uo'                             => ['nullable', 'string', 'max:10'],
            'rendement_standard'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'controle_qualite'               => ['boolean'],
            // [Maquette Gamme] entête + propriétés
            'type_gamme'                     => ['nullable', 'string', 'max:30'],
            'methode_suivi'                  => ['nullable', 'string', 'max:30'],
            'unite_gestion_id'               => ['nullable', 'integer', 'exists:units,id'],
            'depot_production_id'            => ['nullable', 'integer', 'exists:warehouses,id'],
            'tolerance_rendement'            => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'operations'                     => ['nullable', 'array'],
            'operations.*.name'              => ['nullable', 'string', 'max:150'],
            'operations.*.work_center_id'    => ['nullable', 'integer', 'exists:work_centers,id'],
            'operations.*.sequence'          => ['nullable', 'integer', 'min:0'],
            'operations.*.operation_number'  => ['nullable', 'integer', 'min:0'],
            'operations.*.setup_minutes'     => ['nullable', 'numeric', 'min:0'],
            'operations.*.run_minutes_per_unit' => ['nullable', 'numeric', 'min:0'],
            'operations.*.labor_minutes'     => ['nullable', 'numeric', 'min:0'],
            'operations.*.quantite_base'     => ['nullable', 'numeric', 'min:0'],
            'operations.*.uo'                => ['nullable', 'string', 'max:10'],
            'operations.*.rendement'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'operations.*.controle_qualite'  => ['nullable', 'boolean'],
            'operations.*.sous_traitance'    => ['nullable', 'boolean'],
            'operations.*.statut'            => ['nullable', 'string', 'max:20'],
            'operations.*.code'              => ['nullable', 'string', 'max:20'],
            'operations.*.type_operation'    => ['nullable', 'string', 'max:30'],
            'operations.*.waiting_minutes'   => ['nullable', 'numeric', 'min:0'],
            'operations.*.point_controle'    => ['nullable', 'string', 'max:20'],
            'operations.*.is_critical'       => ['nullable', 'boolean'],
            'documents'                      => ['nullable', 'array'],
            'documents.*'                    => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        // [FIX null-vs-défaut] Champ numérique vide = valeur par défaut (colonne NOT NULL) —
        // un null explicite provoquait « Column cannot be null » (500). Même classe que BOM.
        if (array_key_exists('quantite_base', $data) && $data['quantite_base'] === null) {
            $data['quantite_base'] = 1;
        }

        return $data + [
            'is_active'             => $request->boolean('is_active'),
            'controle_qualite'      => $request->boolean('controle_qualite'),
            'version_active'        => $request->boolean('version_active'),
            'allow_time_overrun'    => $request->boolean('allow_time_overrun'),
            'gestion_rebuts'        => $request->boolean('gestion_rebuts'),
            'auto_transfer'         => $request->boolean('auto_transfer'),
            'block_on_control_fail' => $request->boolean('block_on_control_fail'),
        ];
    }
}
