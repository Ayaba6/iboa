<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SalesInsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [VEN Marge] Rapport de marge brute ventilée par commercial et par site/dépôt.
 */
class SalesMarginController extends Controller
{
    public function __construct(private SalesInsightsService $insights) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $months = (int) $request->integer('mois', 12);
        $months = max(1, min(36, $months));

        $bySalesRep = $this->insights->marginBySalesRep($months);
        $bySite     = $this->insights->marginBySite($months);
        $global     = $this->insights->grossMargin();

        return view('ventes.marges', compact('bySalesRep', 'bySite', 'global', 'months'));
    }
}
