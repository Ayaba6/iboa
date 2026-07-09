<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\Coil;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoilController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.create|stocks.adjust')->only(['index', 'show']);
        $this->middleware('permission:production.create')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $coils = Coil::with(['product', 'supplier'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) =>
                $w->where('reference', 'like', "%$v%")->orWhere('lot_number', 'like', "%$v%")->orWhere('color', 'like', "%$v%")))
            ->orderByDesc('received_at')->paginate(25)->withQueryString();

        $stats = [
            'total'       => Coil::count(),
            'disponible'  => Coil::where('status', 'disponible')->count(),
            'poids_dispo' => (float) Coil::where('status', '!=', 'epuisee')->sum('remaining_weight'),
            'valeur'      => (float) Coil::where('status', '!=', 'epuisee')
                                ->selectRaw('COALESCE(SUM(remaining_weight * cost_per_kg),0) v')->value('v'),
        ];

        return view('production.coils.index', compact('coils', 'stats'));
    }

    public function create(): View
    {
        return view('production.coils.form', $this->formData(new Coil()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id']       = currentCompany()->id;
        $data['remaining_weight'] = $data['initial_weight'];
        $data['cost_per_kg']      = $data['initial_weight'] > 0 ? round($data['purchase_price'] / $data['initial_weight'], 2) : 0;
        $data['status']           = 'disponible';
        Coil::create($data);

        return redirect()->route('production.coils.index')->with('success', 'Bobine réceptionnée.');
    }

    public function show(Coil $coil): View
    {
        $coil->load(['product', 'supplier', 'consumptions.productionOrder']);

        return view('production.coils.show', compact('coil'));
    }

    public function edit(Coil $coil): View
    {
        return view('production.coils.form', $this->formData($coil));
    }

    public function update(Request $request, Coil $coil): RedirectResponse
    {
        $data = $this->validateData($request, $coil->id);
        $consumed = $coil->initial_weight - $coil->remaining_weight;
        $data['remaining_weight'] = max(0, $data['initial_weight'] - $consumed);
        $data['cost_per_kg']      = $data['initial_weight'] > 0 ? round($data['purchase_price'] / $data['initial_weight'], 2) : 0;
        $coil->update($data);

        return redirect()->route('production.coils.index')->with('success', 'Bobine mise à jour.');
    }

    public function destroy(Coil $coil): RedirectResponse
    {
        if ($coil->consumptions()->exists()) {
            return back()->with('error', 'Bobine déjà consommée — suppression interdite.');
        }
        $coil->delete();

        return back()->with('success', 'Bobine supprimée.');
    }

    private function formData(Coil $coil): array
    {
        return [
            'coil'       => $coil,
            'products'   => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'suppliers'  => Supplier::orderBy('name')->get(['id', 'name']),
            'warehouses' => \App\Models\Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        // [CDC §13.5] Contrôles obligatoires à la réception bobine :
        // poids, largeur, épaisseur, couleur, lot, fournisseur.
        $data = $request->validate([
            'product_id'       => ['nullable', 'integer', 'exists:products,id'],
            'supplier_id'      => ['required', 'integer', 'exists:suppliers,id'],
            'reference'        => ['required', 'string', 'max:60'],
            'lot_number'       => ['required', 'string', 'max:60'],
            'color'            => ['required', 'string', 'max:60'],
            'thickness'        => ['required', 'numeric', 'min:0.01'],
            'width'            => ['required', 'numeric', 'min:0.01'],
            'initial_weight'   => ['required', 'numeric', 'min:0.01'],
            'estimated_length' => ['nullable', 'numeric', 'min:0'],
            'purchase_price'   => ['required', 'integer', 'min:0'],
            'received_at'      => ['nullable', 'date'],
            // [Maquette Bobine] réception + caractéristiques + gestion
            'supplier_reference'   => ['nullable', 'string', 'max:60'],
            'warehouse_id'         => ['nullable', 'integer', 'exists:warehouses,id'],
            'site'                 => ['nullable', 'string', 'max:20'],
            'bl_number'            => ['nullable', 'string', 'max:60'],
            'origine'              => ['nullable', 'string', 'max:30'],
            'devise'               => ['nullable', 'string', 'max:10'],
            'nuance'               => ['nullable', 'string', 'max:30'],
            'gross_weight'         => ['nullable', 'numeric', 'min:0'],
            'inner_diameter'       => ['nullable', 'numeric', 'min:0'],
            'outer_diameter'       => ['nullable', 'numeric', 'min:0'],
            'coating'              => ['nullable', 'string', 'max:30'],
            'surface_finish'       => ['nullable', 'string', 'max:30'],
            'tolerance_thickness'  => ['nullable', 'numeric', 'min:0'],
            'barcode'              => ['nullable', 'string', 'max:60'],
            'brand'                => ['nullable', 'string', 'max:60'],
            'serial_number'        => ['nullable', 'string', 'max:60'],
            'valuation_method'     => ['nullable', 'string', 'max:20'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ]);

        // [FIX null-vs-défaut] estimated_length est NOT NULL en base : un champ laissé
        // vide (null explicite) provoquerait « Column cannot be null » (500).
        if (array_key_exists('estimated_length', $data) && $data['estimated_length'] === null) {
            $data['estimated_length'] = 0;
        }

        return $data + [
            'is_stock_managed'     => $request->boolean('is_stock_managed'),
            'lot_tracking'         => $request->boolean('lot_tracking'),
            'allow_negative_stock' => $request->boolean('allow_negative_stock'),
        ];
    }
}
