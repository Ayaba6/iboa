<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NonConformityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index']);
        // Ouvrir et traiter une non-conformité est un acte QUALITÉ : `quality.manage`
        // ouvre au même titre que `production.update`. Ajout par OU, rien n'est retiré.
        $this->middleware('permission:production.update|quality.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $items = NonConformity::with(['responsible', 'inspection'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('severity'), fn ($q, $v) => $q->where('severity', $v))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'ouvertes'  => NonConformity::whereIn('status', ['ouverte', 'en_cours'])->count(),
            'critiques' => NonConformity::where('severity', 'critique')->whereIn('status', ['ouverte', 'en_cours'])->count(),
            'cloturees' => NonConformity::where('status', 'cloturee')->count(),
        ];

        return view('qualite.non-conformities.index', compact('items', 'stats'));
    }

    public function create(Request $request): View
    {
        $nc = new NonConformity(['severity' => 'mineure', 'status' => 'ouverte']);
        if ($i = $request->input('quality_inspection_id')) {
            $nc->quality_inspection_id = $i;
        }

        return view('qualite.non-conformities.form', $this->formData($nc));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        $data['reference']  = $request->input('reference') ?: $this->nextRef();
        $nc = NonConformity::create($data);
        $this->syncCharacteristics($nc, $request);

        // [CDC §13/§12.4] NC ouverte → Service Qualité notifié, criticité élevée
        // remonte aussi au Chef Atelier (impact production immédiat).
        $roles = $nc->severity === 'critique' ? ['responsable_qualite', 'chef_atelier'] : ['responsable_qualite'];
        \App\Notifications\ValidationStepNotification::sendToRoles(
            $roles,
            title: 'Non-conformité ouverte',
            message: "NC {$nc->reference} ({$nc->severity}) — traitement requis.",
            url: route('qualite.non-conformities.index'),
            modelType: 'NonConformity',
            modelId: $nc->id,
            type: 'non_conformity_opened',
            icon: 'exclamation-triangle',
            color: $nc->severity === 'critique' ? 'red' : 'amber',
        );

        return redirect()->route('qualite.non-conformities.index')->with('success', 'Non-conformité créée : ' . $data['reference']);
    }

    public function edit(NonConformity $nonConformity): View
    {
        $nonConformity->load('characteristics');

        return view('qualite.non-conformities.form', $this->formData($nonConformity));
    }

    public function update(Request $request, NonConformity $nonConformity): RedirectResponse
    {
        $data = $this->validateData($request);
        if ($data['status'] === 'cloturee' && ! $nonConformity->closed_at) {
            $data['closed_at'] = now();
        }
        $nonConformity->update($data);
        $nonConformity->characteristics()->delete();
        $this->syncCharacteristics($nonConformity, $request);

        return redirect()->route('qualite.non-conformities.index')->with('success', 'Non-conformité mise à jour.');
    }

    /** [Maquette NC] Enregistre les caractéristiques en défaut. */
    private function syncCharacteristics(NonConformity $nc, Request $request): void
    {
        foreach ((array) $request->input('characteristics', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $nc->characteristics()->create([
                'name'           => $row['name'],
                'spec_min'       => $row['spec_min'] ?? null,
                'spec_max'       => $row['spec_max'] ?? null,
                'unit'           => $row['unit'] ?? null,
                'measured_value' => $row['measured_value'] ?? null,
                'result'         => $row['result'] ?? null,
                'sort_order'     => $i,
            ]);
        }
    }

    public function destroy(NonConformity $nonConformity): RedirectResponse
    {
        $nonConformity->delete();

        return back()->with('success', 'Non-conformité supprimée.');
    }

    private function nextRef(): string
    {
        return 'NC-' . str_pad((string) (NonConformity::withoutGlobalScopes()->where('company_id', currentCompany()->id)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function formData(NonConformity $nc): array
    {
        return [
            'nc'          => $nc,
            'inspections' => QualityInspection::orderByDesc('id')->limit(100)->get(['id', 'reference', 'inspected_at']),
            'employees'   => Employee::orderBy('last_name')->get(),
            'products'    => \App\Models\Product::orderBy('name')->get(['id', 'name', 'reference']),
            'lines'       => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'name']),
            'workCenters' => \App\Modules\Production\Models\WorkCenter::orderBy('name')->get(['id', 'code', 'name']),
            'machines'    => \App\Modules\Production\Models\ProductionMachine::orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'quality_inspection_id' => ['nullable', 'integer', 'exists:quality_inspections,id'],
            'title'                 => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'severity'              => ['required', 'in:mineure,majeure,critique'],
            'status'                => ['required', 'in:ouverte,en_cours,cloturee'],
            // [CDC §12.4] Une NC clôturée doit documenter l'action corrective —
            // sinon le suivi de clôture (§16.9) perd toute valeur probante.
            'corrective_action'     => ['nullable', 'string', 'max:2000', 'required_if:status,cloturee'],
            'responsible_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'due_date'              => ['nullable', 'date'],
            // [Maquette NC] général
            'reference'             => ['nullable', 'string', 'max:30'],
            'nc_type'               => ['nullable', 'string', 'max:30'],
            'origin'                => ['nullable', 'string', 'max:30'],
            'category'              => ['nullable', 'string', 'max:30'],
            'atelier'               => ['nullable', 'string', 'max:60'],
            'production_line_id'    => ['nullable', 'integer', 'exists:production_lines,id'],
            'work_center_id'        => ['nullable', 'integer', 'exists:work_centers,id'],
            'machine_id'            => ['nullable', 'integer', 'exists:production_machines,id'],
            'product_id'            => ['nullable', 'integer', 'exists:products,id'],
            'lot_number'            => ['nullable', 'string', 'max:60'],
            'norm_reference'        => ['nullable', 'string', 'max:60'],
            'requirement'           => ['nullable', 'string', 'max:150'],
            'measured_value'        => ['nullable', 'string', 'max:30'],
            'deviation'             => ['nullable', 'string', 'max:20'],
            'deviation_unit'        => ['nullable', 'string', 'max:15'],
            'nc_quantity'           => ['nullable', 'numeric', 'min:0'],
            'nc_quantity_unit'      => ['nullable', 'string', 'max:15'],
            'detected_at'           => ['nullable', 'date'],
            'detected_by_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'comments'              => ['nullable', 'string', 'max:300'],
            // évaluation + classification
            'impact_quality'        => ['nullable', 'string', 'max:15'],
            'impact_cost'           => ['nullable', 'string', 'max:15'],
            'impact_delay'          => ['nullable', 'string', 'max:15'],
            'safety_risk'           => ['nullable', 'string', 'max:15'],
            'classification'        => ['nullable', 'string', 'max:20'],
            // disposition immédiate
            'immediate_action'          => ['nullable', 'string', 'max:30'],
            'isolated_quantity'         => ['nullable', 'numeric', 'min:0'],
            'isolated_quantity_unit'    => ['nullable', 'string', 'max:15'],
            'isolation_location'        => ['nullable', 'string', 'max:60'],
            'disposition_responsible_id'=> ['nullable', 'integer', 'exists:employees,id'],
            'isolated_at'               => ['nullable', 'date'],
            'disposition_comments'      => ['nullable', 'string', 'max:300'],
            // caractéristiques en défaut
            'characteristics'                  => ['nullable', 'array'],
            'characteristics.*.name'           => ['nullable', 'string', 'max:100'],
            'characteristics.*.spec_min'       => ['nullable', 'string', 'max:20'],
            'characteristics.*.spec_max'       => ['nullable', 'string', 'max:20'],
            'characteristics.*.unit'           => ['nullable', 'string', 'max:15'],
            'characteristics.*.measured_value' => ['nullable', 'string', 'max:30'],
            'characteristics.*.result'         => ['nullable', 'string', 'max:20'],
        ], [
            'corrective_action.required_if' => 'L\'action corrective est obligatoire pour clôturer une non-conformité.',
        ]) + [
            'client_claim'       => $request->boolean('client_claim'),
            'production_stopped' => $request->boolean('production_stopped'),
            'isolation_needed'   => $request->boolean('isolation_needed'),
            'product_isolated'   => $request->boolean('product_isolated'),
        ];
    }
}
