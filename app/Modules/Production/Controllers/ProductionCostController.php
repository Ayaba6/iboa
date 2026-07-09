<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductionCostController extends Controller
{
    public function __construct(private ProductionCostService $costs)
    {
        $this->middleware('permission:production.update');
    }

    /** Recalcule et persiste le coût de revient de l'OF. */
    public function compute(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'labor_cost'       => ['nullable', 'integer', 'min:0'],
            'machine_cost'     => ['nullable', 'integer', 'min:0'],
            'energy_cost'      => ['nullable', 'integer', 'min:0'],
            'maintenance_cost' => ['nullable', 'integer', 'min:0'],
            'packaging_cost'   => ['nullable', 'integer', 'min:0'],
            'overhead_cost'    => ['nullable', 'integer', 'min:0'],
            'overhead_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue'          => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->costs->compute($order, $data);

        return back()->with('success', 'Coût de revient calculé.');
    }
}
