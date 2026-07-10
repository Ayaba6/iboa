<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(Request $request)
    {
        $units = Unit::when($request->search, fn($q) => $q->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('abbreviation', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('units.index', compact('units'));
    }

    public function create()
    {
        $parentUnits = Unit::whereNull('parent_unit_id')->orderBy('name')->get(['id', 'name', 'abbreviation']);

        return view('units.create', compact('parentUnits'));
    }

    public function store(StoreUnitRequest $request)
    {
        Unit::create($this->prepare($request));

        return redirect()->route('units.index')->with('success', 'Unité de mesure créée avec succès.');
    }

    public function edit(Unit $unit)
    {
        $parentUnits = Unit::whereNull('parent_unit_id')->where('id', '!=', $unit->id)
            ->orderBy('name')->get(['id', 'name', 'abbreviation']);
        // [Maquette Unité] conversions = unités rattachées à celle-ci
        $conversions = $unit->childUnits()->orderBy('name')->get();

        return view('units.edit', compact('unit', 'parentUnits', 'conversions'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit->update($this->prepare($request));

        return redirect()->route('units.index')->with('success', 'Unité de mesure mise à jour.');
    }

    // [Maquette Unité] normalisation commune store/update
    private function prepare(StoreUnitRequest|UpdateUnitRequest $request): array
    {
        $data = $request->validated();
        $data['is_active']            = $request->boolean('is_active', true);
        $data['is_default_inventory'] = $request->boolean('is_default_inventory');
        $data['is_default_sales']     = $request->boolean('is_default_sales');
        $data['decimal_places']       = $data['decimal_places'] ?? 2;
        $data['conversion_factor']    = $data['conversion_factor'] ?? 1;
        $data['rounding_mode']        = $data['rounding_mode'] ?? 'mathematique';

        return $data;
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des articles utilisent cette unité.');
        }
        $unit->delete();
        return redirect()->route('units.index')->with('success', 'Unité supprimée.');
    }

    /**
     * Quick-create via AJAX — retourne JSON {id, name, abbreviation}.
     */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100|unique:units,name',
            'abbreviation' => 'required|string|max:20|unique:units,abbreviation',
            'type'         => 'nullable|in:quantite,poids,volume,longueur,surface,temps,autre',
        ]);
        $data['is_active']      = true;
        $data['decimal_places'] = 2;

        $unit = Unit::create($data);

        return response()->json([
            'id'           => $unit->id,
            'name'         => $unit->name,
            'abbreviation' => $unit->abbreviation,
            'label'        => $unit->name . ' (' . $unit->abbreviation . ')',
        ], 201);
    }
}
