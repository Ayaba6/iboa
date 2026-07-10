<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Production\Models\BillOfMaterial;
use Illuminate\View\View;

/**
 * [BUG-002] Rapport : articles fabriqués (is_manufacturable / production_mode MTO)
 * ne disposant d'aucune nomenclature active — donc non lançables en production.
 */
class MissingBomController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.report.view');
    }

    public function index(): View
    {
        $withBom = BillOfMaterial::where('is_active', true)->pluck('product_id')->filter()->unique();

        $products = Product::where(fn ($q) => $q->where('is_manufacturable', true)->orWhere('production_mode', 'mto'))
            ->whereNotIn('id', $withBom)
            ->with('family')
            ->orderBy('name')
            ->paginate(30);

        return view('production.reports.missing-bom', compact('products'));
    }
}
