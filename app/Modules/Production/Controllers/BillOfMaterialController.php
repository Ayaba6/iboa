<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillOfMaterialController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.create');
        
    }

    public function index(Request $request): View
    {
        $boms = BillOfMaterial::with('product')->withCount('lines')
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) =>
                $w->where('name', 'like', "%$v%")
                  ->orWhere('sheet_type', 'like', "%$v%")
                  ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$v%")->orWhere('reference', 'like', "%$v%"))))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('production.bom.index', compact('boms'));
    }

    public function create(): View
    {
        return view('production.bom.form', $this->formData(new BillOfMaterial()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $bom = BillOfMaterial::create($data + ['company_id' => currentCompany()->id]);
            $this->syncLines($bom, $request);
        });

        return redirect()->route('production.bom.index')->with('success', 'Nomenclature créée.');
    }

    public function show(BillOfMaterial $bom): View
    {
        $bom->load(['product', 'lines.product', 'lines.unit']);
        $explosion = app(\App\Modules\Production\Services\BomExplosionService::class)->explode($bom, 1);

        return view('production.bom.show', compact('bom', 'explosion'));
    }

    public function edit(BillOfMaterial $bom): View
    {
        $bom->load('lines');

        return view('production.bom.form', $this->formData($bom));
    }

    public function update(Request $request, BillOfMaterial $bom): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($bom, $data, $request) {
            $bom->update($data);
            $bom->lines()->delete();
            $this->syncLines($bom, $request);
        });

        return redirect()->route('production.bom.index')->with('success', 'Nomenclature mise à jour.');
    }

    public function destroy(BillOfMaterial $bom): RedirectResponse
    {
        $bom->delete();

        return back()->with('success', 'Nomenclature supprimée.');
    }

    private function syncLines(BillOfMaterial $bom, Request $request): void
    {
        foreach ((array) $request->input('lines', []) as $i => $row) {
            if (empty($row['label']) && empty($row['product_id'])) {
                continue;
            }
            $bom->lines()->create([
                'product_id'            => $row['product_id'] ?? null,
                'substitute_product_id' => ($row['substitute_product_id'] ?? '') ?: null,
                'substitute_note'       => $row['substitute_note'] ?? null,
                'label'              => $row['label'] ?? null,
                'quantity_per_meter' => $row['quantity_per_meter'] ?? 0,
                'unit_id'            => $row['unit_id'] ?? null,
                'waste_rate'         => $row['waste_rate'] ?? 0,
                'sort_order'         => $i,
                // [SAGE parité]
                'sequence'           => $row['sequence'] ?? (($i + 1) * 10),
                'groupe'             => $row['groupe'] ?? null,
                'type_composant'     => $row['type_composant'] ?? null,
                'coef'               => $row['coef'] ?? 1,
                'depot_sortie_id'    => $row['depot_sortie_id'] ?? null,
                'lot_obligatoire'    => ! empty($row['lot_obligatoire']),
                'statut'             => $row['statut'] ?? 'actif',
            ]);
        }
    }

    private function formData(BillOfMaterial $bom): array
    {
        return [
            'bom'        => $bom,
            'products'   => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'units'      => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'product_id'            => ['nullable', 'integer', 'exists:products,id'],
            'name'                  => ['required', 'string', 'max:150'],
            'sheet_type'            => ['nullable', 'string', 'max:60'],
            'thickness'             => ['nullable', 'numeric', 'min:0'],
            'coil_width'            => ['nullable', 'numeric', 'min:0'],
            'usable_width'          => ['nullable', 'numeric', 'min:0'],
            'standard_waste_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consumption_per_meter' => ['nullable', 'numeric', 'min:0'],
            'machine_time_per_unit' => ['nullable', 'numeric', 'min:0'],
            'labor_per_unit'        => ['nullable', 'numeric', 'min:0'],
            'packaging_per_unit'    => ['nullable', 'numeric', 'min:0'],
            'std_material_cost'     => ['nullable', 'integer', 'min:0'],
            'std_labor_cost'        => ['nullable', 'integer', 'min:0'],
            'std_machine_cost'      => ['nullable', 'integer', 'min:0'],
            'std_energy_cost'       => ['nullable', 'integer', 'min:0'],
            'std_maintenance_cost'  => ['nullable', 'integer', 'min:0'],
            'std_packaging_cost'    => ['nullable', 'integer', 'min:0'],
            'std_overhead_cost'     => ['nullable', 'integer', 'min:0'],
            'is_active'             => ['boolean'],
            // [SAGE parité] en-tête article composé
            'site'                  => ['nullable', 'string', 'max:20'],
            'alternative'           => ['nullable', 'string', 'max:5'],
            'date_reference'        => ['nullable', 'date'],
            'version_majeure'       => ['nullable', 'string', 'max:5'],
            'version_mineure'       => ['nullable', 'string', 'max:5'],
            'unite_gestion_id'      => ['nullable', 'integer', 'exists:units,id'],
            'quantite_base'         => ['nullable', 'numeric', 'min:0'],
            'statut'                => ['nullable', 'string', 'max:20'],
            'date_debut_validite'   => ['nullable', 'date'],
            'date_fin_validite'     => ['nullable', 'date'],
            'rendement_standard'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'controle_qualite'      => ['boolean'],
            // [Maquette Nomenclature]
            'code'                  => ['nullable', 'string', 'max:30'],
            'type_nomenclature'     => ['nullable', 'string', 'max:30'],
            'depot_production_id'   => ['nullable', 'integer', 'exists:warehouses,id'],
            'valuation_method'      => ['nullable', 'string', 'max:20'],
            'priorite'              => ['nullable', 'string', 'max:15'],
        ]);

        // [FIX BOM] Les champs numériques laissés VIDES par l'utilisateur (les placeholders
        // « 3,00 », « 97,00 »… ne sont que des exemples) arrivent ici en null. Or ces
        // colonnes sont NOT NULL en base : un null explicite provoque « Column cannot
        // be null » (500 brut). Un champ vide vaut sa valeur PAR DÉFAUT — identique en
        // création comme en modification.
        $defaults = [
            'standard_waste_rate' => 0, 'consumption_per_meter' => 0, 'machine_time_per_unit' => 0,
            'labor_per_unit' => 0, 'packaging_per_unit' => 0,
            'std_material_cost' => 0, 'std_labor_cost' => 0, 'std_machine_cost' => 0,
            'std_energy_cost' => 0, 'std_maintenance_cost' => 0, 'std_packaging_cost' => 0,
            'std_overhead_cost' => 0,
            'quantite_base' => 1, 'statut' => 'exploitation',
        ];
        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = $default;
            }
        }

        return $data + [
            'is_active'         => $request->boolean('is_active'),
            'controle_qualite'  => $request->boolean('controle_qualite'),
            'version_active'    => $request->boolean('version_active'),
            'multi_niveaux'     => $request->boolean('multi_niveaux'),
            'allow_sub_bom'     => $request->boolean('allow_sub_bom'),
            'lot_management'    => $request->boolean('lot_management'),
            'serial_tracking'   => $request->boolean('serial_tracking'),
            'lock_modification' => $request->boolean('lock_modification'),
        ];
    }
}
