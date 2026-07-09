<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseLocationController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $locations = $warehouse->locations()
            ->withCount('productStocks')
            ->orderBy('zone')
            ->orderBy('aisle')
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('warehouses.locations.index', compact('warehouse', 'locations'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('warehouses.locations.create', compact('warehouse'));
    }

    public function store(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'name'        => ['required', 'string', 'max:100'],
            'zone'        => ['nullable', 'string', 'max:50'],
            'aisle'       => ['nullable', 'string', 'max:20'],
            'rack'        => ['nullable', 'string', 'max:20'],
            'level'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['warehouse_id'] = $warehouse->id;
        $data['is_active'] = $request->boolean('is_active', true);

        $location = WarehouseLocation::create($data);

        return redirect()
            ->route('stocks.warehouses.locations.index', $warehouse)
            ->with('success', "Emplacement « {$location->name} » créé.");
    }

    public function edit(Warehouse $warehouse, WarehouseLocation $location): View
    {
        return view('warehouses.locations.edit', compact('warehouse', 'location'));
    }

    public function update(Request $request, Warehouse $warehouse, WarehouseLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'name'        => ['required', 'string', 'max:100'],
            'zone'        => ['nullable', 'string', 'max:50'],
            'aisle'       => ['nullable', 'string', 'max:20'],
            'rack'        => ['nullable', 'string', 'max:20'],
            'level'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $location->update($data);

        return redirect()
            ->route('stocks.warehouses.locations.index', $warehouse)
            ->with('success', "Emplacement « {$location->name} » mis à jour.");
    }

    public function destroy(Warehouse $warehouse, WarehouseLocation $location): RedirectResponse
    {
        if ($location->productStocks()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'Impossible de supprimer un emplacement contenant du stock.');
        }

        $location->delete();

        return redirect()
            ->route('stocks.warehouses.locations.index', $warehouse)
            ->with('success', 'Emplacement supprimé.');
    }
}
