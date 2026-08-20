<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Modules\Quality\Models\ControlPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * [QUA-01] Plans de contrôle qualité.
 */
class ControlPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        // Définir un plan de contrôle est un acte QUALITÉ : `quality.manage` ouvre
        // au même titre que `production.update`. Ajout par OU, rien n'est retiré.
        $this->middleware('permission:production.update|quality.manage')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $items = ControlPlan::with(['product', 'family'])
            ->withCount('characteristics')
            ->when($request->input('stage'), fn ($q, $v) => $q->where('stage', $v))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->input('active') === '1'))
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")->orWhere('reference', 'like', "%{$v}%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('qualite.control-plans.index', compact('items'));
    }

    public function create(): View
    {
        return view('qualite.control-plans.form', $this->formData(new ControlPlan([
            'stage' => 'production', 'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request) {
            $data = $this->validateData($request);
            $data['company_id'] = currentCompany()->id;
            $data['created_by'] = auth()->id();
            $plan = ControlPlan::create($data);
            $this->syncCharacteristics($plan, $request);

            return $plan;
        });

        return redirect()->route('qualite.control-plans.show', $plan)->with('success', 'Plan de contrôle créé.');
    }

    public function show(ControlPlan $controlPlan): View
    {
        $controlPlan->load(['product', 'family', 'characteristics']);

        return view('qualite.control-plans.show', ['plan' => $controlPlan]);
    }

    public function edit(ControlPlan $controlPlan): View
    {
        $controlPlan->load('characteristics');

        return view('qualite.control-plans.form', $this->formData($controlPlan));
    }

    public function update(Request $request, ControlPlan $controlPlan): RedirectResponse
    {
        DB::transaction(function () use ($request, $controlPlan) {
            $controlPlan->update($this->validateData($request));
            $this->syncCharacteristics($controlPlan, $request);
        });

        return redirect()->route('qualite.control-plans.show', $controlPlan)->with('success', 'Plan de contrôle mis à jour.');
    }

    public function destroy(ControlPlan $controlPlan): RedirectResponse
    {
        $controlPlan->delete();

        return redirect()->route('qualite.control-plans.index')->with('success', 'Plan de contrôle supprimé.');
    }

    private function formData(ControlPlan $plan): array
    {
        return [
            'plan'       => $plan,
            'products'   => Product::orderBy('name')->get(['id', 'name', 'code_article as code']),
            'families'   => ProductFamily::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'reference'         => ['nullable', 'string', 'max:60'],
            'name'              => ['required', 'string', 'max:150'],
            'product_id'        => ['nullable', 'exists:products,id'],
            'product_family_id' => ['nullable', 'exists:product_families,id'],
            'stage'             => ['required', 'in:'.implode(',', array_keys(ControlPlan::STAGES))],
            'is_active'         => ['boolean'],
            'description'       => ['nullable', 'string', 'max:1000'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /** Réécrit les caractéristiques du plan à partir du tableau soumis. */
    private function syncCharacteristics(ControlPlan $plan, Request $request): void
    {
        $rows = collect($request->input('characteristics', []))
            ->filter(fn ($r) => filled($r['name'] ?? null))
            ->values();

        $dec = fn ($v) => ($v === null || $v === '') ? null : $v;

        $plan->characteristics()->delete();
        foreach ($rows as $i => $r) {
            $plan->characteristics()->create([
                'sort_order'    => $i + 1,
                'name'          => $r['name'],
                'method'        => $r['method'] ?? null,
                'unit'          => $r['unit'] ?? null,
                'frequency'     => $r['frequency'] ?? null,
                'sampling'      => $r['sampling'] ?? null,
                'target_value'  => $dec($r['target_value'] ?? null),
                'tolerance_min' => $dec($r['tolerance_min'] ?? null),
                'tolerance_max' => $dec($r['tolerance_max'] ?? null),
                'is_critical'   => ! empty($r['is_critical']),
                'responsible'   => $r['responsible'] ?? null,
            ]);
        }
    }
}
