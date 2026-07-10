<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AnalyticLine;
use App\Models\CostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [§12 CDC] Comptabilité analytique — centres de coûts/profit.
 * Permet la ventilation des charges par axe métier (matière/MO/énergie/maintenance).
 */
class CostCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:analytic.view')->only(['index', 'show', 'report']);
        $this->middleware('permission:analytic.manage')->only(['create', 'store', 'edit', 'update', 'destroy', 'storeLine']);
    }

    public function index(): View
    {
        $company = currentCompany();
        $centers = CostCenter::withCount('analyticLines')
            ->withSum('analyticLines', 'amount')
            ->orderBy('code')
            ->paginate(25);

        return view('analytique.centres-couts.index', compact('centers'));
    }

    public function create(): View
    {
        $parents = CostCenter::orderBy('code')->get(['id', 'code', 'name']);
        return view('analytique.centres-couts.form', ['center' => new CostCenter(), 'parents' => $parents]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20'],
            'name'        => ['required', 'string', 'max:120'],
            'type'        => ['required', 'in:cost,profit,investment'],
            'parent_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ]);

        $data['company_id'] = currentCompany()->id;
        $center = CostCenter::create($data);

        if ($request->boolean('save_and_new')) {
            return redirect()->route('analytique.centres-couts.create')->with('success', "Centre {$center->code} créé — nouveau centre.");
        }

        return redirect()->route('analytique.centres-couts.show', $center)->with('success', "Centre {$center->code} créé.");
    }

    public function show(CostCenter $costCenter): View
    {
        $costCenter->load(['parent', 'children']);

        $lines = AnalyticLine::where('cost_center_id', $costCenter->id)
            ->orderByDesc('date')
            ->paginate(50);

        $byCategory = AnalyticLine::where('cost_center_id', $costCenter->id)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        return view('analytique.centres-couts.show', compact('costCenter', 'lines', 'byCategory'));
    }

    public function edit(CostCenter $costCenter): View
    {
        $parents = CostCenter::where('id', '!=', $costCenter->id)->orderBy('code')->get(['id', 'code', 'name']);
        return view('analytique.centres-couts.form', ['center' => $costCenter, 'parents' => $parents]);
    }

    public function update(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20'],
            'name'        => ['required', 'string', 'max:120'],
            'type'        => ['required', 'in:cost,profit,investment'],
            'parent_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ]);

        $costCenter->update($data);

        return redirect()->route('analytique.centres-couts.show', $costCenter)->with('success', 'Centre mis à jour.');
    }

    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        abort_if($costCenter->analyticLines()->exists(), 422, 'Impossible de supprimer un centre ayant des lignes analytiques.');
        $costCenter->delete();

        return redirect()->route('analytique.centres-couts.index')->with('success', 'Centre supprimé.');
    }

    /** Rapport de synthèse analytique — rentabilité par centre (§12 CDC). */
    public function report(Request $request): View
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', 0); // 0 = toute l'année

        $query = CostCenter::withSum(['analyticLines as total_charges' => function ($q) use ($year, $month) {
            $q->where('amount', '>', 0)->whereYear('date', $year);
            if ($month > 0) { $q->whereMonth('date', $month); }
        }], 'amount')
        ->withSum(['analyticLines as total_produits' => function ($q) use ($year, $month) {
            $q->where('amount', '<', 0)->whereYear('date', $year);
            if ($month > 0) { $q->whereMonth('date', $month); }
        }], 'amount')
        ->where('is_active', true)
        ->orderBy('code')
        ->get();

        // Ventilation par catégorie pour le rapport détaillé
        $byCategory = AnalyticLine::selectRaw('cost_center_id, category, SUM(amount) as total')
            ->whereYear('date', $year)
            ->when($month > 0, fn ($q) => $q->whereMonth('date', $month))
            ->groupBy('cost_center_id', 'category')
            ->get()
            ->groupBy('cost_center_id');

        return view('analytique.rapport', compact('query', 'byCategory', 'year', 'month'));
    }

    /** Enregistrer une ligne analytique manuelle. */
    public function storeLine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'date'           => ['required', 'date'],
            'label'          => ['required', 'string', 'max:200'],
            'category'       => ['required', 'in:matiere,main_oeuvre,energie,maintenance,emballage,overhead,autre'],
            'amount'         => ['required', 'numeric'],
        ]);

        $data['company_id'] = currentCompany()->id;
        $data['created_by'] = auth()->id();
        AnalyticLine::create($data);

        return back()->with('success', 'Ligne analytique enregistrée.');
    }
}
